// Fund test account and fire a PoolEmitted event
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts, FUJI_RPC_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    // Use Hardhat default account #0 (has 10000 ETH)
    const HARDHAT_ACCOUNT_0_PK = "***REMOVED***";
    const funder = new Wallet(HARDHAT_ACCOUNT_0_PK, provider);

    // Test account from test_event.js
    const TEST_ADDRESS = "0xb8195e6e7761ab2758803dcf2fd38016b2bea079";

    // Step 1: Fund the test account
    console.log("💰 Funding test account...");
    const fundTx = await funder.sendTransaction({
        to: TEST_ADDRESS,
        value: parseEther("1.0")
    });
    await fundTx.wait();
    console.log("✅ Test account funded with 1 ETH");

    // Step 2: Connect as the test account and call triggerPoolEmittedEventForTesting
    const TEST_PK = "0x8351c039abec71bfb0338862fcbc129b487108e8e84a7aa8957f407a1c1d2162";
    const tester = new Wallet(TEST_PK, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, tester);

    console.log(`\n🚀 Firing PoolEmitted event on contract ${contracts.game.address}...`);

    const tx = await contract.triggerPoolEmittedEventForTesting(
        999,                        // poolId
        parseEther("0.001"),        // baseBet
        [
            "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266",
            "0x70997970C51812dc3A010C7d01b50e0d17dc79C8"
        ],
        ["cid_test_1", "cid_test_2"],
        "0x_test_salt_123456789"
    );
    await tx.wait();

    console.log(`✅ Transaction confirmed! Hash: ${tx.hash}`);
    console.log("👉 Check the app.js console for the PoolEmitted event pickup.");
    console.log("👉 Check Laravel logs for the /internal/handle-pool-emited call.");
}

main().catch(e => { console.error("❌ Error:", e.message); process.exit(1); });
