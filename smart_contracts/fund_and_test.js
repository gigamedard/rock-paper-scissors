// Fund test account and fire a PoolEmitted event
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts, FUJI_RPC_URL, GAME_WALLET_PK } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    // SECURITY: Use GAME_WALLET_PK (owner) from config.js — no hardcoded keys.
    // The owner can call triggerPoolEmittedEventForTesting (onlyOwner + chainId 31337).
    const funder = new Wallet(GAME_WALLET_PK, provider);

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

    // Step 2: Connect as the owner and call triggerPoolEmittedEventForTesting
    const tester = new Wallet(GAME_WALLET_PK, provider);
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
