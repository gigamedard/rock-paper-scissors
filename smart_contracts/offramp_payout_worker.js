// Sends USDT on Polygon for approved off-ramp AVAXe orders.
// Treasury key is derived from the HD seed at index 1,000,000 (same USDT
// treasury as on-ramp). A MySQL lock serializes treasury transactions with
// the on-ramp gas-topup sweeper.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, HDNodeWallet, Contract, parseUnits, formatUnits, formatEther } from 'ethers';

const SEED_PATH = process.env.ONRAMP_DEPOSIT_SEED_FILE || '/run/secrets/onramp_deposit_seed';
const TREASURY_INDEX = Number(process.env.OFFRAMP_PAYOUT_TREASURY_INDEX || process.env.ONRAMP_SWEEP_TREASURY_INDEX || 1000000);
const RPC_URL = process.env.ONRAMP_POLYGON_RPC_URL || 'https://polygon-bor-rpc.publicnode.com';
const TOKEN = process.env.ONRAMP_POLYGON_USDT_CONTRACT || '0xc2132D05D31c914a87C6611C10748AEb04B58e8F';
const POLL_MS = Number(process.env.OFFRAMP_PAYOUT_POLL_MS || 10000);
const TX_TIMEOUT_MS = Number(process.env.OFFRAMP_PAYOUT_TX_TIMEOUT_MS || 120000);
const STALE_SECONDS = Number(process.env.OFFRAMP_PAYOUT_STALE_SECONDS || 300);
const MIN_POL = process.env.OFFRAMP_PAYOUT_MIN_POL || '0.02';
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '').split(',').map(s => s.trim()).filter(Boolean);
const LOCK_NAME = 'battlepool_treasury_polygon';

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}
const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
const SEED = readSecret(SEED_PATH, 'ONRAMP_DEPOSIT_SEED');
if (!DB_PASSWORD) throw new Error('DB password missing');
if (!SEED) throw new Error('seed missing');
const pool = mysql.createPool({host:process.env.DB_HOST||'db',port:Number(process.env.DB_PORT||3306),user:process.env.DB_USERNAME||'rps_app',password:DB_PASSWORD,database:process.env.DB_DATABASE||'rock_paper_scissors',waitForConnections:true,connectionLimit:4,charset:'utf8mb4',supportBigNumbers:true,bigNumberStrings:true});
const provider = new JsonRpcProvider(RPC_URL);
const treasury = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${TREASURY_INDEX}`).connect(provider);
const treasuryAddress = treasury.address;
const readToken = new Contract(TOKEN, ['function decimals() view returns (uint8)','function balanceOf(address) view returns (uint256)'], provider);
const writeToken = new Contract(TOKEN, ['function decimals() view returns (uint8)','function transfer(address to,uint256 amount) returns(bool)'], treasury);
const sleep = ms => new Promise(r => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString(), ...a);
let decimals = 6;

async function notify(chatId, text) {
  if (!TELEGRAM_TOKEN || !chatId) return;
  try { const r = await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`, {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({chat_id:String(chatId),text,disable_web_page_preview:true})}); if (!r.ok) log('telegram notify failed', chatId, r.status); } catch (e) { log('telegram notify error', chatId, e.message); }
}
async function notifyAdmins(text) { for (const id of TELEGRAM_ADMIN_IDS) await notify(id, text); }
async function event(orderId, type, payload) {
  await pool.query(`INSERT INTO offramp_events (offramp_order_id,type,actor_type,actor_id,payload,created_at,updated_at) VALUES (?,?, 'system','offramp-payout',?,NOW(),NOW())`, [orderId, type, payload ? JSON.stringify(payload) : null]);
}
async function withTreasuryLock(fn) {
  const conn = await pool.getConnection();
  try {
    const [[r]] = await conn.query('SELECT GET_LOCK(?, 30) AS ok', [LOCK_NAME]);
    if (!r || Number(r.ok) !== 1) throw new Error('treasury lock busy');
    try { return await fn(); } finally { await conn.query('SELECT RELEASE_LOCK(?)', [LOCK_NAME]); }
  } finally { conn.release(); }
}
async function release(order, reason) {
  await pool.query(`UPDATE offramp_orders SET status='payout_deferred',payout_last_error=?,payout_started_at=NULL,updated_at=NOW() WHERE id=? AND status='paying'`, [String(reason).slice(0,1000), order.id]);
  await event(order.id, 'payout_deferred', {reason});
  log('payout deferred', order.reference, reason);
}
async function manual(order, reason, txHash = null) {
  const message = String(reason).slice(0,1000);
  await pool.query(`UPDATE offramp_orders SET status='manual_review',payout_last_error=?,payout_tx_hash=COALESCE(?,payout_tx_hash),payout_started_at=NULL,updated_at=NOW() WHERE id=? AND status='paying'`, [message, txHash ? txHash.toLowerCase() : null, order.id]);
  await event(order.id, 'payout_manual_review', {error:message,tx_hash:txHash});
  await notifyAdmins(`⚠️ Payout USDT en revue\nCommande: ${order.reference}\nRaison: ${message}${txHash ? `\nTx: ${txHash}` : ''}\nTreasury: ${treasuryAddress}`);
  log('payout manual', order.reference, message);
}
async function finalize(order, txHash) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [res] = await conn.query(`UPDATE offramp_orders SET status='paid',payout_tx_hash=?,paid_at=NOW(),payout_last_error=NULL,payout_started_at=NULL,updated_at=NOW() WHERE id=? AND status='paying'`, [txHash.toLowerCase(), order.id]);
    if (res.affectedRows === 1) {
      await conn.query(`INSERT INTO offramp_events (offramp_order_id,type,actor_type,actor_id,payload,created_at,updated_at) VALUES (?, 'usdt_paid','system','offramp-payout',?,NOW(),NOW())`, [order.id, JSON.stringify({tx_hash:txHash.toLowerCase(),usdt:order.usdt_amount,tp:treasuryAddress})]);
    }
    await conn.commit();
    return res.affectedRows === 1;
  } catch (e) { await conn.rollback(); throw e; } finally { conn.release(); }
}
async function loadOrders() {
  const [rows] = await pool.query(`SELECT id,reference,user_id,channel,chat_id,payout_address,usdt_amount,status,payout_tx_hash,payout_attempts,TIMESTAMPDIFF(SECOND,payout_started_at,NOW()) AS age FROM offramp_orders WHERE payout_tx_hash IS NULL AND status IN ('approved','payout_deferred') ORDER BY id ASC LIMIT 10`);
  return rows;
}
async function recover() {
  const [rows] = await pool.query(`SELECT id,reference,user_id,channel,chat_id,payout_address,usdt_amount,status,payout_tx_hash,TIMESTAMPDIFF(SECOND,payout_started_at,NOW()) AS age FROM offramp_orders WHERE status='paying' ORDER BY id ASC LIMIT 20`);
  for (const order of rows) {
    const age = Number(order.age || 0);
    if (!order.payout_tx_hash) { if (age >= STALE_SECONDS) await manual(order, 'Payout interrompu avant broadcast USDT.'); continue; }
    try {
      const receipt = await provider.getTransactionReceipt(order.payout_tx_hash);
      if (receipt) {
        if (Number(receipt.status) === 1) { if (await finalize(order, order.payout_tx_hash)) await notifyAdmins(`✅ Payout USDT récupéré\nCommande: ${order.reference}\nTx: ${order.payout_tx_hash}`); }
        else await manual(order, 'Payout USDT reverted.', order.payout_tx_hash);
      } else if (age > Math.max(STALE_SECONDS, 900)) await manual(order, 'Payout sans receipt — vérifier le hash.', order.payout_tx_hash);
    } catch (e) { log('recover payout error', order.reference, e.message); }
  }
}
async function processOrder(order) {
  let to;
  try { to = requireAddress(order.payout_address); } catch { await manual(order, 'Adresse payout invalide.'); return; }
  let amount;
  try { amount = parseUnits(Number(order.usdt_amount).toFixed(decimals), decimals); } catch { await manual(order, 'Montant USDT invalide.'); return; }
  if (amount <= 0n) { await manual(order, 'Montant USDT nul.'); return; }

  let usdtBalance, polBalance;
  try {
    usdtBalance = await readToken.balanceOf(treasuryAddress);
    polBalance = await provider.getBalance(treasuryAddress);
  } catch (e) { await release(order, `RPC trésorerie: ${e.shortMessage || e.message}`); return; }
  if (usdtBalance < amount) { await release(order, `USDT trésorerie insuffisant: ${formatUnits(usdtBalance, decimals)} USDT`); await notifyAdmins(`⛔ USDT trésorerie insuffisant\nSolde: ${formatUnits(usdtBalance, decimals)} USDT\nRequis: ${order.usdt_amount} USDT\nCommande: ${order.reference}`); return; }
  if (polBalance < parseUnits(MIN_POL, 18)) { await release(order, `POL gas insuffisant: ${formatEther(polBalance)} POL`); await notifyAdmins(`⛔ POL gas insuffisant pour payout: ${formatEther(polBalance)} POL`); return; }

  let tx;
  try {
    tx = await withTreasuryLock(async () => {
      const t = await writeToken.transfer(to, amount);
      await pool.query(`UPDATE offramp_orders SET payout_tx_hash=?,payout_attempts=payout_attempts+1,payout_started_at=NOW(),payout_last_error=NULL,updated_at=NOW() WHERE id=? AND status='paying'`, [t.hash.toLowerCase(), order.id]);
      return t;
    });
  } catch (e) { await manual(order, `Broadcast payout USDT échoué: ${e.shortMessage || e.message}`); return; }
  log('payout broadcast', order.reference, tx.hash);
  try {
    const receipt = await tx.wait(1, TX_TIMEOUT_MS);
    if (receipt && Number(receipt.status) === 1) {
      if (await finalize(order, tx.hash)) {
        log('paid', order.reference, tx.hash);
        if (order.channel === 'telegram' && order.chat_id) await notify(order.chat_id, `✅ Off-ramp ${order.reference} payé\n${order.usdt_amount} USDT envoyés sur ${order.payout_address}\nTx: ${tx.hash}`);
        await notifyAdmins(`✅ Off-ramp payé\nCommande: ${order.reference}\nUser: #${order.user_id}\n${order.avaxe_amount} AVAXe → ${order.usdt_amount} USDT\nTx: ${tx.hash}`);
      }
    } else await manual(order, 'Payout sans statut success.', tx.hash);
  } catch (e) { await manual(order, `Attente receipt payout: ${e.shortMessage || e.message}`, tx.hash); }
}
function requireAddress(a) {
  if (!/^0x[0-9a-fA-F]{40}$/.test(String(a))) throw new Error('bad address');
  return a;
}
async function claim(order) {
  const [res] = await pool.query(`UPDATE offramp_orders SET status='paying',payout_attempts=payout_attempts+1,payout_started_at=NOW(),payout_last_error=NULL,updated_at=NOW() WHERE id=? AND status IN ('approved','payout_deferred')`, [order.id]);
  return res.affectedRows === 1;
}
async function main() {
  decimals = Number(await readToken.decimals().catch(() => 6));
  log('offramp payout worker started', {treasury:treasuryAddress,token:TOKEN,decimals,usdt:formatUnits(await readToken.balanceOf(treasuryAddress).catch(() => 0n), decimals),pol:formatEther(await provider.getBalance(treasuryAddress).catch(() => 0n))});
  while (true) {
    try {
      await recover();
      const orders = await loadOrders();
      for (const order of orders) if (await claim(order)) await processOrder(order);
    } catch (e) { log('payout cycle error', e.message); }
    await sleep(POLL_MS);
  }
}
process.on('SIGTERM', async () => { try { await pool.end(); } catch {} process.exit(0); });
process.on('SIGINT', async () => { try { await pool.end(); } catch {} process.exit(0); });
main().catch(e => { log('fatal', e.message); process.exit(1); });
