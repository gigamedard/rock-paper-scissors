import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const MASTER_PK = "***REMOVED***";
const adminWallet = new Wallet(MASTER_PK, provider);
const gameContract = new Contract(contracts.game.address, contracts.game.abi, adminWallet);

// Hardhat bots known addresses
const BOT_ADDRESSES = [
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC", // Bot 1 (index 2)
    "0x90F79bf6EB2c4f870365E785982E1f101E93b906", // Bot 2 (index 3)
    "0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65", // Bot 3 (index 4)
    "0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc", // Bot 4 (index 5)
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8", // Human user (index 1)
];

async function main() {
    const bets = ["0.001", "0.01", "0.05", "0.1"];

    console.log("=== POOL STATE ===");
    for (const bet of bets) {
        const betWei = parseEther(bet);
        try {
            const poolInfo = await gameContract.pools(betWei);
            const users = await gameContract.getPoolUsers(betWei);
            console.log(`\nPool ${bet} ETH: id=${poolInfo.poolId}, maxSize=${poolInfo.maxSize}, users=[${users.length}]`);
            if (users.length > 0) console.log(`  Users: ${users.join(", ")}`);
        } catch(e) { /* ignore */ }
    }

    console.log("\n=== USER POOL FLAGS (isUserInAnyPool) ===");
    for (const addr of BOT_ADDRESSES) {
        const flag = await gameContract.isUserInAnyPool(addr);
        const bal = await gameContract.getUserBalance(addr);
        console.log(`${addr}: inPool=${flag}, balance=${bal}`);
    }

    console.log("\n=== CONTRACT STATE ===");
    const nextId = await gameContract.nextPoolId();
    const secCoef = await gameContract.securityCoefficient();
    const contractBal = await gameContract.getContractBalance();
    console.log(`nextPoolId: ${nextId}, securityCoefficient: ${secCoef}, contractBalance: ${contractBal}`);
}

main().catch(console.error);
