import express from "express";
import { JsonRpcProvider, Wallet, Contract } from "ethers";
import { contractAddress3, privateKey3, localHardhatUrl, abi3 } from "./config.js";

const app = express();
app.use(express.json());

// Initialize provider, wallet, and contract
const provider = new JsonRpcProvider(localHardhatUrl);
const wallet = new Wallet(privateKey3, provider);
const contract = new Contract(contractAddress3, abi3, wallet);

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
       const tx = await contract.storeMatchHistoryCID(poolId, CID);
       await tx.wait();

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending data to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});

// Start Node.js server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 Node.js API running on http://127.0.0.1:${PORT}`);
});

