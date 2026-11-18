// Semi-automatic on-ramp credit worker.
// Claims admin-approved orders, sends AVAXe from the treasury hot wallet,
// then marks the order credited. No private key is ever logged.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { ethers } from 'ethers';

const ABI = [
  'function decimals() view returns (uint8)',
  'function balanceOf(address) view returns (uint256)',
  'function transfer(address to, uint256 amount) returns (bool)',
];

const AVAXE_ADDRESS = process.env.AVAXE_ADDRESS || '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7';
const RPC_URL = process.env.PINGALA_RPC_URL || process.env.ONRAMP_RPC_URL;
const POLL_MS = Number(process.env.ONRAMP_WORKER_POLL_MS || 5000);
const BATCH = Math.max(1, Math.min(10, Number(process.env.ONRAMP_WORKER_BATCH || 3)));
const MIN_HOT_AVAXE = process.env.ONRAMP_WORKER_MIN_HOT_AVAXE || '1';
const MIN_GAS_TST = process.env.ONRAMP_WORKER_MIN_GAS_TST || '0.01';
const MAX_ORDER_AVAXE = process.env.ONRAMP_WORKER_MAX_ORDER_AVAXE || '10';
const TX_TIMEOUT_MS = Number(process.env.ONRAMP_WORKER_TX_TIMEOUT_MS || 120000);
const STALE_SECONDS = Number(process.env.ONRAMP_WORKER_STALE_SECONDS || 300);
const EXPECTED_TREASURY = (process.env.ONRAMP_TREASURY_ADDRESS || '').toLowerCase();

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}

const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
const PRIVATE_KEY = readSecret('/run/secrets/onramp_treasury_pk', 'ONRAMP_TREASURY_PK');
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '')
  .split(',').map((s) => s.trim()).filter(Boolean);

if (!RPC_URL) throw new Error('PINGALA_RPC_URL missing');
if (!PRIVATE_KEY) throw new Error('onramp treasury key missing');
if (!DB_PASSWORD) throw new Error('DB password missing');

const pool = mysql.createPool({
  host: process.env.DB_HOST || 'db',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USERNAME || 'rps_app',
  password: DB_PASSWORD,
  database: process.env.DB_DATABASE || 'rock_paper_scissors',
  waitForConnections: true,
  connectionLimit: 4,
  charset: 'utf8mb4',
  supportBigNumbers: true,
  bigNumberStrings: true,
});

const provider = new ethers.JsonRpcProvider(RPC_URL);
const wallet = new ethers.Wallet(PRIVATE_KEY, provider);
const token = new ethers.Contract(AVAXE_ADDRESS, ABI, wallet);
let decimals = 18;
let treasuryAddress = '';
let lastLowBalanceNotice = 0;

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...args) => console.log(new Date().toISOString(), ...args);

async function notifyTelegram(chatId, text) {
  if (!TELEGRAM_TOKEN || !chatId) return;
  try {
    const res = await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ chat_id: String(chatId), text, disable_web_page_preview: true }),
    });
    if (!res.ok) log('telegram notify failed', chatId, res.status);
  } catch (e) {
    log('telegram notify error', chatId, e.message);
  }
}

async function notifyAdmins(text) {
  for (const id of TELEGRAM_ADMIN_IDS) await notifyTelegram(id, text);
}

async function event(orderId, type, actorId, payload) {
  await pool.query(
    'INSERT INTO onramp_events (onramp_order_id, type, actor_type, actor_id, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
    [orderId, type, 'system', actorId, payload ? JSON.stringify(payload) : null],
  );
}

async function claimApproved() {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [rows] = await conn.query(
      `SELECT o.id, o.reference, o.user_id, o.channel, o.chat_id, o.avaxe_amount,
              o.credit_attempts, u.wallet_address, u.name
         FROM onramp_orders o
         JOIN users u ON u.id = o.user_id
        WHERE o.status = 'approved' AND o.credit_tx_hash IS NULL
        ORDER BY o.id ASC
        LIMIT ? FOR UPDATE`,
      [BATCH],
    );
    for (const row of rows) {
      await conn.query(
        `UPDATE onramp_orders
            SET status = 'crediting', credit_started_at = NOW(),
                credit_attempts = credit_attempts + 1, credit_last_error = NULL,
                updated_at = NOW()
          WHERE id = ?`,
        [row.id],
      );
    }
    await conn.commit();
    return rows;
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
}

async function markCredited(order, txHash) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [result] = await conn.query(
      `UPDATE onramp_orders
          SET status = 'credited', credit_tx_hash = ?, credited_at = NOW(),
              credit_last_error = NULL, credit_started_at = NULL, updated_at = NOW()
        WHERE id = ? AND status IN ('crediting', 'manual_review')`,
      [txHash.toLowerCase(), order.id],
    );
    if (result.affectedRows !== 1) {
      await conn.rollback();
      log('markCredited skipped: order not creditable', order.id, order.reference);
      return false;
    }
    await conn.query(
      `INSERT INTO onramp_events (onramp_order_id, type, actor_type, actor_id, payload, created_at, updated_at)
       VALUES (?, 'order_credited', 'system', 'onramp-worker', ?, NOW(), NOW())`,
      [order.id, JSON.stringify({ credit_tx_hash: txHash.toLowerCase() })],
    );
    await conn.commit();
    return true;
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
}

async function setManualReview(order, reason, txHash = null) {
  const message = String(reason || '').slice(0, 1000);
  await pool.query(
    `UPDATE onramp_orders
        SET status = 'manual_review', credit_last_error = ?,
            credit_tx_hash = COALESCE(?, credit_tx_hash),
            credit_started_at = NULL, updated_at = NOW()
      WHERE id = ? AND status = 'crediting'`,
    [message, txHash ? txHash.toLowerCase() : null, order.id],
  );
  await event(order.id, 'credit_manual_review', 'onramp-worker', { error: message, credit_tx_hash: txHash });
  await notifyAdmins(
    `⚠️ On-ramp en revue manuelle\nCommande: ${order.reference}\nUser: #${order.user_id}\nMontant: ${order.avaxe_amount} AVAXe\nRaison: ${message}${txHash ? `\nTx: ${txHash}` : ''}`,
  );
  log('manual review', order.reference, message, txHash || '');
}

async function releaseApproved(order, reason) {
  await pool.query(
    `UPDATE onramp_orders
        SET status = 'approved', credit_last_error = ?, credit_started_at = NULL, updated_at = NOW()
      WHERE id = ? AND status = 'crediting'`,
    [String(reason || '').slice(0, 1000), order.id],
  );
  await event(order.id, 'credit_deferred', 'onramp-worker', { error: String(reason || '') });
  log('deferred', order.reference, reason);
}

async function notifyLowBalance(reason) {
  const now = Date.now();
  if (now - lastLowBalanceNotice < 300000) return;
  lastLowBalanceNotice = now;
  log('low balance', reason);
  await notifyAdmins(`⛔ Crédit on-ramp en pause\n${reason}`);
}

async function recoverInFlight() {
  const [rows] = await pool.query(
    `SELECT id, reference, user_id, channel, chat_id, avaxe_amount, credit_tx_hash,
            credit_started_at, TIMESTAMPDIFF(SECOND, credit_started_at, NOW()) AS age_seconds
       FROM onramp_orders
      WHERE status = 'crediting'
      ORDER BY id ASC LIMIT 20`,
  );
  for (const order of rows) {
    const age = Number(order.age_seconds || 0);
    if (!order.credit_tx_hash) {
      if (age >= STALE_SECONDS) {
        await setManualReview(order, 'Claim sans hash (arrêt du worker avant envoi) — vérifier puis relancer.');
      }
      continue;
    }
    try {
      const receipt = await provider.getTransactionReceipt(order.credit_tx_hash);
      if (receipt) {
        if (Number(receipt.status) === 1) {
          if (await markCredited(order, order.credit_tx_hash)) {
            await notifyAdmins(`✅ On-ramp crédité (récupération)\nCommande: ${order.reference}\nTx: ${order.credit_tx_hash}`);
          }
        } else {
          await setManualReview(order, 'Transaction de crédit reverted.', order.credit_tx_hash);
        }
      } else if (age > Math.max(STALE_SECONDS, 900)) {
        await setManualReview(order, 'Transaction toujours sans receipt — vérifier le hash.', order.credit_tx_hash);
      }
    } catch (e) {
      log('recover error', order.reference, e.message);
    }
  }
}

async function processOrder(order) {
  let to;
  try {
    to = ethers.getAddress(order.wallet_address);
  } catch {
    await setManualReview(order, `Adresse wallet invalide: ${order.wallet_address || 'vide'}`);
    return;
  }

  let amount;
  try {
    amount = ethers.parseUnits(String(order.avaxe_amount), decimals);
  } catch {
    await setManualReview(order, `Montant AVAXe invalide: ${order.avaxe_amount}`);
    return;
  }
  if (amount <= 0n || amount > ethers.parseUnits(String(MAX_ORDER_AVAXE), decimals)) {
    await setManualReview(order, `Montant hors limite worker: ${order.avaxe_amount} AVAXe`);
    return;
  }

  let hotBalance;
  let gasBalance;
  try {
    hotBalance = await token.balanceOf(treasuryAddress);
    gasBalance = await provider.getBalance(treasuryAddress);
  } catch (e) {
    await releaseApproved(order, `RPC balance indisponible: ${e.shortMessage || e.message}`);
    return;
  }
  const reserve = ethers.parseUnits(String(MIN_HOT_AVAXE), decimals);
  if (hotBalance < amount + reserve) {
    await releaseApproved(order, `Hot wallet insuffisant: ${ethers.formatUnits(hotBalance, decimals)} AVAXe, requis ${order.avaxe_amount} + reserve ${MIN_HOT_AVAXE}`);
    await notifyLowBalance(`Hot wallet AVAXe bas: ${ethers.formatUnits(hotBalance, decimals)} AVAXe (commande ${order.reference}).`);
    return;
  }
  if (gasBalance < ethers.parseUnits(String(MIN_GAS_TST), 18)) {
    await releaseApproved(order, `Gas TST insuffisant: ${ethers.formatEther(gasBalance)} TST`);
    await notifyLowBalance(`Gas TST bas: ${ethers.formatEther(gasBalance)} TST.`);
    return;
  }

  let tx;
  try {
    log('sending', order.reference, order.avaxe_amount, 'AVAXe ->', to);
    tx = await token.transfer(to, amount);
  } catch (e) {
    // Do not auto-retry: a broadcast error can be ambiguous. Admin decides.
    await setManualReview(order, `Envoi AVAXe échoué: ${e.shortMessage || e.message}`);
    return;
  }

  try {
    await pool.query(
      `UPDATE onramp_orders SET credit_tx_hash = ?, updated_at = NOW()
        WHERE id = ? AND status = 'crediting'`,
      [tx.hash.toLowerCase(), order.id],
    );
  } catch (e) {
    log('store tx hash failed', order.reference, tx.hash, e.message);
  }
  log('broadcast', order.reference, tx.hash);

  try {
    const receipt = await tx.wait(1, TX_TIMEOUT_MS);
    if (receipt && Number(receipt.status) === 1) {
      if (await markCredited(order, tx.hash)) {
        log('credited', order.reference, tx.hash);
        if (order.channel === 'telegram' && order.chat_id) {
          await notifyTelegram(order.chat_id,
            `✅ Crédit AVAXe effectué\nCommande: ${order.reference}\nMontant: ${order.avaxe_amount} AVAXe\nTx: ${tx.hash}`);
        }
        await notifyAdmins(`✅ On-ramp crédité\nCommande: ${order.reference}\nUser: #${order.user_id}\nMontant: ${order.avaxe_amount} AVAXe\nTx: ${tx.hash}`);
      }
    } else {
      await setManualReview(order, 'Transaction de crédit sans statut success.', tx.hash);
    }
  } catch (e) {
    await setManualReview(order, `Attente receipt échouée/timeout: ${e.shortMessage || e.message}`, tx.hash);
  }
}

async function cycle() {
  if (String(process.env.ONRAMP_WORKER_PAUSED || '').toLowerCase() === 'true') return;
  await recoverInFlight();
  const orders = await claimApproved();
  for (const order of orders) await processOrder(order);
}

async function main() {
  treasuryAddress = await wallet.getAddress();
  if (EXPECTED_TREASURY && treasuryAddress.toLowerCase() !== EXPECTED_TREASURY) {
    throw new Error(`Treasury key mismatch: expected ${EXPECTED_TREASURY}, got ${treasuryAddress}`);
  }
  try { decimals = Number(await token.decimals()); } catch { decimals = 18; }
  const hot = await token.balanceOf(treasuryAddress).catch(() => 0n);
  log('onramp worker started', {
    treasury: treasuryAddress,
    avaxe: AVAXE_ADDRESS,
    hot_avaxe: ethers.formatUnits(hot, decimals),
    batch: BATCH,
    poll_ms: POLL_MS,
  });
  while (true) {
    try {
      await cycle();
    } catch (e) {
      log('cycle error', e.shortMessage || e.message);
    }
    await sleep(POLL_MS);
  }
}

process.on('SIGTERM', async () => { log('SIGTERM'); try { await pool.end(); } catch {} process.exit(0); });
process.on('SIGINT', async () => { log('SIGINT'); try { await pool.end(); } catch {} process.exit(0); });

main().catch((e) => { log('fatal', e.message); process.exit(1); });
