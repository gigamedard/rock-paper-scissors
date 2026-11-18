// Watches unique per-order USDT deposit addresses on BSC/Polygon.
// On a confirmed exact deposit, auto-approves the order for the credit worker
// (≤ ONRAMP_AUTO_CREDIT_MAX_USDT). Mismatches/over-limit go to manual review.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, Contract, parseUnits, formatUnits, zeroPadValue } from 'ethers';

const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
const POLL_MS = Number(process.env.ONRAMP_WATCHER_POLL_MS || 10000);
const LOOKBACK = Math.max(0, Number(process.env.ONRAMP_WATCHER_LOOKBACK_BLOCKS || 5000));
const CHUNK = Math.max(1, Math.min(2000, Number(process.env.ONRAMP_WATCHER_CHUNK_BLOCKS || 500)));
const AUTO_ENABLED = String(process.env.ONRAMP_AUTO_CREDIT_ENABLED || 'true').toLowerCase() === 'true';
const AUTO_MAX = Number(process.env.ONRAMP_AUTO_CREDIT_MAX_USDT || 50);
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '')
  .split(',').map((s) => s.trim()).filter(Boolean);

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}
const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
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

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...args) => console.log(new Date().toISOString(), ...args);

const NETWORKS = [
  {
    key: 'bep20',
    label: 'BNB Smart Chain (BEP20)',
    rpc: process.env.ONRAMP_BEP20_RPC_URL || 'https://bsc-dataseed.binance.org',
    token: process.env.ONRAMP_BEP20_USDT_CONTRACT || '0x55d398326f99059fF775485246999027B3197955',
    confirmations: Number(process.env.ONRAMP_USDT_BEP20_CONFIRMATIONS || 15),
  },
  {
    key: 'polygon',
    label: 'Polygon PoS',
    rpc: process.env.ONRAMP_POLYGON_RPC_URL || 'https://polygon-bor-rpc.publicnode.com',
    token: process.env.ONRAMP_POLYGON_USDT_CONTRACT || '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
    confirmations: Number(process.env.ONRAMP_USDT_POLYGON_CONFIRMATIONS || 30),
  },
];

for (const n of NETWORKS) {
  n.provider = new JsonRpcProvider(n.rpc);
  n.contract = new Contract(n.token, ['function decimals() view returns (uint8)'], n.provider);
  n.decimals = 18;
  n.cursor = null;
  n.lastErrorLog = 0;
}

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

async function event(conn, orderId, type, payload) {
  await conn.query(
    `INSERT INTO onramp_events (onramp_order_id, type, actor_type, actor_id, payload, created_at, updated_at)
     VALUES (?, ?, 'system', 'deposit-watcher', ?, NOW(), NOW())`,
    [orderId, type, payload ? JSON.stringify(payload) : null],
  );
}

async function loadPending() {
  const [rows] = await pool.query(
    `SELECT o.id, o.reference, o.user_id, o.channel, o.chat_id, o.network,
            o.deposit_address, o.usdt_amount, o.avaxe_amount, o.status, o.tx_hash,
            o.confirmations, u.kyc_status, u.onramp_allowed
       FROM onramp_orders o
       JOIN users u ON u.id = o.user_id
      WHERE o.status IN ('pending_deposit', 'pending_review')
        AND o.deposit_index IS NOT NULL
        AND o.deposit_address IS NOT NULL
        AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
      ORDER BY o.id ASC
      LIMIT 200`,
  );
  return rows;
}

async function processLog(network, order, txHash, rawAmount, blockNumber, currentBlock) {
  if (order.tx_hash && String(order.tx_hash).toLowerCase() !== txHash) {
    log('order already has another deposit tx', order.reference, order.tx_hash, txHash);
    return;
  }
  const [[dupe]] = await pool.query(
    'SELECT id FROM onramp_orders WHERE tx_hash = ? AND id <> ? LIMIT 1',
    [txHash, order.id],
  );
  if (dupe) {
    log('deposit tx already used by another order', txHash, order.id, dupe.id);
    await notifyAdmins(`⚠️ Dépôt USDT déjà utilisé\nTx: ${txHash}\nCommande: ${order.reference}`);
    return;
  }

  const expected = parseUnits(String(order.usdt_amount), network.decimals);
  const tolerance = parseUnits('0.01', network.decimals);
  const matches = rawAmount >= expected - tolerance && rawAmount <= expected + tolerance;
  const confirmations = currentBlock - blockNumber + 1;
  const nowMs = Date.now();

  if (!matches) {
    const reason = rawAmount < expected ? 'Montant reçu insuffisant' : 'Montant reçu supérieur au package';
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [res] = await conn.query(
        `UPDATE onramp_orders
            SET status = 'pending_review', tx_hash = ?, deposit_amount = ?, confirmations = ?,
                submitted_at = COALESCE(submitted_at, NOW()), deposit_detected_at = NOW(),
                admin_note = ?, updated_at = NOW()
          WHERE id = ? AND status IN ('pending_deposit', 'pending_review')
            AND (tx_hash IS NULL OR tx_hash = ?)`,
        [txHash, formatUnits(rawAmount, network.decimals), confirmations, reason, order.id, txHash],
      );
      if (res.affectedRows === 1) {
        await event(conn, order.id, 'deposit_manual_review', {
          tx_hash: txHash, amount: formatUnits(rawAmount, network.decimals), reason,
        });
      }
      await conn.commit();
      if (res.affectedRows === 1) {
        log('manual amount review', order.reference, txHash, reason);
        if (order.channel === 'telegram' && order.chat_id) {
          await notifyTelegram(order.chat_id,
            `⚠️ Dépôt détecté pour ${order.reference}, mais le montant ne correspond pas exactement.\nUn administrateur va vérifier.`);
        }
        await notifyAdmins(`⚠️ Dépôt USDT à vérifier\nCommande: ${order.reference}\nUser: #${order.user_id}\nTx: ${txHash}\nReçu: ${formatUnits(rawAmount, network.decimals)} USDT\nAttendu: ${order.usdt_amount} USDT\n${reason}`);
      }
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
    return;
  }

  if (!AUTO_ENABLED || Number(order.usdt_amount) > AUTO_MAX || order.kyc_status !== 'approved' || !order.onramp_allowed) {
    const reason = !AUTO_ENABLED
      ? 'Auto-crédit désactivé'
      : (Number(order.usdt_amount) > AUTO_MAX
        ? `Montant supérieur au seuil auto (${AUTO_MAX} USDT)`
        : 'KYC ou allowlist non validé');
    const conn = await pool.getConnection();
    try {
      await conn.beginTransaction();
      const [res] = await conn.query(
        `UPDATE onramp_orders
            SET status = 'pending_review', tx_hash = ?, deposit_amount = ?, confirmations = ?,
                submitted_at = COALESCE(submitted_at, NOW()), deposit_detected_at = NOW(),
                admin_note = ?, updated_at = NOW()
          WHERE id = ? AND status IN ('pending_deposit', 'pending_review')
            AND (tx_hash IS NULL OR tx_hash = ?)`,
        [txHash, formatUnits(rawAmount, network.decimals), confirmations, reason, order.id, txHash],
      );
      if (res.affectedRows === 1) {
        await event(conn, order.id, 'deposit_manual_review', {
          tx_hash: txHash, amount: formatUnits(rawAmount, network.decimals), reason,
        });
      }
      await conn.commit();
      if (res.affectedRows === 1) {
        log('manual policy review', order.reference, reason);
        await notifyAdmins(`⚠️ Dépôt USDT en revue\nCommande: ${order.reference}\nTx: ${txHash}\nRaison: ${reason}`);
      }
    } catch (e) {
      await conn.rollback();
      throw e;
    } finally {
      conn.release();
    }
    return;
  }

  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [res] = await conn.query(
      `UPDATE onramp_orders
          SET status = 'approved', tx_hash = ?, deposit_amount = ?, confirmations = ?,
              submitted_at = COALESCE(submitted_at, NOW()), deposit_detected_at = NOW(),
              deposit_confirmed_at = NOW(), approved_at = NOW(),
              admin_id = NULL, admin_note = 'Auto: dépôt USDT confirmé', updated_at = NOW()
        WHERE id = ? AND status IN ('pending_deposit', 'pending_review')
          AND (tx_hash IS NULL OR tx_hash = ?)`,
      [txHash, formatUnits(rawAmount, network.decimals), confirmations, order.id, txHash],
    );
    if (res.affectedRows === 1) {
      await event(conn, order.id, 'deposit_confirmed', {
        tx_hash: txHash, amount: formatUnits(rawAmount, network.decimals), confirmations,
      });
      await event(conn, order.id, 'order_auto_approved', {
        max_usdt: AUTO_MAX, tx_hash: txHash,
      });
    }
    await conn.commit();
    if (res.affectedRows === 1) {
      log('auto-approved', order.reference, txHash, `${formatUnits(rawAmount, network.decimals)} USDT`);
      if (order.channel === 'telegram' && order.chat_id) {
        await notifyTelegram(order.chat_id,
          `✅ Dépôt USDT confirmé pour ${order.reference}.\nCrédit de ${order.avaxe_amount} AVAXe en cours.`);
      }
      await notifyAdmins(`✅ Dépôt USDT auto-approuvé\nCommande: ${order.reference}\nUser: #${order.user_id}\nMontant: ${formatUnits(rawAmount, network.decimals)} USDT → ${order.avaxe_amount} AVAXe\nTx: ${txHash}`);
    }
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
}

async function scanNetwork(network) {
  const current = await network.provider.getBlockNumber();
  const safe = current - Math.max(0, network.confirmations) + 1;
  if (safe <= 0) return;
  if (network.cursor === null) {
    network.cursor = Math.max(0, safe - LOOKBACK);
  }
  if (safe <= network.cursor) return;

  const orders = (await loadPending()).filter((o) => o.network === network.key);
  if (orders.length === 0) {
    network.cursor = safe;
    return;
  }

  const byAddress = new Map();
  for (const o of orders) {
    if (o.deposit_address) byAddress.set(o.deposit_address.toLowerCase(), o);
  }
  const addresses = [...byAddress.keys()];
  let from = network.cursor + 1;
  while (from <= safe) {
    const to = Math.min(from + CHUNK - 1, safe);
    let logs = [];
    try {
      for (let ai = 0; ai < addresses.length; ai += 50) {
        const padded = addresses.slice(ai, ai + 50).map((a) => zeroPadValue(a, 32));
        const batch = await network.provider.getLogs({
          address: network.token,
          topics: [TRANSFER_TOPIC, null, padded],
          fromBlock: from,
          toBlock: to,
        });
        logs = logs.concat(batch);
      }
    } catch (e) {
      if (Date.now() - network.lastErrorLog > 60000) {
        log('getLogs error', network.key, from, to, e.shortMessage || e.message);
        network.lastErrorLog = Date.now();
      }
      break;
    }
    for (const l of logs) {
      if (!l.topics || l.topics.length < 3) continue;
      const toAddress = '0x' + l.topics[2].slice(26);
      const order = byAddress.get(toAddress.toLowerCase());
      if (!order) continue;
      const raw = BigInt(l.data);
      try {
        await processLog(network, order, String(l.transactionHash).toLowerCase(), raw, l.blockNumber, current);
      } catch (e) {
        log('process deposit error', network.key, order.reference, e.message);
      }
    }
    network.cursor = to;
    from = to + 1;
  }
}

async function initNetwork(network) {
  try {
    network.decimals = Number(await network.contract.decimals());
    log('watcher network ready', { key: network.key, token: network.token, decimals: network.decimals, confirmations: network.confirmations });
  } catch (e) {
    log('watcher network init error', network.key, e.shortMessage || e.message);
  }
}

async function main() {
  for (const network of NETWORKS) await initNetwork(network);
  log('deposit watcher started', { auto: AUTO_ENABLED, max_usdt: AUTO_MAX, lookback: LOOKBACK, chunk: CHUNK });
  while (true) {
    for (const network of NETWORKS) {
      try {
        await scanNetwork(network);
      } catch (e) {
        if (Date.now() - network.lastErrorLog > 60000) {
          log('scan error', network.key, e.shortMessage || e.message);
          network.lastErrorLog = Date.now();
        }
      }
    }
    await sleep(POLL_MS);
  }
}

process.on('SIGTERM', async () => { log('SIGTERM'); try { await pool.end(); } catch {} process.exit(0); });
process.on('SIGINT', async () => { log('SIGINT'); try { await pool.end(); } catch {} process.exit(0); });

main().catch((e) => { log('fatal', e.message); process.exit(1); });
