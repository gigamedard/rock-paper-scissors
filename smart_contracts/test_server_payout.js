// Test script to verify server-triggered payout (Laravel → Node.js → Smart Contract → User)
import fetch from 'node-fetch';
import { JsonRpcProvider, formatEther } from "ethers";
import {
    LARAVEL_API_URL,
    FUJI_RPC_URL,
    INTERNAL_API_SECRET,
    contracts
} from "./config.js";

// Test user to receive payout
const testUser = {
    address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
    walletAddress: "0xdded5d7d8171b68b6105236164bca7a45839d150"
};

async function testServerPayout() {
    console.log("🧪 Testing Server-Triggered Payout Flow\n");
    console.log("📊 Flow: Laravel → Node.js Worker → Smart Contract → User Wallet\n");

    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    console.log("📍 Configuration:");
    console.log(`   Laravel API: ${LARAVEL_API_URL}`);
    console.log(`   Contract: ${contracts.game.address}`);
    console.log(`   User: ${testUser.address}\n`);

    // 1. Check balances BEFORE payout
    console.log("1️⃣ Checking balances BEFORE payout...");
    const contractBalanceBefore = await provider.getBalance(contracts.game.address);
    const userBalanceBefore = await provider.getBalance(testUser.address);

    console.log(`   Contract Balance: ${formatEther(contractBalanceBefore)} AVAX`);
    console.log(`   User Balance: ${formatEther(userBalanceBefore)} AVAX\n`);

    // 2. Define payout amount (0.001 AVAX)
    const payoutAmountEth = "0.001";
    console.log(`2️⃣ Payout Amount: ${payoutAmountEth} AVAX\n`);

    // 3. Trigger payout via Laravel API (which will call Node.js worker)
    console.log("3️⃣ Triggering payout via Laravel API...");
    console.log(`   Endpoint: ${LARAVEL_API_URL}/internal/payout`);

    try {
        const response = await fetch(`${LARAVEL_API_URL}/internal/payout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify({
                wallet_address: testUser.walletAddress,
                amount: payoutAmountEth
            })
        });

        const data = await response.json();

        if (!response.ok) {
            console.error(`   ❌ Laravel API Error (${response.status}):`, data);
            return;
        }

        console.log(`   ✅ Laravel API Response:`, data);

        if (data.success && data.txHash) {
            console.log(`   📡 Transaction Hash: ${data.txHash}`);
            console.log(`   🔗 View on Snowtrace: https://testnet.snowtrace.io/tx/${data.txHash}\n`);
        } else {
            console.log(`   ⚠️ Payout initiated but no transaction hash returned\n`);
        }

    } catch (error) {
        console.error("   ❌ ERROR calling Laravel API:", error.message);
        return;
    }

    // 4. Wait for blockchain to update
    console.log("4️⃣ Waiting 5 seconds for blockchain confirmation...");
    await new Promise(resolve => setTimeout(resolve, 5000));

    // 5. Check balances AFTER payout
    console.log("5️⃣ Checking balances AFTER payout...");
    const contractBalanceAfter = await provider.getBalance(contracts.game.address);
    const userBalanceAfter = await provider.getBalance(testUser.address);

    console.log(`   Contract Balance: ${formatEther(contractBalanceAfter)} AVAX`);
    console.log(`   User Balance: ${formatEther(userBalanceAfter)} AVAX\n`);

    // 6. Calculate differences
    console.log("6️⃣ Balance Changes:");
    const contractDiff = contractBalanceBefore - contractBalanceAfter;
    const userDiff = userBalanceAfter - userBalanceBefore;

    console.log(`   Contract: -${formatEther(contractDiff)} AVAX`);
    console.log(`   User: +${formatEther(userDiff)} AVAX\n`);

    // 7. Verify payout
    console.log("7️⃣ Verification:");

    if (userDiff > 0n) {
        console.log(`   ✅ Payout SUCCESSFUL!`);
        console.log(`   ✅ User received ${formatEther(userDiff)} AVAX`);
        console.log(`   ✅ Contract sent ${formatEther(contractDiff)} AVAX`);
        console.log(`\n   🎉 Server-triggered payout flow is working correctly!`);
    } else {
        console.log(`   ❌ Payout FAILED - User balance did not increase`);
        console.log(`   ℹ️ This might be due to:`);
        console.log(`      - Laravel API endpoint not configured`);
        console.log(`      - Node.js worker not processing the request`);
        console.log(`      - Smart contract transaction failed`);
    }

    console.log("\n✅ Server payout test completed!");
}

testServerPayout().catch(error => {
    console.error("\n❌ Fatal error:", error);
    process.exit(1);
});
