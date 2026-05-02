import { JsonRpcProvider, Contract, parseEther } from "ethers";
import { contracts, LOCAL_HARDHAT_URL } from "./config.js";

async function check() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const game = new Contract(contracts.game.address, contracts.game.abi, provider);
    
    const address = "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266";
    const inPool = await game.isUserInAnyPool(address);
    console.log(`Address ${address} In Any Pool: ${inPool}`);
}

check();
