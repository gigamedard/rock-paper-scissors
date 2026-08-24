/**
 * E2E Test: Full Pool Lifecycle
 * 
 * Prerequisites:
 * 1. npx hardhat node          (terminal 1)
 * 2. node app.js               (terminal 2)
 * 3. php artisan serve         (terminal 3)
 * 4. node run_batch_processor.js (terminal 4)
 * 5. php artisan db:seed --class=E2ETestSeeder
 * 6. Deploy contracts: cd battlepool && npx hardhat run full_deploy.js --network localhost
 * 
 * Then run: node e2e_test.js
 */
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts, FUJI_RPC_URL, GAME_WALLET_PK } from "./config.js";

// SECURITY: Use GAME_WALLET_PK (owner) from config.js instead of hardcoded key.
const HARDHAT_ACCOUNT_0_PK = GAME_WALLET_PK;

// 4 Hardhat default accounts matching E2ETestSeeder
const USERS = [
    "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266",   // Alice
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8",   // Bob
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC",   // Charlie
    "0x90F79bf6EB2c4f870365E785982E1f101E93b906",   // Diana
];

const PREMOVE_CIDS = [
    "cid_alice_e2e_test",
    "cid_bob_e2e_test",
    "cid_charlie_e2e_test",
    "cid_diana_e2e_test",
];

// Valid hex salt (64 chars = 32 bytes)
const POOL_SALT = "a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const funder = new Wallet(HARDHAT_ACCOUNT_0_PK, provider);

    console.log("=".repeat(60));
    console.log("🧪 E2E TEST: Pool 4 joueurs → Fights → Re-pooling");
    console.log("=".repeat(60));

    // Step 1: Fund the test account
    const TEST_ADDRESS = "0xb8195e6e7761ab2758803dcf2fd38016b2bea079";
    // SECURITY: This is a TEST-ONLY key for a throwaway test account, not a production key.
    // It does not control any production funds or contract roles.
    const TEST_PK = "0x8351c039abec71bfb0338862fcbc129b487108e8e84a7aa8957f407a1c1d2162";

    console.log("\n💰 Step 1: Funding test account...");
    try {
        const balance = await provider.getBalance(TEST_ADDRESS);
        if (balance < parseEther("0.1")) {
            const fundTx = await funder.sendTransaction({
                to: TEST_ADDRESS,
                value: parseEther("1.0")
            });
            await fundTx.wait();
            console.log("   ✅ Funded with 1 ETH");
        } else {
            console.log("   ✅ Already funded");
        }
    } catch (e) {
        console.log("   ⚠️ Funding skipped:", e.message);
    }

    // Step 2: Fire PoolEmitted with 4 users
    console.log("\n🚀 Step 2: Firing PoolEmitted event with 4 users...");
    console.log("   Users:", USERS.map((a, i) => `${["Alice", "Bob", "Charlie", "Diana"][i]}: ${a.slice(0, 8)}...`).join(", "));

    const tester = new Wallet(TEST_PK, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, tester);

    const tx = await contract.triggerPoolEmittedEventForTesting(
        1001,                          // poolId
        parseEther("0.001"),           // baseBet (matches E2ETestSeeder bet_amount)
        USERS,
        PREMOVE_CIDS,
        POOL_SALT
    );
    const receipt = await tx.wait();

    console.log(`   ✅ Transaction: ${tx.hash}`);
    console.log(`   Block: ${receipt.blockNumber}`);

    // Step 3: Wait and check
    console.log("\n⏳ Step 3: Waiting for app.js to pick up the event (5s)...");
    await new Promise(r => setTimeout(r, 5000));

    console.log("\n📋 Step 4: Check these places for results:");
    console.log("   1. app.js console     → 🔔 [JEU] PoolEmitted: 1001");
    console.log("   2. app.js console     → 📡 [pool/validate] Validating pool");
    console.log("   3. Laravel logs       → Pool is 100% valid");
    console.log("   4. Laravel logs       → Fight created + completed");
    console.log("   5. Laravel logs       → SessionFinishedEventListener");
    console.log("   6. batch processor    → InternalPoolService grouping users");
    console.log("\n   View Laravel logs: Get-Content storage\\logs\\laravel.log -Tail 40");

    console.log("\n" + "=".repeat(60));
    console.log("✅ E2E test event fired! Check logs above.");
    console.log("=".repeat(60));
}

main().catch(e => { console.error("❌ Error:", e.message); process.exit(1); });
