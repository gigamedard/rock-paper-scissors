import { JsonRpcProvider, Contract, parseEther } from "ethers";
import { contracts, LOCAL_HARDHAT_URL } from "./config.js";

async function check() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const game = new Contract(contracts.game.address, contracts.game.abi, provider);
    
    const baseBet = parseEther("0.01");
    const users = await game.getPoolUsers(baseBet);
    const info = await game.getPoolInfo(baseBet);
    
    console.log("Pool Info for 0.01:");
    console.log("  Pool ID:", info.poolId.toString());
    console.log("  Max Size:", info.maxSize.toString());
    console.log("  Users count:", users.length);
    console.log("  Users:", users);
    console.log("  Is Locked:", info.isLocked);
}

check();
