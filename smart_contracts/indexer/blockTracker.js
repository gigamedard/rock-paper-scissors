// ============================================================
// Block Tracker : état de synchronisation persistant par contrat.
// ============================================================
import { getPool } from './db.js';
import { indexerConfig } from './config.js';

/**
 * Charge last_processed_block pour un contrat donné.
 * Si absent, initialise à DEPLOYMENT_BLOCK - 1.
 * @param {string} contractAddress
 * @returns {Promise<number>}
 */
export async function loadLastProcessedBlock(contractAddress) {
  const p = getPool();
  const [rows] = await p.query(
    'SELECT last_processed_block FROM blockchain_sync_states WHERE contract_address = ?',
    [contractAddress]
  );

  if (rows.length === 0) {
    const start = indexerConfig.deploymentBlock - 1;
    return start < 0 ? 0 : start;
  }

  return Number(rows[0].last_processed_block);
}

/**
 * Persiste last_processed_block (upsert).
 * @param {string} contractAddress
 * @param {number} block
 */
export async function saveLastProcessedBlock(contractAddress, block) {
  const p = getPool();
  await p.query(
    `INSERT INTO blockchain_sync_states (contract_address, last_processed_block)
     VALUES (?, ?)
     ON DUPLICATE KEY UPDATE last_processed_block = VALUES(last_processed_block)`,
    [contractAddress, block]
  );
}
