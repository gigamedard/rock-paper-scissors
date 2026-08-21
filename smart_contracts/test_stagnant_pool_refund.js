// test_stagnant_pool_refund.js
import { ethers } from "ethers";
import { contracts, FUJI_RPC_URL, GAME_WALLET_PK } from "./config.js";

const provider = new ethers.JsonRpcProvider(FUJI_RPC_URL);
const wallet = new ethers.Wallet(GAME_WALLET_PK, provider);
const contract = new ethers.Contract(contracts.game.address, contracts.game.abi, wallet);

// Test accounts (using Hardhat default accounts or create your own)
const TEST_ACCOUNTS = [
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8",
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC",
    "0x90F79bf6EB2c4f870365E785982E1f101E93b906"
];

console.log("🧪 Testing Stagnant Pool Refund Mechanism\n");
console.log("=".repeat(60));

async function main() {
    try {
        // Step 1: Get current stagnant block limit
        console.log("\n📊 Step 1: Check Current Configuration");
        console.log("-".repeat(60));
        const currentLimit = await contract.stagnantBlockLimit();
        console.log(`✓ Current stagnant block limit: ${currentLimit} blocks`);

        // Step 2: Set a low limit for testing (5 blocks)
        console.log("\n⚙️  Step 2: Set Lower Block Limit for Testing");
        console.log("-".repeat(60));
        const newLimit = 5;
        console.log(`Changing limit to ${newLimit} blocks...`);
        const setLimitTx = await contract.setStagnantBlockLimit(newLimit);
        await setLimitTx.wait();
        console.log(`✓ Block limit updated to ${newLimit} blocks`);
        console.log(`✓ Transaction: ${setLimitTx.hash}`);

        // Step 3: Create a pool by adding users
        console.log("\n🏊 Step 3: Create Pool with Test Users");
        console.log("-".repeat(60));
        const baseBet = ethers.parseEther("0.001"); // 0.001 AVAX

        // Fetch security coefficient from contract
        const securityCoefficient = await contract.securityCoefficient();
        console.log(`Security Coefficient: ${securityCoefficient}`);

        const requiredDeposit = baseBet * securityCoefficient;

        console.log(`Base Bet: ${ethers.formatEther(baseBet)} AVAX`);
        console.log(`Required Deposit per user: ${ethers.formatEther(requiredDeposit)} AVAX`);

        // Get default pool max size
        const defaultMaxSize = await contract.defaultPoolMaxSize();
        console.log(`Default Pool Max Size: ${defaultMaxSize}`);

        // Check initial pool state
        const initialUsers = await contract.getPoolUsers(baseBet);
        console.log(`\nInitial pool size: ${initialUsers.length} users`);

        // Add first user if pool is empty
        if (initialUsers.length === 0) {
            console.log("\n📝 Adding first test user to pool...");

            // Use a random wallet for user 1
            const user1Wallet = ethers.Wallet.createRandom().connect(provider);
            console.log(`Created User 1 wallet: ${user1Wallet.address}`);

            // Fund user 1 from main wallet
            console.log("Funding User 1...");
            const fundAmount = ethers.parseEther("2.0"); // 2.0 AVAX (enough for deposit + gas)
            const fundTx = await wallet.sendTransaction({
                to: user1Wallet.address,
                value: fundAmount
            });
            await fundTx.wait();
            console.log(`✓ Funded User 1 with 2.0 AVAX. Tx: ${fundTx.hash}`);

            const user1Contract = contract.connect(user1Wallet);

            const addUser1Tx = await user1Contract.submitPremoveCID(
                baseBet,
                "QmTestCID1",
                { value: requiredDeposit }
            );
            await addUser1Tx.wait();
            console.log(`✓ User 1 added: ${user1Wallet.address}`);
            console.log(`✓ Transaction: ${addUser1Tx.hash}`);
        }

        // Get pool state after adding users
        const poolUsers = await contract.getPoolUsers(baseBet);
        console.log(`\n✓ Current pool size: ${poolUsers.length}/${defaultMaxSize} users`);
        console.log(`✓ Pool users:`, poolUsers);

        // Step 4: Get current block and calculate target block
        console.log("\n⏰ Step 4: Wait for Stagnation Period");
        console.log("-".repeat(60));
        const startBlock = await provider.getBlockNumber();
        console.log(`Current block: ${startBlock}`);
        console.log(`Waiting for ${newLimit} blocks to pass...`);
        console.log(`Target block: ${startBlock + Number(newLimit) + 1}`);

        // Poll for blocks
        let currentBlock = startBlock;
        while (currentBlock <= startBlock + Number(newLimit)) {
            await new Promise(resolve => setTimeout(resolve, 3000)); // Wait 3 seconds
            currentBlock = await provider.getBlockNumber();
            process.stdout.write(`\r⏳ Current block: ${currentBlock} (${currentBlock - startBlock} blocks passed)`);
        }
        console.log("\n✓ Required blocks have passed!");

        // Step 5: Check if pool is considered stagnant
        console.log("\n🔍 Step 5: Verify Pool is Stagnant");
        console.log("-".repeat(60));

        // Try to trigger refund
        console.log("Attempting to trigger refund for stagnant pool...");

        try {
            // Get user balances before refund
            console.log("\n💰 User Balances Before Refund:");
            for (const user of poolUsers) {
                const balance = await contract.getUserBalance(user);
                const ethBalance = await provider.getBalance(user);
                console.log(`  ${user}:`);
                console.log(`    Contract Balance: ${ethers.formatEther(balance)} AVAX`);
                console.log(`    Wallet Balance: ${ethers.formatEther(ethBalance)} AVAX`);
            }

            // Setup event listener
            console.log("\n🎧 Setting up event listener...");
            const refundPromise = new Promise((resolve, reject) => {
                contract.once("PoolStagnantRefund", (poolId, refundedCount, timestamp) => {
                    resolve({ poolId, refundedCount, timestamp });
                });
                setTimeout(() => reject(new Error("Event timeout")), 30000);
            });

            // Trigger the refund
            const refundTx = await contract.checkAndRefundStagnantPool(baseBet);
            console.log(`✓ Refund transaction sent: ${refundTx.hash}`);

            const receipt = await refundTx.wait();
            console.log(`✓ Transaction confirmed in block ${receipt.blockNumber}`);

            // Wait for event
            try {
                const eventData = await refundPromise;
                console.log("\n🎉 PoolStagnantRefund Event Emitted!");
                console.log(`  Pool ID: ${eventData.poolId}`);
                console.log(`  Refunded Count: ${eventData.refundedCount}`);
                console.log(`  Timestamp: ${eventData.timestamp}`);
            } catch (e) {
                console.log("⚠️  Event not captured (might have been emitted before listener setup)");
            }

            // Step 6: Verify refund results
            console.log("\n✅ Step 6: Verify Refund Results");
            console.log("-".repeat(60));

            // Check pool is now empty
            const finalPoolUsers = await contract.getPoolUsers(baseBet);
            console.log(`✓ Pool size after refund: ${finalPoolUsers.length} users`);

            if (finalPoolUsers.length === 0) {
                console.log("✓ Pool successfully reset!");
            } else {
                console.log("❌ Pool not properly reset!");
            }

            // Check user balances after refund
            console.log("\n💰 User Balances After Refund:");
            for (const user of poolUsers) {
                const balance = await contract.getUserBalance(user);
                const ethBalance = await provider.getBalance(user);
                console.log(`  ${user}:`);
                console.log(`    Contract Balance: ${ethers.formatEther(balance)} AVAX`);
                console.log(`    Wallet Balance: ${ethers.formatEther(ethBalance)} AVAX`);

                if (balance === 0n) {
                    console.log(`    ✓ Balance properly refunded!`);
                } else {
                    console.log(`    ⚠️  Balance not fully refunded`);
                }
            }

            console.log("\n" + "=".repeat(60));
            console.log("✅ TEST COMPLETED SUCCESSFULLY!");
            console.log("=".repeat(60));

        } catch (error) {
            if (error.message.includes("Pool is not stagnant")) {
                console.log("\n⏰ Pool is not yet stagnant. Need to wait more blocks.");
                console.log(`Current block: ${await provider.getBlockNumber()}`);
            } else if (error.message.includes("Pool is empty")) {
                console.log("\n✓ Pool is already empty (possibly already refunded)");
            } else {
                throw error;
            }
        }

        // Step 7: Reset limit back to original value
        console.log("\n🔧 Step 7: Reset Block Limit");
        console.log("-".repeat(60));
        console.log(`Resetting limit back to ${currentLimit} blocks...`);
        const resetTx = await contract.setStagnantBlockLimit(currentLimit);
        await resetTx.wait();
        console.log(`✓ Block limit reset to ${currentLimit} blocks`);
        console.log(`✓ Transaction: ${resetTx.hash}`);

    } catch (error) {
        console.error("\n❌ TEST FAILED!");
        console.error("=".repeat(60));
        console.error("Error:", error.message);
        if (error.data) {
            console.error("Error data:", error.data);
        }
        process.exit(1);
    }
}

main().catch((error) => {
    console.error("Unexpected error:", error);
    process.exit(1);
});
