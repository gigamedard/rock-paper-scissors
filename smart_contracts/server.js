import express from "express";
import { JsonRpcProvider, Wallet, Contract, formatEther} from "ethers";
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
        const tx = await contract.storeSessionCID(wallet, CID);
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
        const tx = await contract.payOut(wallet, amount);
        await tx.wait();

        res.json({ success: true, txHash: tx.hash });
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
        const tx = await contract.batchPayOut(wallets, amounts);
        await tx.wait();

        res.json({ success: true, txHash: tx.hash });
    } catch (error) {
        console.error("❌ Error sending batch payments to smart contract:", error);
        res.status(500).json({ error: error.message });
    }
});


// Start Node.js server and schedule periodic POST request
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`🚀 Node.js API running on http://127.0.0.1:${PORT}`);
    // Every 3 seconds send a POST request to /batch_pool_processing
    
});



