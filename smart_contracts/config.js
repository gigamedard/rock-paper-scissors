// ===================================
// == CONFIGURATION PRINCIPALE
// ===================================
import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

// Charger les variables d'environnement
dotenv.config({ path: join(__dirname, '.env') });

// ===================================
// == CONFIGURATION PRINCIPALE
// ===================================
export const LARAVEL_API_URL = "http://127.0.0.1:8081/api"; // Force local Docker access
export const INTERNAL_API_SECRET = process.env.INTERNAL_API_SECRET;
export const BACKEND_URL = "https://srv1198092.hstgr.cloud"; // Force correct domain

// VALIDATION - Arrête l'application si les secrets ne sont pas définis
if (!INTERNAL_API_SECRET) {
  console.error('❌ INTERNAL_API_SECRET must be defined in .env file');
  // We don't throw here to allow build/test without .env if mocked, but it's critical for runtime
}

export const SECURITY_COEFFICIENT = 100/*1000*/;

// --- URLs des Réseaux ---
export const LOCAL_HARDHAT_URL = "http://127.0.0.1:8545";
export const FUJI_RPC_URL = process.env.FUJI_RPC_URL || "https://api.avax-test.network/ext/bc/C/rpc";

// --- Port du Serveur Node ---
export const NODE_SERVER_PORT = process.env.NODE_SERVER_PORT || 3000;

// ===================================
// == PORTEFEUILLES (WALLETS)
// ===================================
// (Tu dois remplacer ces clés par les tiennes)
export const GAME_WALLET_PK = process.env.GAME_WALLET_PK;
export const MARKETPLACE_WALLET_PK = process.env.MARKETPLACE_WALLET_PK;

// VALIDATION
if (!GAME_WALLET_PK || !MARKETPLACE_WALLET_PK) {
  console.error('❌ Wallet private keys must be defined in .env file');
}

// ===================================
// == CONTRATS (Harmonisation)
// ===================================
export const contracts = {
  // --- Contrat du JEU (de listener3.js) ---
  game: {
    address: "0x21C29794150Eb7801D84860c2B124274F902514B",// oldaddress: "0x95A99C0673a02c458c45B6dD0Cb146a80318805b", // L'adresse que tu avais
    abi: [
      {
        "inputs": [],
        "stateMutability": "nonpayable",
        "type": "constructor"
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
        "name": "deposit",
        "outputs": [],
        "stateMutability": "payable",
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
            "name": "poolId",
            "type": "uint256"
          },
          {
            "internalType": "address",
            "name": "user",
            "type": "address"
          }
        ],
        "name": "isUserInPool",
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
          }
        ],
        "name": "pools",
        "outputs": [
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
            "internalType": "uint256",
            "name": "maxSize",
            "type": "uint256"
          },
          {
            "internalType": "string",
            "name": "poolSalt",
            "type": "string"
          }
        ],
        "stateMutability": "view",
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
        "stateMutability": "payable",
        "type": "receive"
      }
    ]
  },
  // --- Contrat du MARKETPLACE (de server.js) ---
  marketplace: {
    address: "0xb0Fe23c18bCc490CDFe4E244e9F1c4e54A10cE6c", // L'adresse que tu avais
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
  // --- Contrat SNT Token (ERC20) ---
  snt: {
    address: "0x05A26c7f06127710463692263E12c1BF51A34184",
    abi: [
      { "constant": false, "inputs": [{ "name": "spender", "type": "address" }, { "name": "amount", "type": "uint256" }], "name": "approve", "outputs": [{ "name": "", "type": "bool" }], "payable": false, "stateMutability": "nonpayable", "type": "function" },
      { "constant": true, "inputs": [{ "name": "account", "type": "address" }], "name": "balanceOf", "outputs": [{ "name": "", "type": "uint256" }], "payable": false, "stateMutability": "view", "type": "function" },
      { "constant": true, "inputs": [{ "name": "owner", "type": "address" }, { "name": "spender", "type": "address" }], "name": "allowance", "outputs": [{ "name": "", "type": "uint256" }], "payable": false, "stateMutability": "view", "type": "function" }
    ]
  }
};

export const pinata = {
  PINATA_API_KEY: "***REMOVED***",
  PINATA_SECRET: "***REMOVED***",
  PINATA_API_URL: "https://api.pinata.cloud/pinning/pinJSONToIPFS"
};