import express from "express";
import { JsonRpcProvider, Wallet, Contract, formatEther, parseUnits, parseEther } from "ethers";
import { createHelia } from 'helia';
import { json } from '@helia/json';
import { FsBlockstore } from 'blockstore-fs';
import {
  LARAVEL_API_URL,
  INTERNAL_API_SECRET,
  LOCAL_HARDHAT_URL,
  FUJI_RPC_URL,
  NODE_SERVER_PORT,
  GAME_WALLET_PK,
  MARKETPLACE_WALLET_PK,
  contracts
} from "./config.js";

const app = express();
app.use(express.json());

// --- HELIA IPFS SETUP ---
let heliaJson;
(async () => {
	try {
		const blockstore = new FsBlockstore('./ipfs-storage');
		const helia = await createHelia({ blockstore });
		heliaJson = json(helia);
		console.log('✅ Local Helia IPFS node initialized');
	} catch (e) {
		console.error('❌ Failed to init Helia:', e);
	}
})();

app.post("/ipfs/add-json", async (req, res) => {
	try {
		if (!heliaJson) return res.status(503).json({ error: "IPFS node not ready" });

		const content = req.body; 

		const cid = await heliaJson.add(content);
		const cidString = cid.toString();

		console.log(`📦 Pinned to Local IPFS: ${cidString}`);
		res.json({ Hash: cidString });
	} catch (e) {
		console.error("IPFS Add Error:", e);
		res.status(500).json({ error: e.message });
	}
});

// Initialize provider, wallet, and contract
const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const wallet = new Wallet(GAME_WALLET_PK, provider);
const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

const fujiRpcUrl = FUJI_RPC_URL;
const Avax_wallet = new Wallet(MARKETPLACE_WALLET_PK, fujiRpcUrl);

// Wrapper to retry transactions on Nonce issues
async function sendTransactionWithRetry(actionName, txFunc, maxRetries = 3) {
    let retries = 0;
    while (retries < maxRetries) {
        try {
            const nonce = await wallet.getNonce("pending");
            const tx = await txFunc({ nonce });
            await tx.wait();
            return tx;
        } catch (error) {
            const msg = error.message || error.toString() || "";
            if (msg.includes("nonce too low") || msg.includes("replacement transaction underpriced") || msg.includes("already known")) {
                retries++;
                console.warn(`[Nonce Error - ${actionName}] Retrying (Attempt ${retries}/${maxRetries})...`);
                await new Promise(res => setTimeout(res, 500 * retries));
            } else {
                throw error;
            }
        }
    }
    throw new Error(`[${actionName}] Transaction failed after ${maxRetries} retries due to nonce issues.`);
}

// ABI et Adresse du contrat MarketplaceEscrow
const marketplaceAbi = [

// Watchdog Ping
setInterval(async () => {
    try {
        await fetch(`${LARAVEL_API_URL}/internal/ping`, {
            method: 'POST',
            headers: {
                'X-Internal-Secret': INTERNAL_API_SECRET,
                'Content-Type': 'application/json'
            }
        });
    } catch (e) {
        console.error('❌ Watchdog Ping Failed:', e.message);
    }
}, 5000); // ping every 5 seconds
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
const marketplaceContract = new Contract(marketplaceAddress, marketplaceAbi, Avax_wallet);

// --- NOUVELLE ROUTE ---
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


// --- Route de configuration pour le frontend (via Laravel) ---
app.get("/get-game-config", (req, res) => {
	const internalSecret = req.headers['x-internal-secret'];
	const validSecret = "0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086"; // Shared secret

	if (internalSecret !== validSecret) {
		console.warn("⚠️  Unauthorized config access attempt");
		// return res.status(403).json({ error: "Unauthorized" });
		// Optional: for development we might be lenient or the header is missing
	}

	res.json({
		address: contracts.game.address,
		abi: contracts.game.abi,
		marketplace: contracts.marketplace?.address || "0x5FbDB2315678afecb367f032d93F642f64180aa3",
		snt: "0x05A26c7f06127710463692263E12c1BF51A34184", // SNT Token Address
		pinata_api_url: "https://api.pinata.cloud"
	});
});













/**
 * Handle Laravel request to send data to smart contract
 */
app.post("/sendPoolCID", async (req, res) => {
	try {
		const { poolId, CID } = req.body;

		if (!poolId || !CID) {
			return res.status(400).json({ error: "Missing required parameters." });
		}

		console.log(`📡 Sending  CID on smart contract...`);
		// Call the smart contract function (Replace with actual function name)
		const tx = await sendTransactionWithRetry("storeMatchHistoryCID", (overrides) => contract.storeMatchHistoryCID(poolId, CID, overrides));

		res.json({ success: true, txHash: tx.hash });
	} catch (error) {
		console.error("❌ Error storing Match History CID:", error);
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
		// Call the smart contract function 
		const tx = await sendTransactionWithRetry("storeSessionCID", (overrides) => contract.storeSessionCID(wallet, CID, overrides));

		res.json({ success: true, txHash: tx.hash });
	} catch (error) {
		console.error("❌ Error storing session CID:", error);
		res.status(500).json({ error: error.message });
	}
});

app.post("/sendPayment", async (req, res) => {
	try {
		const { wallet, amount } = req.body;

		if (!wallet || !amount) {
			return res.status(400).json({ error: "Missing required parameters." });
		}

		console.log(`📡 Sending payment - amount: ${formatEther(amount)} ETH on smart contract...`);
		const balanceBefore = await provider.getBalance(wallet);

		// Step 2: Send the payout transaction
		const tx = await sendTransactionWithRetry("payOut", (overrides) => contract.payOut(wallet, amount, overrides));

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
		const tx = await sendTransactionWithRetry("batchPayOut", (overrides) => contract.batchPayOut(wallets, amounts, overrides));

		res.json({ success: true, txHash: tx.hash });
	} catch (error) {
		console.error("❌ Error sending batch payments:", error);
		res.status(500).json({ error: error.message });
	}
});

// --- BLOCKCHAIN READ & REFUND ENDPOINTS ---

app.get("/pool/users/:baseBet", async (req, res) => {
	try {
		const baseBet = parseEther(req.params.baseBet.toString());
		const users = await contract.getPoolUsers(baseBet);
		res.json({ success: true, users: users });
	} catch (error) {
		console.error("❌ Error fetching pool users:", error.message);
		res.status(500).json({ error: error.message });
	}
});

app.get("/pool/premove/:wallet", async (req, res) => {
	try {
		const wallet = req.params.wallet;
		const cid = await contract.getPremoveCID(wallet);
		res.json({ success: true, cid: cid });
	} catch (error) {
		console.error("❌ Error fetching premove CID:", error.message);
		res.status(500).json({ error: error.message });
	}
});

app.post("/refundUsers", async (req, res) => {
	try {
		const { wallets } = req.body;

		if (!Array.isArray(wallets) || wallets.length === 0) {
			return res.status(400).json({ error: "Invalid input. Ensure wallets is a non-empty array." });
		}

		console.log(`📡 Refunding invalid users: ${wallets.join(', ')}`);

		const amounts = [];
		for (const wallet of wallets) {
			const bal = await contract.getUserBalance(wallet);
			amounts.push(bal);
		}

		const tx = await sendTransactionWithRetry("batchPayOut", (overrides) => contract.batchPayOut(wallets, amounts, overrides));

		res.json({ success: true, txHash: tx.hash });
	} catch (error) {
		console.error("❌ Error refunding users:", error.message);
		res.status(500).json({ error: error.message });
	}
});

app.post("/pool/validate", async (req, res) => {
	try {
		const { baseBet } = req.body;
		if (!baseBet) {
			return res.status(400).json({ error: "Missing baseBet." });
		}
		console.log(`📡 Validating pool for baseBet: ${baseBet}`);
		const parsedBaseBet = parseEther(baseBet.toString());
		const tx = await sendTransactionWithRetry("validatePool", (overrides) => contract.validatePool(parsedBaseBet, overrides));

		return res.json({ success: true, txHash: tx.hash, message: "Pool validated successfully" });
	} catch (contractError) {
		console.error("❌ Error validating pool:", contractError.message);
		res.status(500).json({ error: contractError.message });
	}
});

app.post("/pool/invalidate", async (req, res) => {
	try {
		const { baseBet, invalidUsers } = req.body;
		if (!baseBet || !Array.isArray(invalidUsers) || invalidUsers.length === 0) {
			return res.status(400).json({ error: "Invalid input. Ensure baseBet and non-empty invalidUsers array." });
		}
		console.log(`📡 Invalidating users: ${invalidUsers.join(', ')} for baseBet: ${baseBet}`);
		const parsedBaseBet = parseEther(baseBet.toString());
		const tx = await sendTransactionWithRetry("invalidatePoolUsers", (overrides) => contract.invalidatePoolUsers(parsedBaseBet, invalidUsers, overrides));

		return res.json({ success: true, txHash: tx.hash, message: "Pool users invalidated successfully" });
	} catch (contractError) {
		console.error("❌ Error invalidating pool users:", contractError.message);
		res.status(500).json({ error: contractError.message });
	}
});

// Start Node.js server and schedule periodic POST request
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
	console.log(`🚀 Node.js API running on http://127.0.0.1:${PORT}`);
	// Every 3 seconds send a POST request to /batch_pool_processing

});



