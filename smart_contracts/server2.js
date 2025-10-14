// =================================================================
// ==           VERSION FINALE ET CORRIGÉE DE server.js           ==
// =================================================================
import express from 'express';
import { JsonRpcProvider, Wallet, Contract, ethers, parseUnits } from 'ethers';
import { Avax_wallet_privateKey } from "./config.js";
import 'dotenv/config';

// --- CONFIGURATION DE BASE ---
const app = express();
app.use(express.json());
const port = 3000;


// --- 1. CONNEXION À LA BLOCKCHAIN (PROVIDER) ---
const fujiRpcUrl = "https://api.avax-test.network/ext/bc/C/rpc";
const provider = new JsonRpcProvider(fujiRpcUrl);


// --- 2. CONFIGURATION DU PORTEFEUILLE DU BACKEND (SIGNER) ---
const backendWalletPrivateKey = Avax_wallet_privateKey;
if (!backendWalletPrivateKey) {
    throw new Error("La variable BACKEND_WALLET_PRIVATE_KEY n'est pas définie dans le fichier .env");
}
const wallet = new Wallet(backendWalletPrivateKey, provider);


// --- 3. CONFIGURATION DES CONTRATS ---
// !!! IMPORTANT : REMPLACE LES ... PAR TES VRAIES DONNÉES !!!
const marketplaceAbi = [
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
];
const marketplaceAddress = "0xb0Fe23c18bCc490CDFe4E244e9F1c4e54A10cE6c"; // <--- METS L'ADRESSE DE TON CONTRAT MARKETPLACEESCROW

// On crée une instance du contrat, connectée à notre portefeuille pour pouvoir envoyer des transactions
const marketplaceContract = new Contract(marketplaceAddress, marketplaceAbi, wallet);

console.log(`✅ Serveur prêt. Connecté à Fuji via le portefeuille : ${wallet.address}`);


// =================================================================
// ==                       ROUTES DE L'API                       ==
// =================================================================

app.post("/create-offer", async (req, res) => {
    try {
        const { sellerAddress, sntAmount, avaxAmount, durationHours } = req.body;
        if (!sellerAddress || !sntAmount || !avaxAmount || !durationHours) {
            return res.status(400).json({ error: "Paramètres manquants." });
        }

        console.log(`📡 Reçu une demande de création d'offre de Laravel pour ${sellerAddress}`);

        // Conversion des montants en unités blockchain (wei, 18 décimales)
        const sntAmountWei = parseUnits(sntAmount.toString(), 18);
        const avaxAmountWei = parseUnits(avaxAmount.toString(), 18);

        console.log("-> Appel de la fonction 'createOffer' du smart contract...");

        // Note: L'utilisateur DOIT avoir fait 'approve' côté frontend avant cet appel
        const tx = await marketplaceContract.createOffer(sntAmountWei, avaxAmountWei, durationHours);
        
        console.log(`Transaction envoyée. En attente de minage... Hash: ${tx.hash}`);
        await tx.wait(); // On attend la confirmation de la blockchain

        console.log(`✅ Offre créée avec succès !`);
        res.status(200).json({ success: true, txHash: tx.hash });

    } catch (error) {
        console.error("❌ Erreur lors de la création de l'offre:", error);
        res.status(500).json({ error: error.message || "Une erreur interne est survenue." });
    }
});


// --- DÉMARRAGE DU SERVEUR ---
app.listen(port, () => {
    console.log(`🚀 Serveur Node.js à l'écoute sur http://localhost:${port}`);
});