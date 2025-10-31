// app.js (Le nouveau script qui remplace server.js ET listener3.js)

import express from "express";
import { JsonRpcProvider, Wallet, Contract } from "ethers";
import {
    LARAVEL_API_URL,
    INTERNAL_API_SECRET,
    BACKEND_URL,
    LOCAL_HARDHAT_URL,
    FUJI_RPC_URL,
    NODE_SERVER_PORT,
    GAME_WALLET_PK,
    MARKETPLACE_WALLET_PK,
    SECURITY_COEFFICIENT,
    pinata,
    contracts
} from "./config.js";

// ===================================
// == INITIALISATION
// ===================================

const app = express();
app.use(express.json());

// --- Connexion au Jeu (Hardhat) ---
const gameProvider = new JsonRpcProvider(FUJI_RPC_URL);
const gameWallet = new Wallet(GAME_WALLET_PK, gameProvider);
const gameContract = new Contract(contracts.game.address, contracts.game.abi, gameWallet);

// --- Connexion au Marketplace (Fuji) ---
const marketplaceProvider = new JsonRpcProvider(FUJI_RPC_URL);
const marketplaceWallet = new Wallet(MARKETPLACE_WALLET_PK, marketplaceProvider);
const marketplaceContract = new Contract(contracts.marketplace.address, contracts.marketplace.abi, marketplaceWallet);

// ===================================
// == API SERVER (Logique de server.js)
// ===================================
// (Ici on met toutes les routes POST de ton ancien server.js)

app.post("/sendPoolCID", async (req, res) => {
    // ... ta logique de /sendPoolCID
    // const tx = await gameContract.storeMatchHistoryCID(poolId, CID);
    // ...
});

app.post("/sendPayment", async (req, res) => {
    // ... ta logique de /sendPayment
    // const tx = await gameContract.payOut(wallet, amount);
    // ...
});

app.post("/sendBatchPayment", async (req, res) => {
    // ... ta logique de /sendBatchPayment
    // const tx = await gameContract.batchPayOut(wallets, amounts);
    // ...
});

app.post("/create-offer", async (req, res) => {
    // ... ta logique de /create-offer
    // const tx = await marketplaceContract.createOffer(...);
    // ...
});




/**
 * NOUVELLE ROUTE SÉCURISÉE
 * Permet à Laravel de récupérer la configuration du contrat de jeu.
 */
app.get("/get-game-config", (req, res) => {
    // Sécurité : On vérifie que c'est bien Laravel qui appelle
    const secret = req.headers['x-internal-secret'];
    if (!secret || secret !== INTERNAL_API_SECRET) {
        return res.status(403).json({ error: "Accès non autorisé." });
    }

    console.log('contract address:', contracts.game.address);
    console.log('contract abi:', contracts.game.abi);


    try {
        res.status(200).json({
            abi: contracts.game.abi,
            address: contracts.game.address,
            security_coefficient: SECURITY_COEFFICIENT ,
            pinata_secret: pinata.PINATA_SECRET,
            pinata_api_url: pinata.PINATA_API_URL,
            pinata_api_key: pinata.PINATA_API_KEY
        });
    } catch (error) {
        res.status(500).json({ error: "Erreur interne: impossible de lire la configuration." });
    }
});

// ===================================
// == LISTENERS (Logique de listener3.js + server.js)
// ===================================

/**
 * Fonction d'aide pour appeler Laravel de manière sécurisée
 */
async function postToLaravel(endpoint, body) {
    const url = `${LARAVEL_API_URL}${endpoint}`; // ex: /internal/update-balance
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET // <-- Notre header de sécurité
            },
            body: JSON.stringify(body)
        });

        if (response.ok) {
            console.log(`✅ Appel Laravel réussi vers ${endpoint}`);
        } else {
            const errorText = await response.text();
            console.error(`❌ Echec de l'appel Laravel vers ${endpoint}: ${response.status} ${errorText}`);
        }
    } catch (error) {
        console.error(`🚨 Erreur réseau en appelant ${endpoint}:`, error.message);
    }
}

/**
 * Démarre tous les listeners de blockchain
 */
function startBlockchainListeners() {
    console.log("🔊 Démarrage des listeners de blockchain...");

    // --- Listeners du Contrat de JEU (de listener3.js) ---
    gameContract.on("DepositReceived", (user, balance) => {
        console.log(`🔔 [JEU] DepositReceived: ${user}, ${balance}`);
        postToLaravel('/internal/update-balance', { wallet_address: user, balance: balance.toString() });
    });

    gameContract.on("PoolEmitted", (poolId, baseBet, users, premoveCIDs, poolSalt) => {
        console.log(`🔔 [JEU] PoolEmitted: ${poolId}`);
        postToLaravel('/internal/handle-pool-emited', {
            pool_id: poolId.toString(),
            base_bet: baseBet.toString(),
            users: users,
            premove_cids: premoveCIDs,
            pool_salt: poolSalt
        });
    });

    // --- Listeners du Contrat MARKETPLACE (de ton ancien listener.js) ---
    marketplaceContract.on("OfferCreated", (offerId, seller, sntAmount, avaxAmount, expiresAt) => {
        console.log(`🔔 [MARKETPLACE] OfferCreated: #${offerId}`);
        postToLaravel('/internal/trades/create', { 
            offerId: offerId.toString(),
            seller: seller,
            sntAmount: sntAmount.toString(),
            avaxAmount: avaxAmount.toString(),
            expiresAt: expiresAt.toString()
        });
    });

    marketplaceContract.on("OfferFulfilled", (offerId, buyer, seller, feeAmount) => {
        console.log(`🔔 [MARKETPLACE] OfferFulfilled: #${offerId}`);
        // Met à jour le statut du trade
        postToLaravel('/internal/trades/update-status', { 
            offerId: offerId.toString(), 
            newStatus: 'fulfilled', 
            buyerAddress: buyer 
        });
        // Déclenche la vérification du parrainage
        postToLaravel('/internal/trades/trigger-referral-check', { 
            buyer_address: buyer 
        });
        // Enregistre les frais pour l'influenceur
        postToLaravel('/internal/influencer/log-fee', { 
            seller_address: seller,
            fee_amount: feeAmount.toString()
        });
    });

    marketplaceContract.on("OfferCancelled", (offerId) => {
        console.log(`🔔 [MARKETPLACE] OfferCancelled: #${offerId}`);
        postToLaravel('/internal/trades/update-status', { 
            offerId: offerId.toString(), 
            newStatus: 'cancelled' 
        });
    });

    console.log("✅ Tous les listeners sont actifs.");
}






















// ===================================
// == DÉMARRAGE DU SERVEUR
// ===================================
app.listen(NODE_SERVER_PORT, () => {
    console.log(`🚀 Serveur API Node.js unifié démarré sur http://127.0.0.1:${NODE_SERVER_PORT}`);
    
    // Une fois le serveur démarré, on lance les listeners
    startBlockchainListeners();
});