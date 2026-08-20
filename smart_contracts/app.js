// app.js (Le nouveau script qui remplace server.js ET listener3.js)

import express from "express";
import { JsonRpcProvider, Wallet, Contract, formatEther, parseUnits, parseEther, solidityPackedKeccak256, getBytes } from "ethers";
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
    SIGNER_WALLET_PK,
    SECURITY_COEFFICIENT,
    pinata,
    contracts
} from "./config.js";
import { startIndexer } from "./indexer/indexer.js";

// ===================================
// == INITIALISATION
// ===================================

const app = express();
app.use(express.json());

// --- HELIA IPFS SETUP ---
let heliaJson;
async function initIPFS() {
    try {
        const blockstore = new FsBlockstore('./ipfs-storage');
        const helia = await createHelia({ 
            blockstore,
            start: false // Crucial: prevents hanging on P2P network discovery in local dev
        });
        heliaJson = json(helia);
        console.log('✅ Local Helia IPFS node initialized');
        return true;
    } catch (e) {
        console.error('❌ Failed to init Helia:', e);
        return false;
    }
}

app.post("/ipfs/add-json", async (req, res) => {
    try {
        console.log("Receiving IPFS payload:", req.body);
        if (!heliaJson) {
            throw new Error("IPFS Node not initialized");
        }
        const cid = await heliaJson.add(req.body);
        console.log(`📦 Real IPFS CID generated: ${cid}`);
        res.json({ Hash: cid.toString() });
    } catch (e) {
        console.error("IPFS Error:", e);
        res.status(500).json({ error: e.message });
    }
});

// --- Connexion au Jeu (Hardhat) ---
const gameProvider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const gameWallet = new Wallet(GAME_WALLET_PK, gameProvider);
const gameContract = new Contract(contracts.game.address, contracts.game.abi, gameWallet);
// SECURITY: separate signer wallet for claim signatures (rotatable via setSigner).
const signerWallet = new Wallet(SIGNER_WALLET_PK, gameProvider);

// Queue de transactions globale pour éviter les conflits de nonce
let txQueue = Promise.resolve();
let managedNonce = null;

async function enqueueTx(txFunction) {
    return new Promise((resolve, reject) => {
        txQueue = txQueue.then(async () => {
            try {
                // Initialize nonce from blockchain if not yet tracked
                if (managedNonce === null) {
                    managedNonce = await gameProvider.getTransactionCount(gameWallet.address, "pending");
                }
                const currentNonce = managedNonce;
                managedNonce++; // Pre-increment for next queued tx

                const tx = await txFunction(currentNonce);
                const receipt = await tx.wait();
                resolve(tx);
            } catch (err) {
                // On failure, re-sync nonce from blockchain to recover
                managedNonce = null;
                reject(err);
            }
        });
    });
}

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
        const tx = await enqueueTx((nonce) => gameContract.storeMatchHistoryCID(poolId, CID, { nonce }));

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
        const tx = await enqueueTx((nonce) => gameContract.storeSessionCID(wallet, CID, { nonce }));

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
        const tx = await enqueueTx((nonce) => gameContract.payOut(wallet, amount, { nonce }));

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
        const tx = await enqueueTx((nonce) => gameContract.batchPayOut(wallets, amounts, { nonce }));

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending batch payments to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/setUserLimits", async (req, res) => {
    try {
        const { wallet, maxBaseBet, maxQ, minCooldown, expiry } = req.body;

        if (!wallet || maxBaseBet === undefined || maxQ === undefined || minCooldown === undefined || expiry === undefined) {
            return res.status(400).json({ error: "Missing required parameters." });
        }

        console.log(`📡 Setting user limits for ${wallet} on smart contract...`);
        const tx = await enqueueTx((nonce) => gameContract.setUserLimits(wallet, maxBaseBet, maxQ, minCooldown, expiry, { nonce }));

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error setting user limits on smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

app.post("/setUserNextSessionTime", async (req, res) => {
    try {
        const { wallet, nextTime } = req.body;

        if (!wallet || nextTime === undefined) {
            return res.status(400).json({ error: "Missing required parameters." });
        }

        console.log(`📡 Setting next session time for ${wallet} to ${nextTime} on smart contract...`);
        const tx = await enqueueTx((nonce) => gameContract.setUserNextSessionTime(wallet, nextTime, { nonce }));

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error setting user next session time on smart contract:", error);
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

app.post("/generate-signature", async (req, res) => {
    try {
        const { wallet, amount } = req.body;
        if (!wallet || !amount) {
            return res.status(400).json({ error: "Missing required parameters." });
        }
        
        console.log(`📡 Generating signature for claim: ${wallet} - ${amount} wei`);
        const nonce = await gameContract.nonces(wallet);
        const chainId = (await gameProvider.getNetwork()).chainId;
        // Signature valid for 24h (prevents indefinite replay)
        const deadline = Math.floor(Date.now() / 1000) + 86400;
        
        const messageHash = solidityPackedKeccak256(
            ["address", "uint256", "uint256", "address", "uint256", "uint256"],
            [wallet, amount, nonce, contracts.game.address, chainId, deadline]
        );
        const messageHashBytes = getBytes(messageHash);
        const signature = await signerWallet.signMessage(messageHashBytes);
        
        res.json({ signature, deadline });
    } catch (error) {
        console.error("❌ Error generating signature:", error);
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

app.post("/verify-snt-transfer", async (req, res) => {
    // Authentication: verify internal API secret
    const secret = req.headers['x-internal-secret'];
    if (!secret || secret !== INTERNAL_API_SECRET) {
        return res.status(403).json({ error: "Accès non autorisé." });
    }

    try {
        const { txHash, expectedAmount, sender } = req.body;

        if (!txHash || !expectedAmount || !sender) {
            return res.status(400).json({ error: "Paramètres manquants." });
        }

        console.log(`📡 Checking SNT transfer tx: ${txHash}`);

        const receipt = await gameProvider.getTransactionReceipt(txHash);
        if (!receipt) {
             return res.status(404).json({ error: "Transaction introuvable ou non minée." });
        }
        if (receipt.status !== 1) {
             return res.status(400).json({ error: "Transaction échouée (reverted)." });
        }

        const sntContract = new Contract(contracts.snt.address, contracts.snt.abi, gameProvider);
        let validTransferFound = false;

        for (const log of receipt.logs) {
            if (log.address.toLowerCase() === contracts.snt.address.toLowerCase()) {
                try {
                    const parsedLog = sntContract.interface.parseLog({ topics: [...log.topics], data: log.data });
                    if (parsedLog && parsedLog.name === "Transfer") {
                        const from = parsedLog.args[0].toLowerCase();
                        const to = parsedLog.args[1].toLowerCase();
                        const amount = formatEther(parsedLog.args[2]);

                        // Verify sender, recipient (must be platform wallet), and amount
                        const platformWallet = (process.env.SNT_RECEIVER_WALLET || gameWallet.address).toLowerCase();
                        if (from === sender.toLowerCase() && to === platformWallet && parseFloat(amount) >= parseFloat(expectedAmount)) {
                            validTransferFound = true;
                            break;
                        }
                    }
                } catch(err) {
                    // Ignore parse errors
                }
            }
        }

        if (!validTransferFound) {
             return res.status(400).json({ error: "Aucun transfert SNT valide correspondant n'a été trouvé." });
        }

        res.json({ success: true, message: "Transfert SNT vérifié avec succès." });

    } catch (error) {
        console.error("❌ Erreur /verify-snt-transfer:", error);
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

app.get("/admin/contract-stats", async (req, res) => {
    try {
        const devBalance = await gameContract.devBalance();
        res.json({
            houseBalance: formatEther(devBalance)
        });
    } catch (error) {
        res.status(500).json({ error: error.message });
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
        const tx = await enqueueTx((nonce) => gameContract.validatePool(baseBetWei, { nonce }));

        console.log(`   ✅ Pool Validated! TX Hash: ${tx.hash}`);
        res.json({ success: true, txHash: tx.hash });
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
        const tx = await enqueueTx((nonce) => gameContract.invalidatePoolUsers(baseBetWei, invalidUsers, { nonce }));

        console.log(`   ✅ Pool Users Invalidated! TX Hash: ${tx.hash}`);
        res.json({ success: true, txHash: tx.hash });
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
 * Démarre tous les listeners de blockchain.
 * Délègue à l'indexeur robuste (polling par plage de blocs, état persistant,
 * idempotence, reorg safety, backoff exponentiel).
 */
async function startBlockchainListeners() {
    console.log("🔄 Starting Robust Indexer (polling + reorg safety + idempotence)...");

    const sntContract = new Contract(contracts.snt.address, contracts.snt.abi, gameWallet);

    await startIndexer({
        provider: gameProvider,
        gameContract,
        marketplaceContract,
        sntContract,
        marketplaceAddress: contracts.marketplace.address,
        postToLaravel,
    });
}






















// ===================================
// == DÉMARRAGE DU SERVEUR
// ===================================
(async () => {
    // 1. Initialiser IPFS
    await initIPFS();

    // 2. Démarrer le serveur
    app.listen(NODE_SERVER_PORT, '0.0.0.0', () => {
        console.log(`🚀 Serveur API Node.js unifié démarré on port ${NODE_SERVER_PORT}`);
        
        // 3. Lancer les listeners blockchain
        startBlockchainListeners();
    });
})();