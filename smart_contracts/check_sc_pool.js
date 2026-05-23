import { JsonRpcProvider, Contract, parseEther } from "ethers";
import { contracts, LOCAL_HARDHAT_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);
    
    const baseBet = parseEther("0.01");
    const [poolId, maxSize, userCount, isLocked] = await gameContract.getPoolInfo(baseBet);
    const users = await gameContract.getPoolUsers(baseBet);
    
    console.log("=== Smart Contract Pool Info ===");
    console.log(`Pool ID: ${poolId}`);
    console.log(`Max Size: ${maxSize}`);
    console.log(`User Count: ${userCount}`);
    console.log(`Is Locked: ${isLocked}`);
    console.log(`Users:`, users);
}

main().catch(console.error);
