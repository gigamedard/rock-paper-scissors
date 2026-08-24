import { JsonRpcProvider, Wallet, parseEther, formatEther, keccak256, toUtf8Bytes } from "ethers";
import fs from "fs";
import { FUJI_RPC_URL, GAME_WALLET_PK } from "./config.js";

// Configuration
// SECURITY: read owner key from config.js (env/Docker secret) instead of hardcoding.
const MAIN_WALLET_PK = GAME_WALLET_PK;
const BASE_SEED = "rock paper scissors simulation deterministic seed ";
const ACCOUNT_COUNT = 70; // Number of accounts to generate
const AMOUNT_PER_ACCOUNT = "2.0"; // AVAX to send (Gas + Bets + Security Margin)

async function main() {
    console.log("🚀 Preparing Large Scale Simulation...");

    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const funder = new Wallet(MAIN_WALLET_PK, provider);

    console.log(`   Funder Address: ${funder.address}`);

    // 1. Generate Accounts
    console.log(`\n1️⃣ Generating ${ACCOUNT_COUNT} accounts...`);
    const accounts = [];

    for (let i = 0; i < ACCOUNT_COUNT; i++) {
        // "Brain wallet" generation: keccak256(seed + index)
        const seed = BASE_SEED + i;
        const privateKey = keccak256(toUtf8Bytes(seed));
        const wallet = new Wallet(privateKey);

        accounts.push({
            index: i,
            address: wallet.address.toLowerCase(),
            privateKey: privateKey
        });
    }
    console.log(`   ✅ Generated ${accounts.length} accounts.`);

    // 2. Check Funder Balance
    console.log(`\n2️⃣ Checking Funder Balance...`);
    const funderBalance = await provider.getBalance(funder.address);
    const amountPerAccountWei = parseEther(AMOUNT_PER_ACCOUNT);
    const totalNeeded = amountPerAccountWei * BigInt(ACCOUNT_COUNT);

    console.log(`   Funder Balance: ${formatEther(funderBalance)} AVAX`);
    console.log(`   Total Needed:   ${formatEther(totalNeeded)} AVAX`);

    if (funderBalance < totalNeeded) {
        console.warn(`   ⚠️ WARNING: Funder might not have enough AVAX for all accounts!`);
        console.warn(`   Continuing anyway, but some transfers might fail.`);
    } else {
        console.log(`   ✅ Sufficient funds available.`);
    }

    // 3. Fund Accounts
    console.log(`\n3️⃣ Funding Accounts (${AMOUNT_PER_ACCOUNT} AVAX each)...`);
    let fundedCount = 0;
    let skippedCount = 0;
    let errorCount = 0;

    // Batch processing to avoid nonce issues? No, sequential is safer for scripts.
    for (const acc of accounts) {
        try {
            const currentBalance = await provider.getBalance(acc.address);

            // If balance is less than 50% of target, top up
            if (currentBalance < (amountPerAccountWei / 2n)) {
                process.stdout.write(`   Funding ${acc.address.substring(0, 10)}... `);

                const tx = await funder.sendTransaction({
                    to: acc.address,
                    value: amountPerAccountWei
                });
                await tx.wait();
                console.log(`✅ Sent (Tx: ${tx.hash.substring(0, 10)}...)`);
                fundedCount++;
            } else {
                // console.log(`   Skipping ${acc.address.substring(0, 10)} (Balance: ${formatEther(currentBalance)} AVAX)`);
                skippedCount++;
            }
        } catch (error) {
            console.error(`\n   ❌ Error funding ${acc.address}: ${error.message}`);
            errorCount++;
        }
    }

    console.log(`\n   Funding Summary:`);
    console.log(`   - Funded:  ${fundedCount}`);
    console.log(`   - Skipped: ${skippedCount} (Already had funds)`);
    console.log(`   - Errors:  ${errorCount}`);

    // 4. Save Accounts to File
    console.log(`\n4️⃣ Saving accounts to 'simulation_accounts.json'...`);
    fs.writeFileSync("simulation_accounts.json", JSON.stringify(accounts, null, 2));
    console.log(`   ✅ Saved ${accounts.length} accounts.`);

    console.log("\n🏁 Preparation Complete!");
}

main().catch(console.error);
