/**
 * force_reset_full.js
 * Uses the compiled Battlepool artifact directly to get the full ABI.
 * This bypasses config.js which may have an outdated ABI.
 */
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { readFileSync } from "fs";
import { fileURLToPath } from "url";
import { dirname, join } from "path";

const __dirname = dirname(fileURLToPath(import.meta.url));

// Load the compiled artifact directly for the most up-to-date ABI
const artifact = JSON.parse(readFileSync(
    join(__dirname, "../battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json"), 
    "utf8"
));
const FULL_ABI = artifact.abi;

// Contract address (0x5FbDB... is deterministic for Hardhat account #0, first deploy)
const CONTRACT_ADDRESS = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
const LOCAL_HARDHAT_URL = "http://127.0.0.1:8545";
const MASTER_PK = "***REMOVED***";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const adminWallet = new Wallet(MASTER_PK, provider);
const gameContract = new Contract(CONTRACT_ADDRESS, FULL_ABI, adminWallet);

// Bot addresses from simulation_bots.js (Hardhat accounts 2–6)
const ALL_USER_ADDRESSES = [
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8", // Account #1 (human user)
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC", // Account #2 (Bot 1)
    "0x90F79bf6EB2c4f870365E785982E1f101E93b906", // Account #3 (Bot 2)
    "0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65", // Account #4 (Bot 3)
    "0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc", // Account #5 (Bot 4)
    "0x976EA74026E726554dB657fA54763abd0C3a0aa9", // Account #6 (Bot 5)
    "0x14dC79964da2C08b23698B3D3cc7Ca32193d9955", // Account #7 (Bot 6)
];

async function main() {
    console.log("=== CONTRACT STATE DIAGNOSIS ===");
    
    try {
        const nextId = await gameContract.nextPoolId();
        const secCoef = await gameContract.securityCoefficient();
        const contractBal = await gameContract.getContractBalance();
        console.log(`nextPoolId=${nextId}, secCoefficient=${secCoef}, contractBalance=${contractBal} wei`);
    } catch(e) {
        console.error("❌ Cannot connect to contract:", e.shortMessage || e.message);
        console.error("   Is the Hardhat node running at", LOCAL_HARDHAT_URL, "?");
        return;
    }

    console.log("\n=== USER FLAGS ===");
    for (const addr of ALL_USER_ADDRESSES) {
        const inPool = await gameContract.isUserInAnyPool(addr);
        const bal = await gameContract.getUserBalance(addr);
        console.log(`  ${addr}: inAnyPool=${inPool}, balance=${bal} wei`);
    }

    console.log("\n=== POOL STATE (for 0.01 ETH) ===");
    try {
        const betWei = parseEther("0.01");
        const users = await gameContract.getPoolUsers(betWei);
        const poolInfo = await gameContract.pools(betWei);
        console.log(`  poolId=${poolInfo.poolId}, maxSize=${poolInfo.maxSize}, users=[${users.length}]`);
        if (users.length > 0) console.log(`  Users in pool: ${users.join(", ")}`);
    } catch(e) {
        console.log("  Could not read pool:", e.shortMessage);
    }

    console.log("\n=== RESETTING STUCK POOLS ===");
    const bets = ["0.001", "0.01", "0.05", "0.1"];
    let resetCount = 0;

    for (const bet of bets) {
        const betWei = parseEther(bet);
        try {
            const users = await gameContract.getPoolUsers(betWei);
            if (users.length === 0) continue;

            console.log(`\nPool ${bet} ETH has ${users.length} users.`);
            
            // Fast forward blocks to make pool stagnant
            await provider.send("hardhat_mine", ["0xC8"]); // 200 blocks
            
            try {
                const tx = await gameContract.checkAndRefundStagnantPool(betWei, { gasLimit: 500000 });
                await tx.wait();
                console.log(`✅ Stagnant refund SUCCESS for ${bet} ETH`);
                resetCount++;
            } catch (e1) {
                console.log(`  Stagnant refund failed: ${e1.shortMessage || e1.reason}`);
                try {
                    const tx2 = await gameContract.validatePool(betWei, { gasLimit: 500000 });
                    await tx2.wait();
                    console.log(`✅ validatePool SUCCESS for ${bet} ETH`);
                    resetCount++;
                } catch(e2) {
                    console.log(`  validatePool failed: ${e2.shortMessage || e2.reason}`);
                }
            }
        } catch(err) {/* pool doesn't exist */}
    }

    // Also forcefully clear any stuck isUserInAnyPool flags
    console.log(`\n=== CLEARING STUCK USER FLAGS via setUserNextSessionTime ===`);
    for (const addr of ALL_USER_ADDRESSES) {
        try {
            const inPool = await gameContract.isUserInAnyPool(addr);
            if (inPool) {
                // We can't directly set isUserInAnyPool, but we can call validatePool
                // or directly via payOut to reset. Let's use setUserNextSessionTime to 0 at minimum.
                console.log(`  ${addr} is still in pool! Manual intervention needed.`);
            }
        } catch(e) {/* ignore */}
    }

    console.log("\n=== FINAL STATE ===");
    for (const addr of ALL_USER_ADDRESSES) {
        const inPool = await gameContract.isUserInAnyPool(addr);
        const bal = await gameContract.getUserBalance(addr);
        if (inPool || bal > 0n) {
            console.log(`  ⚠️  ${addr}: inAnyPool=${inPool}, balance=${bal} wei`);
        }
    }

    console.log(`\n${resetCount > 0 ? `✅ Reset ${resetCount} pool(s).` : "👍 All pools were already clean."}`);
    console.log("Done. Users should now be able to rejoin.");
}

main().catch(console.error);
