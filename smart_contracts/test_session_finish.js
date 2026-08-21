// Test script to verify automatic payout on Session Finished event
import fetch from 'node-fetch';
import { JsonRpcProvider, formatEther } from "ethers";
import {
    LARAVEL_API_URL,
    FUJI_RPC_URL,
    contracts
} from "./config.js";

// Test user (User 1 from simulation)
const testUser = {
    address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
    id: null // Will fetch from API
};

async function testSessionFinishPayout() {
    console.log("🧪 Testing Automatic Payout on Session Finish\n");

    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    // 1. Login to get User ID
    console.log("1️⃣ Logging in to get User ID...");
    // We'll cheat and assume we can get user ID from a previous login or just query the DB via a helper route if we had one.
    // Instead, let's just try to find the user ID by logging in again.

    // Actually, let's just assume User ID 1 (or similar) based on previous logs, 
    // but to be safe, I'll implement the login flow quickly.
    // Wait, I can't sign messages easily here without importing Wallet.
    // Let's use a simpler approach: I'll assume the user exists and has ID 1 (or I'll fetch it via a public endpoint if available).
    // The simulation logs didn't show User IDs.

    // Let's use the wallet address to find the user via the debug route? No, the debug route takes ID.
    // I'll use `php artisan tinker` to find the ID first, then hardcode it or pass it as arg.
    // But for this script to be standalone, I'll use the login flow.

    // ... (Login flow omitted for brevity, I'll just use the wallet address and hope I can find the ID via a public endpoint or just guess it's likely one of the first users)

    // BETTER IDEA: I'll update the debug route to accept wallet_address instead of ID.
    // But I already wrote the route.

    // Let's try to login.
    const { Wallet } = await import("ethers");
    const signer = new Wallet("400e1b043832260518588f42125acf9b974f6365f78b8ab6899eefe350b228b4", provider);

    let response = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ wallet_address: signer.address, locale: 'en' })
    });
    let data = await response.json();
    const signature = await signer.signMessage(data.message);

    response = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ wallet_address: signer.address, signature: signature, locale: 'en' })
    });
    data = await response.json();
    testUser.id = data.user.id;
    console.log(`   ✅ User ID: ${testUser.id}`);
    console.log(`   ✅ Wallet: ${testUser.address}\n`);

    // 2. Check balances BEFORE
    console.log("2️⃣ Checking balances BEFORE event...");
    const contractBalanceBefore = await provider.getBalance(contracts.game.address);
    const userBalanceBefore = await provider.getBalance(testUser.address);
    console.log(`   Contract: ${formatEther(contractBalanceBefore)} AVAX`);
    console.log(`   User: ${formatEther(userBalanceBefore)} AVAX\n`);

    // 3. Trigger Session Finished Event
    console.log("3️⃣ Triggering SessionFinishedEvent...");
    response = await fetch(`${LARAVEL_API_URL}/debug-session-finish`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: testUser.id })
    });

    const result = await response.json();
    console.log(`   Response:`, result);

    if (!response.ok) {
        console.error("   ❌ Failed to trigger event");
        return;
    }

    // 4. Wait for processing
    console.log("4️⃣ Waiting 10 seconds for async processing...");
    await new Promise(resolve => setTimeout(resolve, 10000));

    // 5. Check balances AFTER
    console.log("5️⃣ Checking balances AFTER event...");
    const contractBalanceAfter = await provider.getBalance(contracts.game.address);
    const userBalanceAfter = await provider.getBalance(testUser.address);

    console.log(`   Contract: ${formatEther(contractBalanceAfter)} AVAX`);
    console.log(`   User: ${formatEther(userBalanceAfter)} AVAX\n`);

    const diff = userBalanceAfter - userBalanceBefore;
    if (diff > 0n) {
        console.log(`   🎉 SUCCESS! User received ${formatEther(diff)} AVAX`);
        console.log(`   ✅ Automatic payout logic is working!`);
    } else {
        console.log(`   ⚠️ No balance change detected.`);
        console.log(`   Possible reasons:`);
        console.log(`   - User q-score < threshold (need to check logs)`);
        console.log(`   - Event listener failed (check Laravel logs)`);
        console.log(`   - Node.js worker error`);
    }
}

testSessionFinishPayout().catch(console.error);
