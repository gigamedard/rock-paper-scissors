// =================================================================
// ==              LISTENER D'ÉVÉNEMENTS BLOCKCHAIN               ==
// =================================================================
import { WebSocketProvider, Contract, formatUnits, JsonRpcProvider } from 'ethers';
// import { marketplaceAddress, internalApiSecret } from "./_config.js";
import 'dotenv/config';

const marketplaceAddress = "0xb0Fe23c18bCc490CDFe4E244e9F1c4e54A10cE6c";
const internalApiSecret = "Dyx4n8qdBq+J5ulNBkUlxJj7byjoUKOEajsdxGNzAA8=";
// Force localhost for API
process.env.LARAVEL_API_URL = "http://127.0.0.1:8000/api";

// --- CONFIGURATION ---
const fujiWebSocketRpcUrl = "wss://api.avax-test.network/ext/bc/C/ws"; // URL WebSocket

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
const laravelApiUrl = process.env.LARAVEL_API_URL || 'http://72.60.211.162/api';


if (!marketplaceAddress || !laravelApiUrl || !internalApiSecret) {
	throw new Error("Variables d'environnement manquantes (MARKETPLACE_ADDRESS, etc.)");
}

async function main() {
	console.log("📡 Démarrage du listener d'événements...");
	const provider = new WebSocketProvider(fujiWebSocketRpcUrl);
	const contract = new Contract(marketplaceAddress, marketplaceAbi, provider);
	let lastBlock = await new JsonRpcProvider("https://api.avax-test.network/ext/bc/C/rpc").getBlockNumber();
	lastBlock = lastBlock - 50000; // REPLAY LAST 50000 BLOCKS
	console.log(`   Current Block: ${lastBlock + 50000}, Replaying from: ${lastBlock}`);
	console.log(`👂 Écoute des événements sur le contrat Marketplace à l'adresse : ${marketplaceAddress}`);

	// --- Écouteur pour l'événement "OfferCreated" ---
	contract.on("OfferCreated", (offerId, seller, sntAmount, avaxAmount, event) => {
		console.log("✅ Événement 'OfferCreated' détecté !");

		// On récupère le timestamp du bloc pour l'expiration
		event.getBlock().then(block => {
			const expiresAt = block.timestamp + (24 * 60 * 60); // Suppose 24h, à ajuster si la durée est dans l'event

			const offerData = {
				offerId: offerId.toString(),
				seller: seller,
				sntAmount: formatUnits(sntAmount, 18), // Conversion de Wei en unité lisible
				avaxAmount: formatUnits(avaxAmount, 18),
				expiresAt: expiresAt
			};

			console.log("📦 Préparation de l'envoi des données à Laravel:", offerData);

			// Envoi des données à l'API interne de Laravel
			fetch(`${laravelApiUrl}/internal/trades/create`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'Accept': 'application/json',
					'X-Internal-Secret': internalApiSecret // En-tête de sécurité
				},
				body: JSON.stringify(offerData)
			})
				.then(res => {
					if (!res.ok) {
						console.error(`❌ Erreur de Laravel : ${res.statusText}`);
						res.text().then(text => console.error(text));
					} else {
						console.log("🚀 Données de l'offre envoyées à Laravel avec succès !");
					}
				})
				.catch(err => console.error("❌ Erreur de connexion à Laravel:", err));
		});
	});

	contract.on("OfferFulfilled", (offerId, buyer, event) => {
		console.log(`✅ Événement 'OfferFulfilled' détecté pour l'offre #${offerId.toString()} par ${buyer}`);

		// --- Action 1 : Mettre à jour le statut du trade (comme avant) ---
		const updateData = {
			offerId: offerId.toString(),
			newStatus: 'fulfilled',
			buyerAddress: buyer
		};

		console.log("📦 Envoi du BODY à /update-status:", JSON.stringify(updateData));

		fetch(`${laravelApiUrl}/internal/trades/update-status`, {
			method: 'POST',
			headers: { /* ... en-têtes ... */ 'X-Internal-Secret': internalApiSecret },
			body: JSON.stringify(updateData)
		}).then(res => {
			if (res.ok) console.log(`🚀 Statut de l'offre #${offerId.toString()} mis à jour dans Laravel.`);
			else console.error(`❌ Erreur de Laravel lors de la mise à jour : ${res.statusText}`);
		}).catch(err => console.error("❌ Erreur de connexion à Laravel (update-status):", err));

		// --- NOUVELLE Action 2 : Déclencher la vérification du parrainage ---
		const referralCheckData = {
			buyer_address: buyer
		};

		console.log("📦 Envoi du BODY à /trigger-referral-check:", JSON.stringify(referralCheckData));


		fetch(`${laravelApiUrl}/internal/trades/trigger-referral-check`, { // On appelle la nouvelle route
			method: 'POST',
			headers: { /* ... en-têtes ... */ 'X-Internal-Secret': internalApiSecret },
			body: JSON.stringify(referralCheckData)
		}).then(res => {
			if (res.ok) console.log(`🕵️ Demande de vérification de parrainage envoyée pour ${buyer}`);
			else console.error(`❌ Erreur de Laravel lors du trigger : ${res.statusText}`);
		}).catch(err => console.error("❌ Erreur de connexion à Laravel (trigger-referral):", err));
	});


	contract.on("OfferCancelled", (offerId, event) => {
		console.log(`🟡 Événement 'OfferCancelled' détecté pour l'offre #${offerId.toString()}`);

		const updateData = {
			offerId: offerId.toString(),
			newStatus: 'cancelled'
			// Pas besoin de 'buyerAddress' ici
		};

		fetch(`${laravelApiUrl}/internal/trades/update-status`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'Accept': 'application/json',
				'X-Internal-Secret': internalApiSecret
			},
			body: JSON.stringify(updateData)
		})
			.then(res => {
				if (!res.ok) {
					console.error(`❌ Erreur de Laravel lors de la mise à jour (annulation) : ${res.statusText}`);
				} else {
					console.log(`🚀 Statut de l'offre #${offerId.toString()} mis à jour sur 'cancelled' dans Laravel.`);
				}
			})
			.catch(err => console.error("❌ Erreur de connexion à Laravel:", err));
	});


}

main().catch(error => {
	console.error("Le listener a rencontré une erreur fatale:", error);
	process.exit(1);
});