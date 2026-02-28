import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { FUJI_RPC_URL, GAME_WALLET_PK, contracts } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const wallet = new Wallet(GAME_WALLET_PK, provider);

    // Use an inline ABI for the missing validatePool function
    const abi = [
        "function pools(uint256) view returns (uint256, uint256, uint256, bool)",
        "function validatePool(uint256) external"
    ];
    const contract = new Contract(contracts.game.address, abi, wallet);

    const baseBet = parseEther("0.05");

    console.log("Checking pool state...");
    const state = await contract.pools(baseBet);
    console.log("Users in pool currently:", state[0].toString());
    console.log("Is Pool Locked?:", state[3]);

    if (state[3] === true || state[0] > 0n) {
        console.log("Pool is locked or has stuck users. Force validating the pool to clear it...");
        const tx = await contract.validatePool(baseBet);
        console.log("Transaction sent:", tx.hash);
        await tx.wait();
        console.log("Pool successfully unlocked and users cleared!");
    } else {
        console.log("Pool is already clean and unlocked.");
    }
}

main().catch(console.error);
