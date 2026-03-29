require("@nomicfoundation/hardhat-toolbox");
require("dotenv").config({ path: "../smart_contracts/.env" });

// ⚠️ IMPORTANT : Remplacez ceci par votre clé privée
// Copiez la valeur de GAME_WALLET_PK depuis votre fichier config.js
const FUJI_PRIVATE_KEY = process.env.GAME_WALLET_PK;

// Copiez l'URL RPC depuis votre fichier config.js
const FUJI_RPC_URL = process.env.FUJI_RPC_URL || "https://api.avax-test.network/ext/bc/C/rpc";

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
      chainId: 1337,
      accounts: {
        count: 101, // Change this number to get more accounts
      },
      mining: {
        auto: true,
        interval: 2000 // Mine un bloc toutes les 2 secondes
      },
    },
    // === BLOC AJOUTÉ ===
    fuji: {
      url: FUJI_RPC_URL,
      accounts: FUJI_PRIVATE_KEY ? [FUJI_PRIVATE_KEY] : [],
      chainId: 43113
    }
    // ===================
  },
};


