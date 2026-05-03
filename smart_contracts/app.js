// app.js (Le nouveau script qui remplace server.js ET listener3.js)

import express from "express";
import { JsonRpcProvider, Wallet, Contract, formatEther, parseUnits, parseEther } from "ethers";
import { createHelia } from 'helia';
import { json } from '@helia/json';
import { FsBlockstore } from 'blockstore-fs';
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

// --- HELIA IPFS SETUP ---
let heliaJson;
(async () => {
    try {
        const blockstore = new FsBlockstore('./ipfs-storage');
        const helia = await createHelia({ 
            blockstore,
            start: false // Crucial: prevents hanging on P2P network discovery in local dev
        });
        heliaJson = json(helia);
        console.log('✅ Local Helia IPFS node initialized');
    } catch (e) {
        console.error('❌ Failed to init Helia:', e);
    }
})();

app.post("/ipfs/add-json", async (req, res) => {
    try {
        console.log("Receiving IPFS payload:", req.body);
        if (!heliaJson) return res.status(503).json({ error: "IPFS node not ready" });

        const content = req.body; // Expecting the full JSON object directly

        const cid = await heliaJson.add(content);
        const cidString = cid.toString();

        console.log(`📦 Pinned to Local IPFS: ${cidString}`);
        res.json({ Hash: cidString });
    } catch (e) {
        console.error("IPFS Add Error:", e);
        res.status(500).json({ error: e.message });
    }
});

// --- Connexion au Jeu (Hardhat) ---
const gameProvider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const gameWallet = new Wallet(GAME_WALLET_PK, gameProvider);
const gameContract = new Contract(contracts.game.address, contracts.game.abi, gameWallet);


console.log(`[DEBUG] Le worker écoute le contrat Battlepool à l'adresse: ${contracts.game.address}`);

// --- Connexion au Marketplace (Fuji) ---
// const marketplaceProvider = new JsonRpcProvider(FUJI_RPC_URL); // Utilise gameProvider pour économiser les ressources
// const marketplaceWallet = new Wallet(MARKETPLACE_WALLET_PK, gameProvider);
const marketplaceContract = new Contract(contracts.marketplace.address, contracts.marketplace.abi, gameWallet);
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


        const balanceBefore = await gameProvider.getBalance(wallet);

        // Step 2: Send the payout transaction
        const tx = await gameContract.payOut(wallet, amount);
        const receipt = await tx.wait();

        // Step 3: Small delay to allow for sync (optional in local dev)
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Step 4: Get balance after payment
        const balanceAfter = await gameProvider.getBalance(wallet);

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

app.get('/getUserNonce/:wallet', async (req, res) => {
    try {
        const { wallet } = req.params;
        const nonce = await gameContract.nonces(wallet);
        res.json({ nonce: nonce.toString() });
    } catch (error) {
        console.error("Error fetching nonce:", error);
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
            marketplace: contracts.marketplace,
            snt: contracts.snt,
            security_coefficient: SECURITY_COEFFICIENT,
            pinata_secret: pinata.PINATA_SECRET,
            pinata_api_url: pinata.PINATA_API_URL,
            pinata_api_key: pinata.PINATA_API_KEY
        });
    } catch (error) {
        res.status(500).json({ error: "Erreur interne: impossible de lire la configuration." });
    }
});

// ===================================
// == ROUTES APPELÉES PAR LARAVEL (Web3Helper)
// ===================================

app.post("/pool/validate", async (req, res) => {
    try {
        const { baseBet } = req.body;
        console.log(`📡 [pool/validate] Validating pool for baseBet: ${baseBet}`);

        if (!baseBet) {
            return res.status(400).json({ error: "baseBet is required" });
        }

        const baseBetWei = parseUnits(baseBet.toString(), 18);
        console.log(`   [Action] Calling gameContract.validatePool(${baseBetWei.toString()})...`);
        const tx = await gameContract.validatePool(baseBetWei);
        const receipt = await tx.wait();

        console.log(`   ✅ Pool Validated! TX Hash: ${receipt.hash}`);
        res.json({ success: true, txHash: receipt.hash });
    } catch (error) {
        console.error("❌ Error in /pool/validate:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/refundUsers", async (req, res) => {
    try {
        const { wallets } = req.body;
        console.log(`📡 [refundUsers] Refunding ${wallets?.length || 0} wallets (logged only)`);
        res.json({ success: true, message: "Refund instruction acknowledged" });
    } catch (error) {
        console.error("❌ Error in /refundUsers:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/pool/invalidate", async (req, res) => {
    try {
        const { baseBet, invalidUsers } = req.body;
        console.log(`📡 [pool/invalidate] Invalidating users for pool ${baseBet}:`, invalidUsers);

        if (!baseBet || !invalidUsers) {
            return res.status(400).json({ error: "baseBet and invalidUsers are required" });
        }

        const baseBetWei = parseUnits(baseBet.toString(), 18);
        console.log(`   [Action] Calling gameContract.invalidatePoolUsers...`);
        const tx = await gameContract.invalidatePoolUsers(baseBetWei, invalidUsers);
        const receipt = await tx.wait();

        console.log(`   ✅ Pool Users Invalidated! TX Hash: ${receipt.hash}`);
        res.json({ success: true, txHash: receipt.hash });
    } catch (error) {
        console.error("❌ Error in /pool/invalidate:", error);
        res.status(500).json({ error: error.message });
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
    console.log(`📡 [BRIDGE] Calling Laravel: ${url}`);
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
let lastBlock = 0;

async function startBlockchainListeners() {
    console.log("🔄 Starting Polling Listeners (Robust Mode)...");

    try {
        lastBlock = 0; // Start from 0 for local hardhat catch-up
        console.log(`   Starting from Block: ${lastBlock}`);
    } catch (e) {
        console.error("Failed to get initial block:", e);
    }

    let isPolling = false;

    setInterval(async () => {
        if (isPolling) return;
        isPolling = true;

        try {
            const chainBlock = await gameProvider.getBlockNumber();
            // Chunking: process max 100 blocks at a time
            const currentBlock = Math.min(chainBlock, lastBlock + 100);

            if (currentBlock > lastBlock) {
                console.log(`📡 Catching up: blocks ${lastBlock + 1} to ${currentBlock}...`);

                // 1. PoolEmitted
                const poolEvents = await gameContract.queryFilter("PoolEmitted", lastBlock + 1, currentBlock);
                for (const event of poolEvents) {
                    const { args } = event;
                    console.log(`🔔 [JEU] PoolEmitted: ${args[0]}`);
                    postToLaravel('/internal/handle-pool-emited', {
                        pool_id: args[0].toString(),
                        base_bet: args[1].toString(),
                        users: args[2],
                        premove_cids: args[3],
                        pool_salt: args[4],
                        balances: args[5].map(b => b.toString())
                    });
                }

                // 2. DepositReceived
                const depositEvents = await gameContract.queryFilter("DepositReceived", lastBlock + 1, currentBlock);
                for (const event of depositEvents) {
                    const { args } = event;
                    console.log(`🔔 [JEU] DepositReceived: ${args[0]}, ${args[1]}`);
                    postToLaravel('/internal/update-balance', { wallet_address: args[0], balance: args[1].toString() });
                }

                // 4. SecurityCoefficientUpdated
                const coeffEvents = await gameContract.queryFilter("SecurityCoefficientUpdated", lastBlock + 1, currentBlock);
                for (const event of coeffEvents) {
                    const { args } = event;
                    console.log(`🔔 [JEU] SecurityCoefficientUpdated: ${args[0]}`);
                    postToLaravel('/internal/update-setting', {
                        key: 'security_coefficient',
                        value: args[0].toString(),
                        type: 'integer'
                    });
                }

                // 5. PayoutProcessed
                const payoutEvents = await gameContract.queryFilter("PayoutProcessed", lastBlock + 1, currentBlock);
                for (const event of payoutEvents) {
                    const { args } = event;
                    console.log(`🔔 [JEU] PayoutProcessed: ${args[0]}, ${args[1]}`);
                    postToLaravel('/internal/update-balance', { wallet_address: args[0], balance: "0" });
                }

                // 6. PlayerClaimed
                const claimEvents = await gameContract.queryFilter("PlayerClaimed", lastBlock + 1, currentBlock);
                for (const event of claimEvents) {
                    const { args } = event;
                    console.log(`🔔 [JEU] PlayerClaimed: ${args[0]}, Amount=${args[1]}, Nonce=${args[2]}`);
                    postToLaravel('/internal/update-balance', { wallet_address: args[0], balance: "0" });
                }

                // --- LISTENERS MARKETPLACE ---

                // 4. OfferCreated
                const offerCreatedEvents = await marketplaceContract.queryFilter("OfferCreated", lastBlock + 1, currentBlock);
                for (const event of offerCreatedEvents) {
                    const { args } = event;
                    const offerId = args[0];
                    console.log(`🔔 [MARKET] OfferCreated: ID=${offerId}, Seller=${args[1]}`);

                    try {
                        // Fetch details (expiration) form contract
                        const offerDetails = await marketplaceContract.offers(offerId);
                        const expiresAt = offerDetails.expiresAt;

                        postToLaravel('/internal/trades/create', {
                            offerId: offerId.toString(),
                            seller: args[1],
                            sntAmount: formatEther(args[2]), // Wei to Eth/Token unit if needed, check controller expectations. 
                            // Controller validation says numeric. internalTradeController stores strictly what receives.
                            // Frontend sends Wei to contract. Contract emits Wei.
                            // However, DB usually stores "human readable" or consistent units.
                            // 'formatEther' converts Wei to string decimal.
                            // Let's assume Laravel expects human readable for display or verify internalTradeController logic.
                            // internalTradeController just stores it. Frontend displays it.
                            // Frontend `loadTrades` does `parseFloat(trade.snt_amount).toLocaleString()`. 
                            // If we store Wei, parseFloat might be huge. 
                            // Let's use formatEther to store as "tokens" not "wei".
                            avaxAmount: formatEther(args[3]),
                            expiresAt: expiresAt.toString()
                        });
                    } catch (err) {
                        console.error(`❌ Failed to fetch offer details for ${offerId}:`, err);
                    }
                }

                // 5. OfferFulfilled
                const offerFulfilledEvents = await marketplaceContract.queryFilter("OfferFulfilled", lastBlock + 1, currentBlock);
                for (const event of offerFulfilledEvents) {
                    const { args } = event;
                    console.log(`🔔 [MARKET] OfferFulfilled: ID=${args[0]}, Buyer=${args[1]}`);
                    postToLaravel('/internal/trades/update-status', {
                        offerId: args[0].toString(),
                        newStatus: 'fulfilled',
                        buyerAddress: args[1]
                    });
                }

                // 6. OfferCancelled
                const offerCancelledEvents = await marketplaceContract.queryFilter("OfferCancelled", lastBlock + 1, currentBlock);
                for (const event of offerCancelledEvents) {
                    const { args } = event;
                    console.log(`🔔 [MARKET] OfferCancelled: ID=${args[0]}`);
                    postToLaravel('/internal/trades/update-status', {
                        offerId: args[0].toString(),
                        newStatus: 'cancelled'
                    });
                }

                // 7. SNT Transfer (Sync Balance & Referral Check)
                // We re-enable this to catch Direct Mints or P2P transfers not covered by Marketplace events.
                // CRITICAL: We skip if the transfer involves the Marketplace to avoid double-counting (since OfferFulfilled handles that).
                // Assuming we need to instantiate it similar to gameContract.
                // Re-using gameWallet (provider) for reading events.
                const sntContract = new Contract(contracts.snt.address, contracts.snt.abi, gameWallet);
                const transferEvents = await sntContract.queryFilter("Transfer", lastBlock + 1, currentBlock);

                for (const event of transferEvents) {
                    const { args } = event;
                    const from = args[0];
                    const to = args[1];
                    const amount = formatEther(args[2]);

                    // DEDUPLICATION: Skip if Marketplace is sender or receiver (handled by OfferFulfilled/OfferCreated logic usually, 
                    // though OfferCreated doesn't transfer token to buyer, OfferFulfilled does).
                    // Actually, OfferFulfilled updates DB balance based on trade struct.
                    // If we also update based on Transfer event, we double count.
                    // Marketplace address: contracts.marketplace.address
                    if (from.toLowerCase() === contracts.marketplace.address.toLowerCase() ||
                        to.toLowerCase() === contracts.marketplace.address.toLowerCase()) {
                        console.log(`⚠️ [SNT] Ignoring Marketplace Transfer: ${from} -> ${to}`);
                        continue;
                    }

                    console.log(`🔔 [SNT] Transfer: From=${from} To=${to} Value=${amount}`);

                    postToLaravel('/internal/trades/sync-transfer', {
                        from: from,
                        to: to,
                        amount: amount
                    });
                }

                lastBlock = currentBlock;
            }
        } catch (error) {
            console.error("Polling Error:", error.message);
        } finally {
            isPolling = false;
        }
    }, 5000); // Poll every 5 seconds
}






















// ===================================
// == DÉMARRAGE DU SERVEUR
// ===================================
app.listen(NODE_SERVER_PORT, '0.0.0.0', () => {
    console.log(`🚀 Serveur API Node.js unifié démarré on port ${NODE_SERVER_PORT}`);

    // Une fois le serveur démarré, on lance les listeners
    startBlockchainListeners();
});