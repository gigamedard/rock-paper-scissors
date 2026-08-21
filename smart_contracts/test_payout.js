// Test script to verify contract payout functionality
import { JsonRpcProvider, Wallet, Contract, parseEther, formatEther } from "ethers";
import {
    FUJI_RPC_URL,
    GAME_WALLET_PK,
    contracts
} from "./config.js";

// Test user to receive payout
const testUser = {
    address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
    privateKey: "400e1b043832260518588f42125acf9b974f6365f78b8ab6899eefe350b228b4"
};

async function testPayout() {
    console.log("🧪 Testing Smart Contract Payout Functionality\n");

    // Setup provider and wallets
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const ownerWallet = new Wallet(GAME_WALLET_PK, provider);
    const userWallet = new Wallet(testUser.privateKey, provider);

    // Connect to contract
    const contract = new Contract(contracts.game.address, contracts.game.abi, ownerWallet);

    console.log("📊 Initial State:");
    console.log(`   Contract Address: ${contracts.game.address}`);
    console.log(`   Owner Address: ${ownerWallet.address}`);
    console.log(`   User Address: ${testUser.address}\n`);

    // 1. Check balances BEFORE payout
    console.log("1️⃣ Checking balances BEFORE payout...");
    const contractBalanceBefore = await provider.getBalance(contracts.game.address);
    const userBalanceBefore = await provider.getBalance(testUser.address);

    console.log(`   Contract Balance: ${formatEther(contractBalanceBefore)} AVAX`);
    console.log(`   User Balance: ${formatEther(userBalanceBefore)} AVAX\n`);

    // 2. Calculate payout amount (send 0.001 AVAX)
    const payoutAmount = parseEther("0.001");
    console.log(`2️⃣ Payout Amount: ${formatEther(payoutAmount)} AVAX\n`);

    // 3. Check if contract has enough balance
    if (contractBalanceBefore < payoutAmount) {
        console.error("❌ ERROR: Contract doesn't have enough balance!");
        console.error(`   Required: ${formatEther(payoutAmount)} AVAX`);
        console.error(`   Available: ${formatEther(contractBalanceBefore)} AVAX`);
        return;
    }

    // 4. Send payout
    console.log("3️⃣ Sending payout from contract...");
    try {
        const tx = await contract.payOut(testUser.address, payoutAmount);
        console.log(`   📡 Transaction sent: ${tx.hash}`);
        console.log(`   ⏳ Waiting for confirmation...`);

        const receipt = await tx.wait();
        console.log(`   ✅ Transaction confirmed in block ${receipt.blockNumber}\n`);

    } catch (error) {
        console.error("❌ ERROR sending payout:", error.message);
        return;
    }

    // 5. Wait a moment for blockchain to update
    console.log("4️⃣ Waiting for blockchain to update...");
    await new Promise(resolve => setTimeout(resolve, 3000));

    // 6. Check balances AFTER payout
    console.log("5️⃣ Checking balances AFTER payout...");
    const contractBalanceAfter = await provider.getBalance(contracts.game.address);
    const userBalanceAfter = await provider.getBalance(testUser.address);

    console.log(`   Contract Balance: ${formatEther(contractBalanceAfter)} AVAX`);
    console.log(`   User Balance: ${formatEther(userBalanceAfter)} AVAX\n`);

    // 7. Calculate differences
    console.log("6️⃣ Balance Changes:");
    const contractDiff = contractBalanceBefore - contractBalanceAfter;
    const userDiff = userBalanceAfter - userBalanceBefore;

    console.log(`   Contract: -${formatEther(contractDiff)} AVAX`);
    console.log(`   User: +${formatEther(userDiff)} AVAX\n`);

    // 8. Verify payout
    console.log("7️⃣ Verification:");
    const expectedContractBalance = contractBalanceBefore - payoutAmount;
    const expectedUserBalance = userBalanceBefore + payoutAmount;

    const contractCorrect = contractBalanceAfter === expectedContractBalance;
    const userCorrect = userBalanceAfter === expectedUserBalance;

    if (contractCorrect && userCorrect) {
        console.log("   ✅ Payout SUCCESSFUL!");
        console.log(`   ✅ Contract sent exactly ${formatEther(payoutAmount)} AVAX`);
        console.log(`   ✅ User received exactly ${formatEther(payoutAmount)} AVAX`);
    } else {
        console.log("   ⚠️ Payout completed but amounts don't match exactly");
        console.log(`   Expected contract: ${formatEther(expectedContractBalance)} AVAX`);
        console.log(`   Actual contract: ${formatEther(contractBalanceAfter)} AVAX`);
        console.log(`   Expected user: ${formatEther(expectedUserBalance)} AVAX`);
        console.log(`   Actual user: ${formatEther(userBalanceAfter)} AVAX`);
    }

    console.log("\n✅ Payout test completed!");
}

testPayout().catch(error => {
    console.error("\n❌ Fatal error:", error);
    process.exit(1);
});
