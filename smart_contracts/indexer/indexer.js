// ============================================================
// Indexeur blockchain robuste & idempotent (APP1 - bridge)
//
// Remplace l'ancien startBlockchainListeners() (polling en mémoire,
// lastBlock reset à 0 au redémarrage, sans idempotence ni reorg safety)
// par un polling par plage de blocs avec :
//   - état de synchronisation persistant par contrat
//   - anti-doublons strict (tx_hash, log_index)
//   - sécurité reorg (CONFIRMATIONS_REQUIRED)
//   - exponential backoff sur erreur RPC (sans crash)
//   - catch-up automatique au redémarrage
// ============================================================
import { indexerConfig } from './config.js';
import { initSchema, closePool } from './db.js';
import { loadLastProcessedBlock, saveLastProcessedBlock } from './blockTracker.js';
import { processEvents } from './eventProcessor.js';
import { createWatchers } from './handlers.js';

/**
 * Démarre l'indexeur pour un ensemble de contrats.
 *
 * @param {object} opts
 * @param {import('ethers').JsonRpcProvider} opts.provider
 * @param {import('ethers').Contract} opts.gameContract
 * @param {import('ethers').Contract} opts.marketplaceContract
 * @param {import('ethers').Contract} opts.sntContract
 * @param {string} opts.marketplaceAddress
 * @param {(endpoint: string, body: object) => Promise<void>} opts.postToLaravel
 * @returns {Promise<void>} - ne résout jamais (boucle infinie) sauf arrêt.
 */
export async function startIndexer({ provider, gameContract, marketplaceContract, sntContract, marketplaceAddress, postToLaravel }) {
  await initSchema();

  const watchers = createWatchers({
    gameContract,
    marketplaceContract,
    sntContract,
    marketplaceAddress,
    postToLaravel,
  });

  // État en mémoire par contrat (chargé depuis la BDD).
  const state = new Map();
  for (const w of watchers) {
    const last = await loadLastProcessedBlock(w.contractAddress);
    state.set(w.key, last);
    console.log(`   [Indexer] ${w.key} (${w.contractAddress}) reprend au bloc ${last}`);
  }

  let backoffMs = indexerConfig.backoffBaseMs;
  let running = true;

  async function pollWatcher(w) {
    const lastProcessed = state.get(w.key);
    const latestBlock = await provider.getBlockNumber();
    const targetBlock = latestBlock - indexerConfig.confirmationsRequired;

    if (lastProcessed >= targetBlock) {
      return { synced: false, processed: 0, skipped: 0 };
    }

    const fromBlock = lastProcessed + 1;
    const toBlock = Math.min(fromBlock + indexerConfig.maxBlockRange - 1, targetBlock);

    const logs = await provider.getLogs({
      address: w.contractAddress,
      fromBlock,
      toBlock,
    });

    const events = [];
    for (const log of logs) {
      let parsed;
      try {
        parsed = w.contract.interface.parseLog(log);
      } catch (_) {
        continue;
      }
      if (!parsed) continue;

      const handler = w.handlers[parsed.name];
      if (!handler) continue; // événement sans handler → ignoré

      events.push({
        txHash: log.transactionHash,
        logIndex: log.index,
        eventName: parsed.name,
        blockNumber: log.blockNumber,
        args: parsed.args,
      });
    }

    const { processed, skipped } = await processEvents(events, async (ev) => {
      await w.handlers[ev.eventName](ev.args);
    });

    await saveLastProcessedBlock(w.contractAddress, toBlock);
    state.set(w.key, toBlock);

    if (processed > 0 || skipped > 0) {
      console.log(
        `[Indexer] ${w.key} synced blocks ${fromBlock} -> ${toBlock} | Events processed: ${processed} | Skipped: ${skipped}`
      );
    }

    return { synced: true, processed, skipped };
  }

  async function run() {
    while (running) {
      try {
        let anySynced = false;
        for (const w of watchers) {
          const { synced } = await pollWatcher(w);
          if (synced) anySynced = true;
        }
        backoffMs = indexerConfig.backoffBaseMs;

        if (!anySynced) {
          await sleep(indexerConfig.pollIntervalMs);
        }
        // Si au moins un watcher a synchronisé, on enchaîne immédiatement
        // pour rattraper le retard (catch-up rapide).
      } catch (err) {
        console.error(`[Indexer] Erreur de polling: ${err.message}`);
        console.error(`[Indexer] Backoff ${backoffMs}ms avant retry...`);
        await sleep(backoffMs);
        backoffMs = Math.min(backoffMs * 2, indexerConfig.backoffMaxMs);
      }
    }
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  // Arrêt propre.
  const stop = () => { running = false; };
  process.on('SIGINT', stop);
  process.on('SIGTERM', stop);

  await run();
}
