import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
// Account 0 is the owner/deployer of the contract
const MASTER_PK = "***REMOVED***"; 
const adminWallet = new Wallet(MASTER_PK, provider);

const gameContract = new Contract(contracts.game.address, contracts.game.abi, adminWallet);

async function main() {
    console.log("==================================================");
    console.log("🧹 ADMIN: Forcing Pool Refund and Ejection...");
    console.log("==================================================");

    // Common Base Bets
    const bets = ["0.001", "0.01", "0.05", "0.1", "0.5", "1.0"];
    
    let poolsEjected = 0;

    for (const bet of bets) {
        const betWei = parseEther(bet);
        try {
            // Get users in pool
            const users = await gameContract.getPoolUsers(betWei);
            
            if (users && users.length > 0) {
                console.log(`\n🔍 Found Pool for Base Bet: ${bet} ETH containing ${users.length} users.`);
                
                // 1. Advance the Hardhat blockchain by 150 blocks to bypass stagnantBlockLimit (100)
                console.log(`   - Fast-forwarding Hardhat blocks to trigger stagnation timeout...`);
                await provider.send("hardhat_mine", ["0x96"]); // 150 blocks in hex
                
                // 2. Safely call checkAndRefundStagnantPool which does the refund AND array deletion natively
                console.log(`   - Kicking users out of the active pool & refunding...`);
                let tx = await gameContract.checkAndRefundStagnantPool(betWei, { gasLimit: 500000 });
                await tx.wait();

                console.log(`✅ Successfully refunded ${users.length} users and emptied Pool array for ${bet} ETH.`);
                poolsEjected++;
            }
        } catch (err) {
            // Some contract functions might revert if pool doesn't exist, ignore
        }
    }

    console.log("\n==================================================");
    if (poolsEjected > 0) {
        console.log(`🎉 Mission Accomplished. Ejected and cleared ${poolsEjected} active pools.`);
    } else {
        console.log(`👍 No active pools found with stuck users.`);
    }
    console.log("==================================================");
}

main().catch(console.error);
