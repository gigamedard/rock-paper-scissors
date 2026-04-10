import { JsonRpcProvider, Contract } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);
    
    const currentBlock = await provider.getBlockNumber();
    const fromBlock = Math.max(0, currentBlock - 100);
    
    console.log(`Checking blocks ${fromBlock} to ${currentBlock} for events...`);
    
    const events = await gameContract.queryFilter("*", fromBlock, currentBlock);
    console.log(`Found ${events.length} events total.`);
    
    events.forEach(e => {
        console.log(`- Event: ${e.fragment ? e.fragment.name : e.eventName} at block ${e.blockNumber}`);
    });
}
main().catch(console.error);
