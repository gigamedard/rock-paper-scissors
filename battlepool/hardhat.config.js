require("@nomicfoundation/hardhat-toolbox");
// PHASE B (déploiement Pingala réel) : battlepool/.env est le fichier gitignored
// dédié aux secrets du déploiement L1 (PINGALA_DEPLOYER_PK, PINGALA_SIGNER_PK,
// PINGALA_PAYOUT_PK, PINGALA_DEV_PK + PINGALA_RPC_URL). Chargé EN PREMIER :
// dotenv n'écrase jamais une variable déjà définie, le premier fichier chargé
// gagne donc. Ce fichier est ignoré par git (battlepool/.gitignore → .env) :
// la clé n'est jamais versionnée.
require("dotenv").config({ path: __dirname + "/.env" });
// MIGRATION PINGALA : smart_contracts/.env n'existe plus (fusionné dans le .env
// racine, source de vérité unique). On charge ensuite le .env racine pour que
// les variables PINGALA_* y soient lisibles aussi. L'appel smart_contracts/.env
// est conservé temporairement (no-op si le fichier est absent) le temps que le
// compose de la phase F soit découpé.
require("dotenv").config({ path: "../smart_contracts/.env" });
require("dotenv").config({ path: "../.env" });

// ⚠️ IMPORTANT : Remplacez ceci par votre clé privée
// Copiez la valeur de GAME_WALLET_PK depuis votre fichier config.js
const FUJI_PRIVATE_KEY = process.env.GAME_WALLET_PK;

// Copiez l'URL RPC depuis votre fichier config.js
const FUJI_RPC_URL = process.env.FUJI_RPC_URL || "https://api.avax-test.network/ext/bc/C/rpc";

// MIGRATION PINGALA : clé du deployer de la L1 souveraine (JAMAMAIS en dur).
// Accepte aussi un secret Docker /run/secrets/pingala_deployer_pk si monté.
function readSecretFile(envVarName) {
  const fs = require("fs");
  const secretPath = "/run/secrets/" + envVarName.toLowerCase();
  if (fs.existsSync(secretPath)) {
    try { return fs.readFileSync(secretPath, "utf8").trim(); } catch (_) { /* fallback env */ }
  }
  return undefined;
}
const PINGALA_DEPLOYER_PK = process.env.PINGALA_DEPLOYER_PK || readSecretFile("PINGALA_DEPLOYER_PK");
const PINGALA_RPC_URL = process.env.PINGALA_RPC_URL;

/** @type import('hardhat/config').HardhatUserConfig */
module.exports = {
  solidity: {
    version: "0.8.28",
    settings: {
      optimizer: {
        enabled: true,
        runs: 200
      }
    }
  },
  networks: {
    hardhat: {
      // Aligné sur 31337 (standard Hardhat) pour un réseau MetaMask unique
      // compatible avec les deux apps du portail (App 2 = 31337).
      // NOTE MIGRATION : ce network reste utilisé pour les TESTS unitaires
      // (npx hardhat test, réseau in-process). Le DÉPLOIEMENT va sur `pingala`.
      chainId: 31337,
      accounts: {
        // NOTE: uses the default Hardhat mnemonic ("test test ... junk").
        // This is a KNOWN residual risk for a LOCAL test node, but it is
        // mitigated by binding the RPC port to 127.0.0.1 (loopback only),
        // so the node is not reachable from outside the host.
        count: 101, // Change this number to get more accounts
      },
      mining: {
        auto: true,
        interval: 2000 // Mine un bloc toutes les 2 secondes
      },
    },
    // === PINGALA = CHAÎNE CIBLE (L1 Avalanche Subnet souveraine, subnet-evm v1.15.1) ===
    // chainId 99999, RPC public. Le Hardhat local (31337) est COUPÉ au runtime :
    // seuls les tests unitaires tournent encore sur le réseau in-process `hardhat`.
    // PINGALA_RPC_URL est fourni au déploiement (Phase B) ; jusqu'ici la valeur vide
    // rend le network non utilisable mais la config reste chargeable (tests OK).
    pingala: {
      url: PINGALA_RPC_URL || "",
      chainId: 99999,
      accounts: PINGALA_DEPLOYER_PK ? [PINGALA_DEPLOYER_PK] : [],
    },
    // === Networks legacy conservés (phase F découpera le compose ; la config
    // seule ne casse rien). Ne plus déployer dessus. ===
    fuji: {
      url: FUJI_RPC_URL,
      accounts: FUJI_PRIVATE_KEY ? [FUJI_PRIVATE_KEY] : [],
      chainId: 43113
    },
    mainnet: {
      url: process.env.MAINNET_RPC_URL || "https://api.avax.network/ext/bc/C/rpc",
      accounts: FUJI_PRIVATE_KEY ? [FUJI_PRIVATE_KEY] : [],
      chainId: 43114
    }
    // ===================
  },
};
