import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { contracts } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider("http://127.0.0.1:8545");
    // Use the first Hardhat account
    const wallet = new Wallet("***REMOVED***", provider);
    const gameContract = new Contract("0x5FbDB2315678afecb367f032d93F642f64180aa3", contracts.game.abi, wallet);

    console.log("Sending test deposit of 11.0 ETH...");
    const tx = await gameContract.submitPremoveCID(parseEther("0.01"), "test_cid", { value: parseEther("11.0") });
    const receipt = await tx.wait();
    
    console.log("Transaction Hash:", tx.hash);
    console.log("Status:", receipt.status === 1 ? "Success" : "Failed");
    console.log("Logs count:", receipt.logs.length);
    
    receipt.logs.forEach((log, i) => {
        try {
            const parsed = gameContract.interface.parseLog(log);
            console.log(`Log ${i} name:`, parsed.name);
            console.log(`Log ${i} args:`, parsed.args);
        } catch (e) {
            console.log(`Log ${i} (unparsed) topics:`, log.topics);
        }
    });
}
main().catch(console.error);
