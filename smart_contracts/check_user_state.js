import { JsonRpcProvider, Contract } from "ethers";
import { contracts, FUJI_RPC_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);
    const userAddress = "0x13681eba8a5efdbb53e5689c16c86014ea2dbe16";

    console.log("Checking state for:", userAddress);

    const inPool = await contract.isUserInAnyPool(userAddress);
    console.log("isUserInAnyPool:", inPool);

    const cooldown = await contract.nextSessionAllowedTime(userAddress);
    console.log("nextSessionAllowedTime:", cooldown.toString(), "Current Time:", Math.floor(Date.now() / 1000));

    const baseBetWei = 50000000000000000n; // 0.05 ether
    const pool = await contract.pools(baseBetWei);
    console.log("Pool for 0.05 AVAX:", pool.poolId.toString(), "Locked?", pool.isLockedForValidation);
}

main().catch(console.error);
