// app.js (Le nouveau script qui remplace server.js ET listener3.js)

import express from "express";
import { JsonRpcProvider, Wallet, Contract, formatEther, parseUnits} from "ethers";
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


console.log(`[DEBUG] Le worker écoute le contrat Battlepool à l'adresse: ${contracts.game.address}`);

/* --- Connexion au Marketplace (Fuji) ---
const marketplaceProvider = new JsonRpcProvider(FUJI_RPC_URL);
const marketplaceWallet = new Wallet(MARKETPLACE_WALLET_PK, marketplaceProvider);
const marketplaceContract = new Contract(contracts.marketplace.address, contracts.marketplace.abi, marketplaceWallet);
*/
// ===================================
// == API SERVER (Logique de server.js)
// ===================================
// (Ici on met toutes les routes POST de ton ancien server.js)

app.post("/sendPoolCID", async (req, res) => {
    try {
        const { poolId, CID } = req.body;

        if (!poolId || !CID) {
            return res.status(400).json({ error: "Missing required parameters." });
        }

        console.log(`📡 Sending  CID on smart contract...`);
        // Call the smart contract function (Replace with actual function name)
       const tx = await gameContract.storeMatchHistoryCID(poolId, CID);
       await tx.wait();

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending CID to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/sendSessionCID", async (req, res) => {
    try {
        const { wallet, CID } = req.body;

        if (!wallet || !CID) {
            return res.status(400).json({ error: "Missing required parameters." });
        }  

        console.log(`📡 Sending session  CID on smart contract...`) 
        // Call the smart contract function (Replace with actual function name)
        const tx = await gameContract.storeSessionCID(wallet, CID);
        await tx.wait();

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending session CID to smart contract:", error);
        res.status(500).json({ error: error.message });
    }

}
);

app.post("/sendPayment", async (req, res) => {
    try {
        const { wallet, amount } = req.body;

        if (!wallet || !amount) {
            return res.status(400).json({ error: "Missing required parameters." });
        }

        console.log(`📡 Sending payment - amount: ${formatEther(amount)} ETH on smart contract...`);
        // Call the smart contract function (Replace with actual function name)
        //const nonce = await provider.getTransactionCount(wallet, 'latest');

       
        const balanceBefore = await provider.getBalance(wallet);

        // Step 2: Send the payout transaction
        const tx = await gameContract.payOut(wallet, amount);
        const receipt = await tx.wait();

        // Step 3: Small delay to allow for sync (optional in local dev)
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Step 4: Get balance after payment
        const balanceAfter = await provider.getBalance(wallet);

        // Step 5: Calculate difference
        const balanceDiff = balanceAfter - balanceBefore;
        const received = balanceDiff >= amount;

        // ✅ Return result with verification
        res.json({
            success: true,
            txHash: tx.hash,
            received,
            expectedETH: formatEther(amount),
            actualIncrease: formatEther(balanceDiff)
        });
        //log the actual increase in balance
        console.log(`💰 Payment sent successfully! Expected: ${formatEther(amount)} ETH, Actual: ${formatEther(balanceDiff)} ETH`);
    } catch (error) {
        console.error("❌ Error sending Payement to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/sendBatchPayment", async (req, res) => {
    try {
        const { wallets, amounts } = req.body;

        if (!Array.isArray(wallets) || !Array.isArray(amounts) || wallets.length !== amounts.length) {
            return res.status(400).json({ error: "Invalid input. Ensure wallets and amounts are arrays of equal length." });
        }

        console.log(`📡 Sending batch payment - total recipients: ${wallets.length}`);

        // Call the smart contract function
        const tx = await gameContract.batchPayOut(wallets, amounts);
        await tx.wait();

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending batch payments to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/create-offer", async (req, res) => {
    try {
        const { sellerAddress, sntAmount, avaxAmount, durationHours } = req.body;

        if (!sellerAddress || !sntAmount || !avaxAmount || !durationHours) {
            return res.status(400).json({ error: "Paramètres manquants." });
        }

        console.log(`📡 Tentative de création d'offre pour ${sellerAddress}...`);

        // On convertit les montants pour le smart contract (avec 18 décimales)
        const sntAmountWei = parseUnits(sntAmount.toString(), 18);
        const avaxAmountWei = parseUnits(avaxAmount.toString(), 18);

        // Appel de la fonction du smart contract
        // Note: Le 'approve' doit avoir été fait par l'utilisateur côté frontend AVANT
        const tx = await marketplaceContract.createOffer(sntAmountWei, avaxAmountWei, durationHours);

        await tx.wait(); // On attend que la transaction soit minée

        console.log(`✅ Offre créée avec succès ! Hash: ${tx.hash}`);

        res.json({ success: true, txHash: tx.hash });

    } catch (error) {
        console.error("❌ Erreur lors de la création de l'offre:", error);
        res.status(500).json({ error: error.message });
    }
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

    gameContract.on("DepositReceived", (user, balance) => {
        console.log(`🔔 [JEU] DepositReceived: ${user}, ${balance}`);

        // Convertir le 'balance' (un BigInt en Wei) en string 'Ether'
        const balanceInEther = formatEther(balance); 

        postToLaravel('/internal/update-balance', { 
            wallet_address: user, 
            balance: balanceInEther // On envoie la valeur convertie
        });
    });

    gameContract.on("PoolEmitted", (poolId, baseBet, users, premoveCIDs, poolSalt) => {
        console.log(`🔔 [JEU] PoolEmitted: ${poolId}`);
        
        // Convertir le 'baseBet' (un BigInt en Wei) en string 'Ether'
        const baseBetInEther = formatEther(baseBet);

        postToLaravel('/internal/handle-pool-emited', {
            pool_id: poolId.toString(),
            base_bet: baseBetInEther, // <-- CORRIGÉ
            users: users,
            premove_cids: premoveCIDs,
            pool_salt: poolSalt
        });
    });

    /* --- Listeners du Contrat MARKETPLACE (de ton ancien listener.js) ---
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

    */console.log("✅ Tous les listeners sont actifs.");
}






















// ===================================
// == DÉMARRAGE DU SERVEUR
// ===================================
app.listen(NODE_SERVER_PORT, () => {
    console.log(`🚀 Serveur API Node.js unifié démarré sur http://127.0.0.1:${NODE_SERVER_PORT}`);
    
    // Une fois le serveur démarré, on lance les listeners
    startBlockchainListeners();
});