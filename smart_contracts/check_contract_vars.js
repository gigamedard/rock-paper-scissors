import { JsonRpcProvider, Contract, parseEther } from "ethers";
import { contracts, FUJI_RPC_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    // Let's use a dynamic inline ABI to ensure we can read what we need
    const abi = [
        "function securityCoefficient() view returns (uint256)",
        "function feeBasisPoints() view returns (uint256)",
        "function nextSessionAllowedTime(address) view returns (uint256)",
        "function defaultPoolMaxSize() view returns (uint256)",
        "function isUserInAnyPool(address) view returns (bool)"
    ];

    const contract = new Contract(contracts.game.address, abi, provider);
    const user = "0x13681eba8a5efdbb53e5689c16c86014ea2dbe16";

    console.log("Checking Live Contract Variables...");

    try {
        const secCoeff = await contract.securityCoefficient();
        console.log("securityCoefficient:", secCoeff.toString());

        const fee = await contract.feeBasisPoints();
        console.log("feeBasisPoints:", fee.toString());

        const cooldownTime = await contract.nextSessionAllowedTime(user);
        const currentTime = Math.floor(Date.now() / 1000);
        console.log("nextSessionAllowedTime:", Number(cooldownTime), "Current:", currentTime);
        console.log("Is User in Cooldown?", currentTime < Number(cooldownTime));

        const inPool = await contract.isUserInAnyPool(user);
        console.log("isUserInAnyPool:", inPool);

    } catch (err) {
        console.error("Contract Error:", err.message);
    }
}

main().catch(console.error);
