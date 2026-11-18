// Sweeps confirmed USDT deposits from unique order addresses to a treasury
// address. Native BNB/POL gas is topped up from the same treasury HD wallet.
// The HD seed is read from /run/secrets/onramp_deposit_seed and never logged.
import fs from 'node:fs';
import mysql from 'mysql2/promise';
import { JsonRpcProvider, HDNodeWallet, Contract, parseEther, formatEther, formatUnits } from 'ethers';

const SEED_PATH = process.env.ONRAMP_DEPOSIT_SEED_FILE || '/run/secrets/onramp_deposit_seed';
const TREASURY_INDEX = Number(process.env.ONRAMP_SWEEP_TREASURY_INDEX || 1000000);
const EXPECTED_TREASURY = (process.env.ONRAMP_SWEEP_TREASURY_ADDRESS || '').toLowerCase();
const POLL_MS = Number(process.env.ONRAMP_SWEEP_POLL_MS || 15000);
const TX_TIMEOUT_MS = Number(process.env.ONRAMP_SWEEP_TX_TIMEOUT_MS || 120000);
const STALE_SECONDS = Number(process.env.ONRAMP_SWEEP_STALE_SECONDS || 300);
const TELEGRAM_TOKEN = process.env.TELEGRAM_BOT_TOKEN || '';
const TELEGRAM_ADMIN_IDS = String(process.env.TELEGRAM_ADMIN_IDS || '')
  .split(',').map((s) => s.trim()).filter(Boolean);

function readSecret(path, envName) {
  if (process.env[envName]) return String(process.env[envName]).trim();
  try { return fs.readFileSync(path, 'utf8').trim(); } catch { return ''; }
}
const DB_PASSWORD = readSecret('/run/secrets/db_app_password', 'DB_PASSWORD');
const SEED = readSecret(SEED_PATH, 'ONRAMP_DEPOSIT_SEED');
if (!DB_PASSWORD) throw new Error('DB password missing');
if (!SEED) throw new Error('deposit seed missing');

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
    label: 'BSC',
    rpc: process.env.ONRAMP_BEP20_RPC_URL || 'https://bsc-rpc.publicnode.com',
    token: process.env.ONRAMP_BEP20_USDT_CONTRACT || '0x55d398326f99059fF775485246999027B3197955',
    symbol: 'BNB',
    gasMin: process.env.ONRAMP_BEP20_GAS_MIN || '0.0001',
    gasTopup: process.env.ONRAMP_BEP20_GAS_TOPUP || '0.0003',
    gasReserve: process.env.ONRAMP_BEP20_GAS_RESERVE || '0.001',
  },
  {
    key: 'polygon',
    label: 'Polygon',
    rpc: process.env.ONRAMP_POLYGON_RPC_URL || 'https://polygon-bor-rpc.publicnode.com',
    token: process.env.ONRAMP_POLYGON_USDT_CONTRACT || '0xc2132D05D31c914a87C6611C10748AEb04B58e8F',
    symbol: 'POL',
    gasMin: process.env.ONRAMP_POLYGON_GAS_MIN || '0.002',
    gasTopup: process.env.ONRAMP_POLYGON_GAS_TOPUP || '0.02',
    gasReserve: process.env.ONRAMP_POLYGON_GAS_RESERVE || '0.05',
  },
];

const treasuryNode = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${TREASURY_INDEX}`);
const treasuryAddress = treasuryNode.address;
if (EXPECTED_TREASURY && treasuryAddress.toLowerCase() !== EXPECTED_TREASURY) {
  throw new Error(`Treasury address mismatch: expected ${EXPECTED_TREASURY}, got ${treasuryAddress}`);
}

for (const n of NETWORKS) {
  n.provider = new JsonRpcProvider(n.rpc);
  n.tokenContractRead = new Contract(n.token, ['function decimals() view returns (uint8)', 'function balanceOf(address) view returns (uint256)'], n.provider);
  n.treasuryWallet = treasuryNode.connect(n.provider);
  n.decimals = 18;
  n.lastErrorLog = 0;
  n.gasNotices = 0;
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
  } catch (e) { log('telegram notify error', chatId, e.message); }
}
async function notifyAdmins(text) {
  for (const id of TELEGRAM_ADMIN_IDS) await notifyTelegram(id, text);
}

async function event(orderId, type, payload) {
  await pool.query(
    `INSERT INTO onramp_events (onramp_order_id, type, actor_type, actor_id, payload, created_at, updated_at)
     VALUES (?, ?, 'system', 'onramp-sweeper', ?, NOW(), NOW())`,
    [orderId, type, payload ? JSON.stringify(payload) : null],
  );
}

async function loadSweeps() {
  const [rows] = await pool.query(
    `SELECT id, reference, user_id, network, deposit_index, deposit_address, deposit_amount,
            status, sweep_status, sweep_tx_hash, sweep_attempts,
            TIMESTAMPDIFF(SECOND, sweep_started_at, NOW()) AS sweep_age
       FROM onramp_orders
      WHERE sweep_tx_hash IS NULL
        AND deposit_index IS NOT NULL
        AND deposit_amount > 0
        AND status IN ('approved', 'crediting', 'credited')
        AND (sweep_status IS NULL OR sweep_status IN ('pending', 'gas_deferred'))
      ORDER BY id ASC LIMIT 10`,
  );
  return rows;
}

async function claim(order) {
  const [res] = await pool.query(
    `UPDATE onramp_orders
        SET sweep_status = 'sweeping', sweep_attempts = sweep_attempts + 1,
            sweep_started_at = NOW(), sweep_last_error = NULL, updated_at = NOW()
      WHERE id = ? AND (sweep_status IS NULL OR sweep_status IN ('pending', 'gas_deferred'))`,
    [order.id],
  );
  return res.affectedRows === 1;
}

async function release(order, status, reason) {
  await pool.query(
    `UPDATE onramp_orders SET sweep_status = ?, sweep_last_error = ?, sweep_started_at = NULL, updated_at = NOW()
      WHERE id = ? AND sweep_status = 'sweeping'`,
    [status, String(reason || '').slice(0, 1000), order.id],
  );
  await event(order.id, status === 'gas_deferred' ? 'sweep_gas_deferred' : 'sweep_deferred', { reason });
}

async function manual(order, reason, txHash = null) {
  const message = String(reason || '').slice(0, 1000);
  await pool.query(
    `UPDATE onramp_orders
        SET sweep_status = 'manual_review', sweep_last_error = ?,
            sweep_tx_hash = COALESCE(?, sweep_tx_hash), sweep_started_at = NULL, updated_at = NOW()
      WHERE id = ? AND sweep_status = 'sweeping'`,
    [message, txHash ? txHash.toLowerCase() : null, order.id],
  );
  await event(order.id, 'sweep_manual_review', { error: message, sweep_tx_hash: txHash });
  await notifyAdmins(`⚠️ Sweep USDT en revue manuelle\nCommande: ${order.reference}\nRaison: ${message}${txHash ? `\nTx: ${txHash}` : ''}\nTreasury: ${treasuryAddress}`);
  log('sweep manual review', order.reference, message, txHash || '');
}

async function finalize(order, txHash, amount) {
  const conn = await pool.getConnection();
  try {
    await conn.beginTransaction();
    const [res] = await conn.query(
      `UPDATE onramp_orders
          SET sweep_status = 'swept', sweep_tx_hash = ?, sweep_amount = ?,
              swept_at = NOW(), sweep_last_error = NULL, sweep_started_at = NULL, updated_at = NOW()
        WHERE id = ? AND sweep_status = 'sweeping'`,
      [txHash.toLowerCase(), amount, order.id],
    );
    if (res.affectedRows === 1) {
      await conn.query(
        `INSERT INTO onramp_events (onramp_order_id, type, actor_type, actor_id, payload, created_at, updated_at)
         VALUES (?, 'usdt_swept', 'system', 'onramp-sweeper', ?, NOW(), NOW())`,
        [order.id, JSON.stringify({ tx_hash: txHash.toLowerCase(), amount, treasury: treasuryAddress })],
      );
    }
    await conn.commit();
    return res.affectedRows === 1;
  } catch (e) {
    await conn.rollback();
    throw e;
  } finally {
    conn.release();
  }
}

async function recoverInFlight() {
  const [rows] = await pool.query(
    `SELECT id, reference, user_id, network, deposit_index, deposit_address, deposit_amount,
            sweep_status, sweep_tx_hash, TIMESTAMPDIFF(SECOND, sweep_started_at, NOW()) AS sweep_age
       FROM onramp_orders WHERE sweep_status = 'sweeping' ORDER BY id ASC LIMIT 20`,
  );
  for (const order of rows) {
    const age = Number(order.sweep_age || 0);
    if (!order.sweep_tx_hash) {
      if (age >= STALE_SECONDS) await manual(order, 'Sweep interrompu avant envoi USDT — vérifier les soldes.');
      continue;
    }
    try {
      const network = NETWORKS.find((n) => n.key === order.network);
      if (!network) continue;
      const receipt = await network.provider.getTransactionReceipt(order.sweep_tx_hash);
      if (receipt) {
        if (Number(receipt.status) === 1) {
          if (await finalize(order, order.sweep_tx_hash, order.deposit_amount)) {
            await notifyAdmins(`✅ Sweep USDT récupéré\nCommande: ${order.reference}\nTx: ${order.sweep_tx_hash}`);
          }
        } else {
          await manual(order, 'Transaction de sweep reverted.', order.sweep_tx_hash);
        }
      } else if (age > Math.max(STALE_SECONDS, 900)) {
        await manual(order, 'Sweep sans receipt — vérifier le hash.', order.sweep_tx_hash);
      }
    } catch (e) {
      log('recover sweep error', order.reference, e.message);
    }
  }
}

async function sendGasTopup(network, order, depositAddress) {
  const topup = parseEther(network.gasTopup);
  const reserve = parseEther(network.gasReserve);
  const balance = await network.provider.getBalance(treasuryAddress);
  if (balance < topup + reserve) {
    const now = Date.now();
    if (!network.gasNotices || now - network.gasNotices > 300000) {
      network.gasNotices = now;
      const msg = `⛔ Gas ${network.symbol} insuffisant pour sweeper\nTreasury: ${treasuryAddress}\nSolde: ${formatEther(balance)} ${network.symbol}\nRequis: ${network.gasTopup} + réserve ${network.gasReserve} ${network.symbol}\nCommande: ${order.reference}`;
      log('gas low', network.key, formatEther(balance));
      await notifyAdmins(msg);
    }
    await release(order, 'gas_deferred', `Gas ${network.symbol} insuffisant (${formatEther(balance)}).`);
    return false;
  }
  try {
    const tx = await network.treasuryWallet.sendTransaction({ to: depositAddress, value: topup });
    await tx.wait(1, TX_TIMEOUT_MS);
    await event(order.id, 'sweep_gas_topup', { network: network.key, amount: network.gasTopup, symbol: network.symbol, tx_hash: tx.hash });
    log('gas topup', order.reference, network.gasTopup, network.symbol, tx.hash);
    return true;
  } catch (e) {
    await release(order, 'gas_deferred', `Topup gas échoué: ${e.shortMessage || e.message}`);
    return false;
  }
}

async function processOrder(network, order) {
  let depositWallet;
  try {
    depositWallet = HDNodeWallet.fromPhrase(SEED, undefined, `m/44'/60'/0'/0/${order.deposit_index}`).connect(network.provider);
  } catch (e) {
    await manual(order, `Dérivation wallet dépôt impossible: ${e.message}`);
    return;
  }
  if (depositWallet.address.toLowerCase() !== String(order.deposit_address).toLowerCase()) {
    await manual(order, 'Adresse dérivée ≠ adresse de la commande.');
    return;
  }

  const balance = await network.tokenContractRead.balanceOf(order.deposit_address);
  if (balance <= 0n) {
    await manual(order, 'Aucun USDT sur l’adresse au moment du sweep.');
    return;
  }

  const gasBalance = await network.provider.getBalance(depositWallet.address);
  if (gasBalance < parseEther(network.gasMin)) {
    const ok = await sendGasTopup(network, order, depositWallet.address);
    if (!ok) return;
  }

  const token = new Contract(network.token, [
    'function decimals() view returns (uint8)',
    'function transfer(address to, uint256 amount) returns (bool)',
  ], depositWallet);
  let tx;
  try {
    tx = await token.transfer(treasuryAddress, balance);
  } catch (e) {
    await release(order, 'pending', `Envoi USDT échoué: ${e.shortMessage || e.message}`);
    return;
  }

  try {
    await pool.query(
      `UPDATE onramp_orders SET sweep_tx_hash = ?, sweep_amount = ?, updated_at = NOW()
        WHERE id = ? AND sweep_status = 'sweeping'`,
      [tx.hash.toLowerCase(), formatUnits(balance, network.decimals), order.id],
    );
  } catch (e) {
    log('store sweep hash failed', order.reference, tx.hash, e.message);
  }
  log('sweep broadcast', order.reference, tx.hash);

  try {
    const receipt = await tx.wait(1, TX_TIMEOUT_MS);
    if (receipt && Number(receipt.status) === 1) {
      if (await finalize(order, tx.hash, formatUnits(balance, network.decimals))) {
        log('swept', order.reference, tx.hash);
        await notifyAdmins(`✅ USDT rapatrié\nCommande: ${order.reference}\nMontant: ${formatUnits(balance, network.decimals)} USDT\nTreasury: ${treasuryAddress}\nTx: ${tx.hash}`);
      }
    } else {
      await manual(order, 'USDT transfer sans statut success.', tx.hash);
    }
  } catch (e) {
    await manual(order, `Attente receipt sweep échouée/timeout: ${e.shortMessage || e.message}`, tx.hash);
  }
}

async function initNetwork(network) {
  try {
    network.decimals = Number(await network.tokenContractRead.decimals());
    const gas = await network.provider.getBalance(treasuryAddress);
    log('sweeper network ready', {
      key: network.key, treasury: treasuryAddress, token: network.token,
      decimals: network.decimals, gas: `${formatEther(gas)} ${network.symbol}`,
    });
  } catch (e) {
    log('sweeper network init error', network.key, e.shortMessage || e.message);
  }
}

async function main() {
  log('sweeper starting', { treasuryIndex: TREASURY_INDEX, treasury: treasuryAddress });
  for (const network of NETWORKS) await initNetwork(network);
  while (true) {
    try {
      await recoverInFlight();
      const orders = await loadSweeps();
      for (const order of orders) {
        const network = NETWORKS.find((n) => n.key === order.network);
        if (!network) continue;
        if (await claim(order)) await processOrder(network, order);
      }
    } catch (e) {
      log('sweep cycle error', e.message);
    }
    await sleep(POLL_MS);
  }
}

process.on('SIGTERM', async () => { log('SIGTERM'); try { await pool.end(); } catch {} process.exit(0); });
process.on('SIGINT', async () => { log('SIGINT'); try { await pool.end(); } catch {} process.exit(0); });

main().catch((e) => { log('fatal', e.message); process.exit(1); });
