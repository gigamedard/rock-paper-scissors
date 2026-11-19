// Watches unique off-ramp AVAXe deposit addresses on Pingala.
// Exact auto-approved orders <= OFFRAMP_AUTO_MAX_USDT; larger/mismatch manual.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, Contract, parseUnits, formatUnits, zeroPadValue } from 'ethers';

const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
const RPC_URL = process.env.PINGALA_RPC_URL || process.env.FUJI_RPC_URL;
const TOKEN = process.env.AVAXE_ADDRESS || '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7';
const CONFIRMATIONS = Number(process.env.OFFRAMP_PINGALA_CONFIRMATIONS || 2);
const POLL_MS = Number(process.env.OFFRAMP_WATCHER_POLL_MS || 8000);
const LOOKBACK = Math.max(0, Number(process.env.OFFRAMP_WATCHER_LOOKBACK_BLOCKS || 5000));
const CHUNK = Math.max(1, Math.min(2000, Number(process.env.OFFRAMP_WATCHER_CHUNK_BLOCKS || 500)));
const AUTO_MAX_USDT = Number(process.env.OFFRAMP_AUTO_MAX_USDT || 50);
const AUTO_ENABLED = String(process.env.OFFRAMP_AUTO_PAYOUT_ENABLED || 'true').toLowerCase() === 'true';
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '').split(',').map(s => s.trim()).filter(Boolean);

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}
const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
if (!DB_PASSWORD) throw new Error('DB password missing');
const pool = mysql.createPool({host:process.env.DB_HOST||'db',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USERNAME||'rps_app',password:DB_PASSWORD,database:process.env.DB_DATABASE||'rock_paper_scissors',waitForConnections:true,connectionLimit:4,charset:'utf8mb4',supportBigNumbers:true,bigNumberStrings:true});
const sleep = ms => new Promise(r => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);
const provider = new JsonRpcProvider(RPC_URL);
const tokenRead = new Contract(TOKEN, ['function decimals() view returns (uint8)'], provider);
let decimals = 18;
let cursor = null;
let lastErrorLog = 0;

async function notify(chatId, text) {
  if (!TELEGRAM_TOKEN || !chatId) return;
  try { const r = await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({chat_id:String(chatId),text,disable_web_page_preview:true})}); if (!r.ok) log('telegram notify failed', chatId, r.status); } catch (e) { log('telegram notify error', chatId, e.message); }
}
async function notifyAdmins(text) { for (const id of TELEGRAM_ADMIN_IDS) await notify(id, text); }
async function event(conn, orderId, type, payload) {
  await conn.query(`INSERT INTO offramp_events (offramp_order_id,type,actor_type,actor_id,payload,created_at,updated_at) VALUES (?,?, 'system','offramp-watcher',?,NOW(),NOW())`, [orderId, type, payload ? JSON.stringify(payload) : null]);
}
async function loadPending() {
  const [rows] = await pool.query(`SELECT o.id,o.reference,o.user_id,o.channel,o.chat_id,o.payout_address,o.avaxe_amount,o.usdt_amount,o.status,o.tx_hash,o.deposit_address,u.kyc_status,u.onramp_allowed FROM offramp_orders o JOIN users u ON u.id=o.user_id WHERE o.status IN ('pending_deposit','pending_review') AND o.deposit_index IS NOT NULL AND o.deposit_address IS NOT NULL AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY o.id ASC LIMIT 200`);
  return rows;
}
async function processLog(order, txHash, raw, blockNumber, currentBlock) {
  if (order.tx_hash && String(order.tx_hash).toLowerCase() !== txHash) {
    log('offramp order already has another deposit tx', order.reference, order.tx_hash, txHash);
    return;
  }
  const [[dupe]] = await pool.query('SELECT id FROM offramp_orders WHERE tx_hash = ? AND id <> ? LIMIT 1', [txHash, order.id]);
  if (dupe) { log('offramp deposit tx used by another order', txHash, order.id, dupe.id); await notifyAdmins(`⚠️ Dépôt AVAXe déjà utilisé\nTx: ${txHash}\nCommande: ${order.reference}`); return; }
  const expected = parseUnits(String(order.avaxe_amount), decimals);
  const tolerance = parseUnits('0.0001', decimals);
  const matches = raw >= expected - tolerance && raw <= expected + tolerance;
  const confirmations = currentBlock - blockNumber + 1;
  const amount = formatUnits(raw, decimals);
  const auto = AUTO_ENABLED && matches && Number(order.usdt_amount) <= AUTO_MAX_USDT && order.kyc_status === 'approved' && !!order.onramp_allowed;

  if (!matches) {
    const reason = raw < expected ? 'Montant AVAXe insuffisant' : 'Montant AVAXe supérieur au package';
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [res] = await conn.query(`UPDATE offramp_orders SET status='pending_review',tx_hash=?,deposit_amount=?,confirmations=?,submitted_at=COALESCE(submitted_at,NOW()),deposit_detected_at=NOW(),admin_note=?,updated_at=NOW() WHERE id=? AND status IN ('pending_deposit','pending_review') AND (tx_hash IS NULL OR tx_hash=?)`, [txHash, amount, confirmations, reason, order.id, txHash]);
      if (res.affectedRows === 1) await event(conn, order.id, 'deposit_manual_review', {tx_hash:txHash,amount,reason});
      await conn.commit();
      if (res.affectedRows === 1) {
        log('offramp manual amount review', order.reference, reason);
        if (order.channel === 'telegram' && order.chat_id) await notify(order.chat_id, `⚠️ Dépôt AVAXe détecté pour ${order.reference}, mais le montant ne correspond pas.\nUn administrateur va vérifier.`);
        await notifyAdmins(`⚠️ Off-ramp à vérifier\nCommande: ${order.reference}\nUser: #${order.user_id}\nTx: ${txHash}\nReçu: ${amount} AVAXe\nAttendu: ${order.avaxe_amount} AVAXe\n${reason}`);
      }
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
    return;
  }

  if (!auto && AUTO_ENABLED && Number(order.usdt_amount) > AUTO_MAX_USDT) {
    const reason = `Montant USDT > seuil auto (${AUTO_MAX_USDT} USDT)`;
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [res] = await conn.query(`UPDATE offramp_orders SET status='pending_review',tx_hash=?,deposit_amount=?,confirmations=?,submitted_at=COALESCE(submitted_at,NOW()),deposit_detected_at=NOW(),admin_note=?,updated_at=NOW() WHERE id=? AND status IN ('pending_deposit','pending_review') AND (tx_hash IS NULL OR tx_hash=?)`, [txHash, amount, confirmations, reason, order.id, txHash]);
      if (res.affectedRows === 1) await event(conn, order.id, 'deposit_manual_review', {tx_hash:txHash,amount,reason});
      await conn.commit();
      if (res.affectedRows === 1) await notifyAdmins(`⚠️ Off-ramp en revue\nCommande: ${order.reference}\n${reason}\nTx: ${txHash}`);
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
    return;
  }

  if (!auto) {
    const reason = 'KYC/allowlist ou auto-payout non validé';
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [res] = await conn.query(`UPDATE offramp_orders SET status='pending_review',tx_hash=?,deposit_amount=?,confirmations=?,submitted_at=COALESCE(submitted_at,NOW()),deposit_detected_at=NOW(),admin_note=?,updated_at=NOW() WHERE id=? AND status IN ('pending_deposit','pending_review') AND (tx_hash IS NULL OR tx_hash=?)`, [txHash, amount, confirmations, reason, order.id, txHash]);
      if (res.affectedRows === 1) await event(conn, order.id, 'deposit_manual_review', {tx_hash:txHash,amount,reason});
      await conn.commit();
      if (res.affectedRows === 1) await notifyAdmins(`⚠️ Off-ramp en revue\nCommande: ${order.reference}\n${reason}\nTx: ${txHash}`);
    } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
    return;
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [res] = await conn.query(`UPDATE offramp_orders SET status='approved',tx_hash=?,deposit_amount=?,confirmations=?,submitted_at=COALESCE(submitted_at,NOW()),deposit_detected_at=NOW(),deposit_confirmed_at=NOW(),approved_at=NOW(),admin_id=NULL,admin_note='Auto: dépôt AVAXe confirmé',updated_at=NOW() WHERE id=? AND status IN ('pending_deposit','pending_review') AND (tx_hash IS NULL OR tx_hash=?)`, [txHash, amount, confirmations, order.id, txHash]);
    if (res.affectedRows === 1) {
      await event(conn, order.id, 'deposit_confirmed', {tx_hash:txHash,amount,confirmations});
      await event(conn, order.id, 'order_auto_approved', {max_usdt:AUTO_MAX_USDT,tx_hash:txHash});
    }
    await conn.commit();
    if (res.affectedRows === 1) {
      log('offramp auto-approved', order.reference, txHash, `${amount} AVAXe`);
      if (order.channel === 'telegram' && order.chat_id) await notify(order.chat_id, `✅ Dépôt AVAXe confirmé pour ${order.reference}.\nPaiement de ${order.usdt_amount} USDT en cours.`);
      await notifyAdmins(`✅ Off-ramp auto-approuvé\nCommande: ${order.reference}\nUser: #${order.user_id}\n${amount} AVAXe → ${order.usdt_amount} USDT\nTx: ${txHash}`);
    }
  } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
}
async function scan() {
  const current = await provider.getBlockNumber();
  const safe = current - CONFIRMATIONS + 1;
  if (safe <= 0) return;
  if (cursor === null) cursor = Math.max(0, safe - LOOKBACK);
  if (safe <= cursor) return;
  const orders = await loadPending();
  if (!orders.length) { cursor = safe; return; }
  const byAddress = new Map(orders.map(o => [o.deposit_address.toLowerCase(), o]));
  const addresses = [...byAddress.keys()];
  let from = cursor + 1;
  while (from <= safe) {
    const to = Math.min(from + CHUNK - 1, safe);
    let logs = [];
    try {
      for (let ai = 0; ai < addresses.length; ai += 50) {
        const padded = addresses.slice(ai, ai + 50).map(a => zeroPadValue(a, 32));
        logs = logs.concat(await provider.getLogs({address:TOKEN,topics:[TRANSFER_TOPIC,null,padded],fromBlock:from,toBlock:to}));
      }
    } catch (e) {
      if (Date.now() - lastErrorLog > 60000) { log('getLogs error', from, to, e.shortMessage || e.message); lastErrorLog = Date.now(); }
      break;
    }
    for (const l of logs) {
      if (!l.topics || l.topics.length < 3) continue;
      const toAddr = '0x' + l.topics[2].slice(26);
      const order = byAddress.get(toAddr.toLowerCase());
      if (!order) continue;
      try { await processLog(order, String(l.transactionHash).toLowerCase(), BigInt(l.data), l.blockNumber, current); } catch (e) { log('process offramp error', order.reference, e.message); }
    }
    cursor = to; from = to + 1;
  }
}
async function main() {
  decimals = Number(await tokenRead.decimals().catch(() => 18));
  log('offramp watcher started', {token:TOKEN,decimals,confirmations:CONFIRMATIONS,auto_max_usdt:AUTO_MAX_USDT});
  while (true) { try { await scan(); } catch (e) { if (Date.now() - lastErrorLog > 60000) { log('scan error', e.shortMessage || e.message); lastErrorLog = Date.now(); } } await sleep(POLL_MS); }
}
process.on('SIGTERM', async () => { try { await pool.end(); } catch {} process.exit(0); });
process.on('SIGINT', async () => { try { await pool.end(); } catch {} process.exit(0); });
main().catch(e => { log('fatal', e.message); process.exit(1); });
