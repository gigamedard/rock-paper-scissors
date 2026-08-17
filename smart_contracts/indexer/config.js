// ============================================================
// Configuration de l'indexeur blockchain (APP1 - bridge)
// Paramètres d'indexation (polling, reorg safety, backoff).
// Les identifiants DB sont lus depuis l'environnement du bridge
// (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD).
// ============================================================

function intEnv(name, fallback) {
  const raw = process.env[name];
  if (raw === undefined || raw === '') return fallback;
  const n = Number.parseInt(raw, 10);
  if (Number.isNaN(n)) {
    throw new Error(`[Indexer] ${name} doit être un entier, reçu: "${raw}"`);
  }
  return n;
}

export const indexerConfig = {
  // --- Sécurité reorg ---
  // 0 pour un nœud Hardhat local (automine, pas de reorg). En production
  // (Avalanche Fuji/mainnet), monter à 5-12.
  confirmationsRequired: intEnv('CONFIRMATIONS_REQUIRED', 0),

  // --- Polling ---
  pollIntervalMs: intEnv('POLL_INTERVAL_MS', 5000),
  maxBlockRange: intEnv('MAX_BLOCK_RANGE', 1000),

  // --- Bloc de déploiement (point de départ si aucun état persisté) ---
  deploymentBlock: intEnv('DEPLOYMENT_BLOCK', 0),

  // --- Backoff exponentiel (erreurs RPC) ---
  backoffBaseMs: intEnv('BACKOFF_BASE_MS', 2000),
  backoffMaxMs: intEnv('BACKOFF_MAX_MS', 30000),

  // --- MySQL (persistance d'état) ---
  db: {
    host: process.env.DB_HOST || 'db',
    port: intEnv('DB_PORT', 3306),
    user: process.env.DB_USERNAME || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_DATABASE || 'rock_paper_scissors',
  },
};
