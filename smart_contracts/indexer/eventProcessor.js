// ============================================================
// Event Processor : idempotence & anti-doublons strict.
// Stratégie "claim-then-process" (crash-safe), identique à APP2.
// ============================================================
import { getPool } from './db.js';

/**
 * Traite un lot d'événements de façon atomique et idempotente.
 *
 * @param {Array<{txHash: string, logIndex: number, eventName: string, blockNumber: number, args: any}>} events
 * @param {(event: any) => Promise<void>} handler
 * @returns {Promise<{processed: number, skipped: number}>}
 */
export async function processEvents(events, handler) {
  const p = getPool();
  let processed = 0;
  let skipped = 0;

  for (const ev of events) {
    const conn = await p.getConnection();
    try {
      await conn.beginTransaction();

      let claimed = false;
      try {
        await conn.query(
          `INSERT INTO processed_blockchain_events
             (tx_hash, log_index, event_name, block_number, status)
           VALUES (?, ?, ?, ?, 'pending')`,
          [ev.txHash, ev.logIndex, ev.eventName, ev.blockNumber]
        );
        claimed = true;
      } catch (err) {
        if (err && err.code === 'ER_DUP_ENTRY') {
          claimed = false;
        } else {
          throw err;
        }
      }

      if (!claimed) {
        await conn.rollback();
        skipped++;
        continue;
      }

      await handler(ev);

      await conn.query(
        `UPDATE processed_blockchain_events
         SET status = 'processed'
         WHERE tx_hash = ? AND log_index = ?`,
        [ev.txHash, ev.logIndex]
      );

      await conn.commit();
      processed++;
    } catch (err) {
      try { await conn.rollback(); } catch (_) { /* ignore */ }
      throw err;
    } finally {
      conn.release();
    }
  }

  return { processed, skipped };
}
