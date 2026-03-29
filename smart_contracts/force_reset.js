/**
 * force_reset.js
 * Forces a reset of all pools by calling validatePool (as owner) on all common base bets.
 * Use this when a pool is stuck in `isLockedForValidation = true` state.
 */
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
const MASTER_PK = "***REMOVED***"; // Hardhat Account #0 (deployer)
const adminWallet = new Wallet(MASTER_PK, provider);
const gameContract = new Contract(contracts.game.address, contracts.game.abi, adminWallet);

async function main() {
    console.log("==================================================");
    console.log("🔑 ADMIN: Force-resetting all locked/stuck pools...");
    console.log("==================================================");

    const bets = ["0.001", "0.01", "0.05", "0.1", "0.5", "1.0"];
    let poolsReset = 0;

    for (const bet of bets) {
        const betWei = parseEther(bet);
        try {
            const users = await gameContract.getPoolUsers(betWei);
            const poolInfo = await gameContract.pools(betWei);

            console.log(`\n🔍 Checking pool for ${bet} ETH: ${users.length} users, poolId=${poolInfo.poolId}`);

            if (users.length > 0) {
                console.log(`   Users: ${users.join(", ")}`);
                
                // Try stagnant refund first (works if pool is NOT locked, just stagnant)
                try {
                    console.log(`   → Trying stagnant refund (fast-forwarding 200 blocks)...`);
                    await provider.send("hardhat_mine", ["0xC8"]); // 200 blocks in hex
                    const tx1 = await gameContract.checkAndRefundStagnantPool(betWei, { gasLimit: 500000 });
                    await tx1.wait();
                    console.log(`   ✅ Stagnant refund succeeded for ${bet} ETH.`);
                    poolsReset++;
                    continue;
                } catch (e1) {
                    console.log(`   → Stagnant refund failed (pool may be locked): ${e1.shortMessage || e1.reason}`);
                }

                // If pool is locked for validation, call validatePool as owner to clear it
                try {
                    console.log(`   → Trying validatePool (owner only)...`);
                    const tx2 = await gameContract.validatePool(betWei, { gasLimit: 500000 });
                    await tx2.wait();
                    console.log(`   ✅ validatePool succeeded for ${bet} ETH. Pool cleared.`);
                    poolsReset++;
                } catch (e2) {
                    console.log(`   → validatePool failed: ${e2.shortMessage || e2.reason}`);
                }
            }
        } catch (err) {
            // Pool may not exist or other error — silently skip
        }
    }

    console.log("\n==================================================");
    if (poolsReset > 0) {
        console.log(`🎉 Reset ${poolsReset} pool(s). All user states cleared.`);
        console.log(`   Users can now rejoin freely.`);
    } else {
        console.log(`👍 No stuck pools found, or pools were already clean.`);
    }
    console.log("==================================================");
}

main().catch(console.error);
