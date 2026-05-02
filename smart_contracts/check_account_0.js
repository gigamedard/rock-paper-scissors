import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { contracts, LOCAL_HARDHAT_URL } from "./config.js";

async function check() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet("***REMOVED***", provider); // Account 0
    
    const game = new Contract(contracts.game.address, contracts.game.abi, wallet);
    
    console.log("Checking Account 0:", wallet.address);
    try {
        // Base bet 0.01, total 0.011 to be safe
        const tx = await game.submitPremoveCID(parseEther("0.01"), "bag-test-cid", { value: parseEther("0.011") });
        console.log("Success! Account 0 joined. TX:", tx.hash);
    } catch (e) {
        console.error("Failed to join:", e.reason || e.message || e);
    }
}

check();
