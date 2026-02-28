import { JsonRpcProvider, Contract, parseEther } from "ethers";
import { contracts, FUJI_RPC_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

    const baseBet = parseEther("0.05");
    const user = "0x13681eba8a5efdbb53e5689c16c86014ea2dbe16";

    console.log("Checking status for User:", user);

    try {
        const isUserInPool = await contract.isUserInAnyPool(user);
        console.log("isUserInAnyPool:", isUserInPool);

        const poolState = await contract.pools(baseBet);
        console.log("Raw Pool State:", poolState);

        const usersInPool = await contract.getPoolUsers(baseBet);
        console.log("Array of Users in Pool:", usersInPool);

        // Also check if user exists in the array
        const isReallyInArray = usersInPool.some(addr => addr.toLowerCase() === user.toLowerCase());
        console.log("User physically present in pool array?", isReallyInArray);
    } catch (err) {
        console.error("Contract Error:", err.message);
    }
}

main().catch(console.error);
