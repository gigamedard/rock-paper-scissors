// ===================================
// == CONFIGURATION PRINCIPALE
// ===================================
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import { readFileSync, writeFileSync, existsSync, unlinkSync } from 'fs';
import { execSync } from 'child_process';
import { scryptSync, createDecipheriv } from 'crypto';
import { tmpdir } from 'os';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Charger les variables d'environnement
// SECURITY: smart_contracts/.env was deleted — merged into root .env (single source of truth).
// In Docker, env vars are injected via `environment:` in docker-compose (dotenv is a no-op).
// On the host (local scripts), load from the root .env two levels up.
dotenv.config({ path: join(__dirname, '.env') });
dotenv.config({ path: join(__dirname, '..', '.env') });

/**
 * SECURITY: readSecret reads a secret value from multiple sources in order:
 * 1. Docker secret file: /run/secrets/<name> (inside containers)
 * 2. Host plaintext file: ../.secrets/<name> (provisioned for docker compose up)
 * 3. Host encrypted file: ../.secrets/<name>.enc (decrypted in memory)
 * 4. process.env variable (last-resort fallback)
 *
 * Sources 1-2 are plaintext (only available when provisioned or in Docker).
 * Source 3 is encrypted at rest (AES-256-GCM), decrypted in memory — no plaintext
 * on disk after deprovision. The passphrase is in Windows Credential Manager.
 *
 * @param {string} envVarName - The environment variable name (e.g. "GAME_WALLET_PK")
 * @param {string} secretName - The secret file name (defaults to envVarName lowercased)
 * @returns {string|undefined} The secret value or undefined
 */
function readSecret(envVarName, secretName) {
  const name = secretName || envVarName.toLowerCase();
  // 1. Docker secret (inside containers)
  const dockerPath = join('/run/secrets', name);
  if (existsSync(dockerPath)) {
    try { return readFileSync(dockerPath, 'utf8').trim(); } catch (e) { /* fall through */ }
  }
  // Host secret paths
  const hostPaths = [
    join(__dirname, '..', '.secrets', name),   // from smart_contracts/
    join(__dirname, '.secrets', name),          // from smart_contracts/ itself
  ];
  // 2. Plaintext file (provisioned for docker compose)
  for (const p of hostPaths) {
    if (existsSync(p)) {
      try { return readFileSync(p, 'utf8').trim(); } catch (e) { /* try next */ }
    }
  }
  // 3. Encrypted file (.enc) — decrypt in memory (passphrase from Credential Manager)
  for (const p of hostPaths) {
    const encPath = p + '.enc';
    if (existsSync(encPath)) {
      try {
        return decryptSecret(encPath);
      } catch (e) {
        console.error(`⚠️  Failed to decrypt secret ${name}:`, e.message);
        /* fall through to env */
      }
    }
  }
  // 4. Env var fallback (last resort — should be empty in .env)
  return process.env[envVarName];
}

/**
 * Decrypts an AES-256-GCM encrypted secret file using a passphrase from
 * Windows DPAPI. The key is derived via scrypt.
 * @param {string} encPath - Path to the .enc file
 * @returns {string} Decrypted plaintext
 */
function decryptSecret(encPath) {
  const passphraseEncPath = join(__dirname, '..', '.secrets', '.passphrase.enc');

  // Load passphrase from DPAPI-encrypted file
  let passphrase;
  try {
    const psScript = `
      $encrypted = Get-Content -Path '${passphraseEncPath.replace(/\\/g, '\\\\')}' -Raw
      $secure = ConvertTo-SecureString $encrypted
      $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
      $plain = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
      Write-Output $plain
    `;
    const tmpFile = join(tmpdir(), 'rps_load_pass_' + process.pid + '.ps1');
    writeFileSync(tmpFile, psScript);
    passphrase = execSync(`powershell -NoProfile -ExecutionPolicy Bypass -File "${tmpFile}"`, { encoding: 'utf8', stdio: 'pipe' }).trim();
    try { unlinkSync(tmpFile); } catch (e) { /* ignore */ }
  } catch (e) {
    throw new Error('No passphrase in DPAPI (run: node secrets-manager.mjs init)');
  }

  const encrypted = readFileSync(encPath);
  const SALT_LEN = 16, IV_LEN = 12, TAG_LEN = 16, KEY_LEN = 32;
  const salt = encrypted.subarray(0, SALT_LEN);
  const iv = encrypted.subarray(SALT_LEN, SALT_LEN + IV_LEN);
  const tag = encrypted.subarray(SALT_LEN + IV_LEN, SALT_LEN + IV_LEN + TAG_LEN);
  const ciphertext = encrypted.subarray(SALT_LEN + IV_LEN + TAG_LEN);
  const key = scryptSync(passphrase, salt, KEY_LEN, { N: 16384, r: 8, p: 1 });
  const decipher = createDecipheriv('aes-256-gcm', key, iv);
  decipher.setAuthTag(tag);
  const decrypted = Buffer.concat([decipher.update(ciphertext), decipher.final()]);
  return decrypted.toString('utf8').trim();
}

// ===================================
// == CONFIGURATION PRINCIPALE
// ===================================
export const LARAVEL_API_URL = process.env.LARAVEL_API_URL || "http://127.0.0.1:8000/api";
export const INTERNAL_API_SECRET = readSecret('INTERNAL_API_SECRET');
export const BACKEND_URL = process.env.BACKEND_URL || "http://127.0.0.1:8000";


// VALIDATION - Arrête l'application si les secrets ne sont pas définis
if (!INTERNAL_API_SECRET) {
  console.error('❌ INTERNAL_API_SECRET must be defined in .env file');
  // We don't throw here to allow build/test without .env if mocked, but it's critical for runtime
}

export const SECURITY_COEFFICIENT = 1000;

// --- URLs des Réseaux ---
export const LOCAL_HARDHAT_URL = process.env.FUJI_RPC_URL || "http://127.0.0.1:8545";
export const FUJI_RPC_URL = process.env.FUJI_RPC_URL || "http://127.0.0.1:8545";

// --- Port du Serveur Node ---
export const NODE_SERVER_PORT = process.env.NODE_SERVER_PORT || 3000;

// ===================================
// == PORTEFEUILLES (WALLETS)
// ===================================
// SECURITY: Wallet keys are read from Docker secrets (/run/secrets/) if available,
// falling back to env vars (for local dev). In Docker, keys are mounted as secrets
// and NOT passed via `environment:` so they don't appear in `docker inspect` or `env`.
export const GAME_WALLET_PK = readSecret('GAME_WALLET_PK');
export const MARKETPLACE_WALLET_PK = readSecret('MARKETPLACE_WALLET_PK');
// SECURITY: SIGNER_WALLET_PK is a SEPARATE key that signs claim signatures.
// It is the "hot" key (exposed to the bridge) and can be rotated via setSigner()
// without touching GAME_WALLET_PK (the "cold" owner/admin key).
// Falls back to GAME_WALLET_PK for backward compatibility during transition.
export const SIGNER_WALLET_PK = readSecret('SIGNER_WALLET_PK') || GAME_WALLET_PK;
// SECURITY: PAYOUT_OPERATOR_PK is the "hot" key the bridge uses to call
// payOut/batchPayOut. It is DISTINCT from the owner key (GAME_WALLET_PK),
// so a bridge compromise cannot access admin functions. Falls back to
// SIGNER_WALLET_PK for backward compatibility during transition.
export const PAYOUT_OPERATOR_PK = readSecret('PAYOUT_OPERATOR_PK') || SIGNER_WALLET_PK;

// VALIDATION
// SECURITY: GAME_WALLET_PK (owner/cold key) is NOT required in the bridge container
// (deliberately not mounted as a secret). Only MARKETPLACE_WALLET_PK and either
// PAYOUT_OPERATOR_PK or SIGNER_WALLET_PK are required for the bridge to function.
if (!MARKETPLACE_WALLET_PK) {
  console.error('❌ MARKETPLACE_WALLET_PK must be defined (env var or Docker secret)');
}
if (!PAYOUT_OPERATOR_PK) {
  console.error('❌ PAYOUT_OPERATOR_PK (or SIGNER_WALLET_PK) must be defined (env var or Docker secret)');
}
// GAME_WALLET_PK is only needed by admin scripts (fund_bots, unstuck_user, etc.)
// and by the blockchain container for deployment. The bridge does NOT need it.
if (!GAME_WALLET_PK) {
  console.warn('⚠️  GAME_WALLET_PK not set — admin scripts will not work (OK for bridge container)');
}

// ===================================
// == CONTRATS (Harmonisation)
// ===================================
export const contracts = {
  // --- Contrat du JEU (de listener3.js) ---
  game: {
    address: "0x5FbDB2315678afecb367f032d93F642f64180aa3",
    abi:  [
  {
    "inputs": [],
    "stateMutability": "nonpayable",
    "type": "constructor"
  },
  {
    "inputs": [],
    "name": "ECDSAInvalidSignature",
    "type": "error"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "length",
        "type": "uint256"
      }
    ],
    "name": "ECDSAInvalidSignatureLength",
    "type": "error"
  },
  {
    "inputs": [
      {
        "internalType": "bytes32",
        "name": "s",
        "type": "bytes32"
      }
    ],
    "name": "ECDSAInvalidSignatureS",
    "type": "error"
  },
  {
    "inputs": [],
    "name": "ReentrancyGuardReentrantCall",
    "type": "error"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "DefaultMaxBaseBetChanged",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "DefaultMaxQChanged",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "DefaultMinCooldownChanged",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newSize",
        "type": "uint256"
      }
    ],
    "name": "DefaultPoolMaxSizeChanged",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "DepositReceived",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "wallet",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "DevFeesWithdrawn",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "address",
        "name": "newWallet",
        "type": "address"
      }
    ],
    "name": "DevWalletUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newFee",
        "type": "uint256"
      }
    ],
    "name": "FeeBasisPointsUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "MatchHistoryCIDUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "nextTime",
        "type": "uint256"
      }
    ],
    "name": "NextSessionTimeUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "previousOwner",
        "type": "address"
      },
      {
        "indexed": true,
        "internalType": "address",
        "name": "newOwner",
        "type": "address"
      }
    ],
    "name": "OwnershipTransferred",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "address",
        "name": "newOperator",
        "type": "address"
      }
    ],
    "name": "PayoutOperatorUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "wallet",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "PayoutProcessed",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "wallet",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "nonce",
        "type": "uint256"
      }
    ],
    "name": "PlayerClaimed",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "maxSize",
        "type": "uint256"
      }
    ],
    "name": "PoolCreated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      },
      {
        "indexed": false,
        "internalType": "string[]",
        "name": "premoveCIDs",
        "type": "string[]"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "poolSalt",
        "type": "string"
      },
      {
        "indexed": false,
        "internalType": "uint256[]",
        "name": "balances",
        "type": "uint256[]"
      }
    ],
    "name": "PoolEmitted",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "refundedCount",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "timestamp",
        "type": "uint256"
      }
    ],
    "name": "PoolStagnantRefund",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "PremoveCIDUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newCoefficient",
        "type": "uint256"
      }
    ],
    "name": "SecurityCoefficientUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "address",
        "name": "newSigner",
        "type": "address"
      }
    ],
    "name": "SignerUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "StagnantBlockLimitUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "bytes32",
        "name": "opHash",
        "type": "bytes32"
      }
    ],
    "name": "TimelockCancelled",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "bytes32",
        "name": "opHash",
        "type": "bytes32"
      }
    ],
    "name": "TimelockExecuted",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "bytes32",
        "name": "opHash",
        "type": "bytes32"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "readyAt",
        "type": "uint256"
      }
    ],
    "name": "TimelockQueued",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "maxBaseBet",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "maxQ",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "minCooldown",
        "type": "uint256"
      },
      {
        "indexed": false,
        "internalType": "uint256",
        "name": "expiry",
        "type": "uint256"
      }
    ],
    "name": "UserLimitsUpdated",
    "type": "event"
  },
  {
    "anonymous": false,
    "inputs": [
      {
        "indexed": true,
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "indexed": false,
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "sessionHistoryCIDUpdated",
    "type": "event"
  },
  {
    "stateMutability": "payable",
    "type": "fallback"
  },
  {
    "inputs": [],
    "name": "MAX_FEE_BASIS_POINTS",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "TIMELOCK_DELAY",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "addSingleUserToPool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      }
    ],
    "name": "addUsersToPool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address[]",
        "name": "wallets",
        "type": "address[]"
      },
      {
        "internalType": "uint256[]",
        "name": "amounts",
        "type": "uint256[]"
      }
    ],
    "name": "batchPayOut",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "bytes32",
        "name": "opHash",
        "type": "bytes32"
      }
    ],
    "name": "cancelTimelock",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "checkAndRefundStagnantPool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "deadline",
        "type": "uint256"
      },
      {
        "internalType": "bytes",
        "name": "signature",
        "type": "bytes"
      }
    ],
    "name": "claimAndExit",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "defaultMaxBaseBet",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "defaultMaxQ",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "defaultMinCooldown",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "defaultPoolMaxSize",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "deployBlock",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "deposit",
    "outputs": [],
    "stateMutability": "payable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "devBalance",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "devWallet",
    "outputs": [
      {
        "internalType": "address payable",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address payable",
        "name": "newWallet",
        "type": "address"
      }
    ],
    "name": "executeSetDevWallet",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newOperator",
        "type": "address"
      }
    ],
    "name": "executeSetPayoutOperator",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newSigner",
        "type": "address"
      }
    ],
    "name": "executeSetSigner",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newOwner",
        "type": "address"
      }
    ],
    "name": "executeTransferOwnership",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "feeBasisPoints",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "getContractAddress",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "getContractBalance",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      }
    ],
    "name": "getMatchHistoryCID",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "getPoolInfo",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "maxSize",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "userCount",
        "type": "uint256"
      },
      {
        "internalType": "bool",
        "name": "isLocked",
        "type": "bool"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "getPoolQueueLength",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "getPoolUsers",
    "outputs": [
      {
        "internalType": "address[]",
        "name": "",
        "type": "address[]"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getPremoveCID",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getSessionHistoryCIDs",
    "outputs": [
      {
        "internalType": "string[]",
        "name": "",
        "type": "string[]"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getUserBalance",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getUserMaxBaseBet",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getUserMaxQ",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "getUserMinCooldown",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "_owner",
        "type": "address"
      },
      {
        "internalType": "address",
        "name": "_signer",
        "type": "address"
      },
      {
        "internalType": "address",
        "name": "_payoutOperator",
        "type": "address"
      },
      {
        "internalType": "address payable",
        "name": "_devWallet",
        "type": "address"
      }
    ],
    "name": "initializeRoles",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address[]",
        "name": "invalidUsers",
        "type": "address[]"
      }
    ],
    "name": "invalidatePoolUsers",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "isUserInAnyPool",
    "outputs": [
      {
        "internalType": "bool",
        "name": "",
        "type": "bool"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      }
    ],
    "name": "isUserInPoolByBaseBet",
    "outputs": [
      {
        "internalType": "bool",
        "name": "",
        "type": "bool"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "nextPoolId",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "nextSessionAllowedTime",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "nonces",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "owner",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address payable",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "uint256",
        "name": "amount",
        "type": "uint256"
      }
    ],
    "name": "payOut",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "payoutOperator",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "bytes32",
        "name": "",
        "type": "bytes32"
      }
    ],
    "name": "pendingTimelock",
    "outputs": [
      {
        "internalType": "bytes32",
        "name": "hash",
        "type": "bytes32"
      },
      {
        "internalType": "uint256",
        "name": "readyAt",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "poolHistoryCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "poolQueues",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address payable",
        "name": "newWallet",
        "type": "address"
      }
    ],
    "name": "queueSetDevWallet",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newOperator",
        "type": "address"
      }
    ],
    "name": "queueSetPayoutOperator",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newSigner",
        "type": "address"
      }
    ],
    "name": "queueSetSigner",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "newOwner",
        "type": "address"
      }
    ],
    "name": "queueTransferOwnership",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "securityCoefficient",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      },
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "name": "sessionHistoryCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "setDefaultMaxBaseBet",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "setDefaultMaxQ",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newLimit",
        "type": "uint256"
      }
    ],
    "name": "setDefaultMinCooldown",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newSize",
        "type": "uint256"
      }
    ],
    "name": "setDefaultPoolMaxSize",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newFee",
        "type": "uint256"
      }
    ],
    "name": "setFeeBasisPoints",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "newMaxSize",
        "type": "uint256"
      }
    ],
    "name": "setPoolMaxSize",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "newCoefficient",
        "type": "uint256"
      }
    ],
    "name": "setSecurityCoefficient",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "_limit",
        "type": "uint256"
      }
    ],
    "name": "setStagnantBlockLimit",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "uint256",
        "name": "maxBaseBet",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "maxQ",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "minCooldown",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "expiry",
        "type": "uint256"
      }
    ],
    "name": "setUserLimits",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "uint256",
        "name": "nextTime",
        "type": "uint256"
      }
    ],
    "name": "setUserNextSessionTime",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "signer",
    "outputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "stagnantBlockLimit",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "storeMatchHistoryCID",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "user",
        "type": "address"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "storeSessionCID",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "string",
        "name": "cid",
        "type": "string"
      }
    ],
    "name": "submitPremoveCID",
    "outputs": [],
    "stateMutability": "payable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "poolId",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      },
      {
        "internalType": "address[]",
        "name": "users",
        "type": "address[]"
      },
      {
        "internalType": "string[]",
        "name": "premoveCIDs",
        "type": "string[]"
      },
      {
        "internalType": "string",
        "name": "poolSalt",
        "type": "string"
      },
      {
        "internalType": "uint256[]",
        "name": "balances",
        "type": "uint256[]"
      }
    ],
    "name": "triggerPoolEmittedEventForTesting",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "userBalances",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "userLimits",
    "outputs": [
      {
        "internalType": "uint256",
        "name": "maxBaseBet",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "maxQ",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "minCooldown",
        "type": "uint256"
      },
      {
        "internalType": "uint256",
        "name": "expiry",
        "type": "uint256"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "address",
        "name": "",
        "type": "address"
      }
    ],
    "name": "userPremoveCIDs",
    "outputs": [
      {
        "internalType": "string",
        "name": "",
        "type": "string"
      }
    ],
    "stateMutability": "view",
    "type": "function"
  },
  {
    "inputs": [
      {
        "internalType": "uint256",
        "name": "baseBet",
        "type": "uint256"
      }
    ],
    "name": "validatePool",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "inputs": [],
    "name": "withdrawDevFees",
    "outputs": [],
    "stateMutability": "nonpayable",
    "type": "function"
  },
  {
    "stateMutability": "payable",
    "type": "receive"
  }
]
  },
  // --- Contrat MARKETPLACE (de app.js/server.js) ---
  marketplace: {
    address: "0x8A791620dd6260079BF849Dc5567aDC3F2FdC318", // MarketplaceEscrow
    abi: [
      {
        "inputs": [
          {
            "internalType": "address",
            "name": "_tokenAddress",
            "type": "address"
          },
          {
            "internalType": "address",
            "name": "_initialOwner",
            "type": "address"
          }
        ],
        "stateMutability": "nonpayable",
        "type": "constructor"
      },
      {
        "inputs": [],
        "name": "EnforcedPause",
        "type": "error"
      },
      {
        "inputs": [],
        "name": "ExpectedPause",
        "type": "error"
      },
      {
        "inputs": [
          {
            "internalType": "address",
            "name": "owner",
            "type": "address"
          }
        ],
        "name": "OwnableInvalidOwner",
        "type": "error"
      },
      {
        "inputs": [
          {
            "internalType": "address",
            "name": "account",
            "type": "address"
          }
        ],
        "name": "OwnableUnauthorizedAccount",
        "type": "error"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": true,
            "internalType": "uint256",
            "name": "offerId",
            "type": "uint256"
          }
        ],
        "name": "OfferCancelled",
        "type": "event"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": true,
            "internalType": "uint256",
            "name": "offerId",
            "type": "uint256"
          },
          {
            "indexed": true,
            "internalType": "address",
            "name": "seller",
            "type": "address"
          },
          {
            "indexed": false,
            "internalType": "uint256",
            "name": "sntAmount",
            "type": "uint256"
          },
          {
            "indexed": false,
            "internalType": "uint256",
            "name": "avaxAmount",
            "type": "uint256"
          }
        ],
        "name": "OfferCreated",
        "type": "event"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": true,
            "internalType": "uint256",
            "name": "offerId",
            "type": "uint256"
          },
          {
            "indexed": true,
            "internalType": "address",
            "name": "buyer",
            "type": "address"
          }
        ],
        "name": "OfferFulfilled",
        "type": "event"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": true,
            "internalType": "address",
            "name": "previousOwner",
            "type": "address"
          },
          {
            "indexed": true,
            "internalType": "address",
            "name": "newOwner",
            "type": "address"
          }
        ],
        "name": "OwnershipTransferred",
        "type": "event"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": false,
            "internalType": "address",
            "name": "account",
            "type": "address"
          }
        ],
        "name": "Paused",
        "type": "event"
      },
      {
        "anonymous": false,
        "inputs": [
          {
            "indexed": false,
            "internalType": "address",
            "name": "account",
            "type": "address"
          }
        ],
        "name": "Unpaused",
        "type": "event"
      },
      {
        "inputs": [
          {
            "internalType": "uint256",
            "name": "_offerId",
            "type": "uint256"
          }
        ],
        "name": "cancelOffer",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [
          {
            "internalType": "uint256",
            "name": "_sntAmount",
            "type": "uint256"
          },
          {
            "internalType": "uint256",
            "name": "_avaxAmount",
            "type": "uint256"
          },
          {
            "internalType": "uint256",
            "name": "_durationHours",
            "type": "uint256"
          }
        ],
        "name": "createOffer",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "feePercentage",
        "outputs": [
          {
            "internalType": "uint256",
            "name": "",
            "type": "uint256"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [
          {
            "internalType": "uint256",
            "name": "_offerId",
            "type": "uint256"
          }
        ],
        "name": "fulfillOffer",
        "outputs": [],
        "stateMutability": "payable",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "nextOfferId",
        "outputs": [
          {
            "internalType": "uint256",
            "name": "",
            "type": "uint256"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [
          {
            "internalType": "uint256",
            "name": "",
            "type": "uint256"
          }
        ],
        "name": "offers",
        "outputs": [
          {
            "internalType": "uint256",
            "name": "id",
            "type": "uint256"
          },
          {
            "internalType": "address payable",
            "name": "seller",
            "type": "address"
          },
          {
            "internalType": "uint256",
            "name": "sntAmount",
            "type": "uint256"
          },
          {
            "internalType": "uint256",
            "name": "avaxAmount",
            "type": "uint256"
          },
          {
            "internalType": "uint256",
            "name": "expiresAt",
            "type": "uint256"
          },
          {
            "internalType": "enum MarketplaceEscrow.Status",
            "name": "status",
            "type": "uint8"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "owner",
        "outputs": [
          {
            "internalType": "address",
            "name": "",
            "type": "address"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "pause",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "paused",
        "outputs": [
          {
            "internalType": "bool",
            "name": "",
            "type": "bool"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "renounceOwnership",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [
          {
            "internalType": "uint256",
            "name": "_newFee",
            "type": "uint256"
          }
        ],
        "name": "setFeePercentage",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "sntToken",
        "outputs": [
          {
            "internalType": "contract IERC20",
            "name": "",
            "type": "address"
          }
        ],
        "stateMutability": "view",
        "type": "function"
      },
      {
        "inputs": [
          {
            "internalType": "address",
            "name": "newOwner",
            "type": "address"
          }
        ],
        "name": "transferOwnership",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      },
      {
        "inputs": [],
        "name": "unpause",
        "outputs": [],
        "stateMutability": "nonpayable",
        "type": "function"
      }
    ]
  },
  // --- Contrat du JETON (SNT / USDT) ---
  snt: {
    address: "0x2279B7A0a67DB372996a5FaB50D91eAA73d2eBe6", // SNTToken
    abi: [
      { "constant": false, "inputs": [{ "name": "spender", "type": "address" }, { "name": "amount", "type": "uint256" }], "name": "approve", "outputs": [{ "name": "", "type": "bool" }], "payable": false, "stateMutability": "nonpayable", "type": "function" },
      { "constant": true, "inputs": [{ "name": "account", "type": "address" }], "name": "balanceOf", "outputs": [{ "name": "", "type": "uint256" }], "payable": false, "stateMutability": "view", "type": "function" },
      { "constant": true, "inputs": [{ "name": "owner", "type": "address" }, { "name": "spender", "type": "address" }], "name": "allowance", "outputs": [{ "name": "", "type": "uint256" }], "payable": false, "stateMutability": "view", "type": "function" },
      {
        "anonymous": false,
        "inputs": [
          { "indexed": true, "name": "from", "type": "address" },
          { "indexed": true, "name": "to", "type": "address" },
          { "indexed": false, "name": "value", "type": "uint256" }
        ],
        "name": "Transfer",
        "type": "event"
      }
    ]
  }
};

export const pinata = {
  PINATA_API_KEY: process.env.PINATA_API_KEY,
  PINATA_SECRET: process.env.PINATA_SECRET,
  PINATA_API_URL: "https://api.pinata.cloud/pinning/pinJSONToIPFS"
};
