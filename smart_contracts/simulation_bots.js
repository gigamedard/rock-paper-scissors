import { JsonRpcProvider, Wallet, Contract, parseEther, ethers } from "ethers";
import fetch from "node-fetch";
import {
    LARAVEL_API_URL,
    LOCAL_HARDHAT_URL,
    contracts,
} from "./config.js";

// --- Configuration ---
const BASE_BET = "0.01"; // ETH
const BOT_INTERVAL_MS = 1000; // 0.5 second delay between bots
const MAX_RETRIES = 3;

// Game contract setup
const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);

/**
 * Generate highly randomized pre-moves to avoid excessive draws
 */
function generateRandomPreMoves(count = 10) {
    const choices = ['rock', 'paper', 'scissors'];
    const moves = [];
    for (let i = 0; i < count; i++) {
        // Pseudo-random weighting to avoid everybody just playing rock
        const rand = Math.random();
        let choice;
        if (rand < 0.33) choice = 'rock';
        else if (rand < 0.66) choice = 'paper';
        else choice = 'scissors';

        moves.push(choice);
    }
    return moves;
}

/**
 * Main function to simulate a single bot player from start to finish
 */
async function simulateBot(botIndex) {
    console.log(`\n===========================================`);
    console.log(`🤖 Starting Bot #${botIndex + 1}`);

    // We use index 0 to 4 (Addresses #1 to #5) as requested by the user for real-time app behavior analysis.
    const hdNode = ethers.HDNodeWallet.fromPhrase(
        "test test test test test test test test test test test junk",
        undefined,
        `m/44'/60'/0'/0/${botIndex}`
    );
    const wallet = new Wallet(hdNode.privateKey, provider);
    const address = wallet.address.toLowerCase();
    console.log(`   [Bot ${botIndex + 1}] Wallet Address: ${address}`);

    // 2. Auth Flow (Sign verification message)
    console.log(`   [Bot ${botIndex + 1}] Authenticating with Laravel...`);

    // Step A: Request signature message
    const msgRes = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ wallet_address: address })
    });
    const msgData = await msgRes.json();
    if (!msgData.message) throw new Error(`Failed to generate message for bot ${botIndex + 1}: ` + JSON.stringify(msgData));

    // Step B: Sign message
    const signature = await wallet.signMessage(msgData.message);

    // Step C: Verify signature to get token
    const verifyRes = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({
            wallet_address: address,
            signature: signature,
            message: msgData.message
        })
    });
    const tokenData = await verifyRes.json();
    if (!tokenData.token || !tokenData.user) throw new Error(`Failed to get token or user for bot ${botIndex + 1}: ` + JSON.stringify(tokenData));

    const authToken = tokenData.token;
    const userId = tokenData.user.id;
    console.log(`   [Bot ${botIndex + 1}] Authentication Successful. User ID: ${userId}`);

    // 3. Define Random Pre-Moves to avoid draw loops
    const moves = generateRandomPreMoves(10);

    console.log(`   [Bot ${botIndex + 1}] Submitting Pre-Moves: [${moves.slice(0, 3).join(', ')}...]`);

    // Step D: Upload to IPFS via Laravel API proxy
    console.log(`   [Bot ${botIndex + 1}] Uploading pre-moves to IPFS...`);
    const pinataData = {
        user_id: userId,
        wallet_address: address,
        moves: moves,
        timestamp: new Date().toISOString()
    };

    const ipfsRes = await fetch(`${LARAVEL_API_URL}/ipfs/upload`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "Authorization": `Bearer ${authToken}` },
        body: JSON.stringify({ data: pinataData })
    });
    const ipfsData = await ipfsRes.json();
    if (!ipfsData.IpfsHash) throw new Error(`Failed to upload IPFS for bot ${botIndex + 1}: ` + JSON.stringify(ipfsData));

    const preMoveCid = ipfsData.IpfsHash;
    console.log(`   [Bot ${botIndex + 1}] Pre-Moves IPFS CID received: ${preMoveCid}`);

    // Step E: Store Pre-moves in Laravel DB
    console.log(`   [Bot ${botIndex + 1}] Storing pre-moves in Backend...`);
    const movesRes = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${authToken}`
        },
        body: JSON.stringify({
            user_id: userId,
            pre_moves: moves,
            cid: preMoveCid,
            bet_amount: BASE_BET
        })
    });

    if (!movesRes.ok) {
        const errText = await movesRes.text();
        throw new Error(`Failed to store pre-moves for bot ${botIndex + 1}. Status: ${movesRes.status}. Body: ${errText}`);
    }
    console.log(`   [Bot ${botIndex + 1}] Backend stored pre-moves correctly!`);

    // 4. Smart Contract Blockchain Interaction (submitPremoveCID)
    console.log(`   [Bot ${botIndex + 1}] Calling Smart Contract submitPremoveCID...`);

    // Fetch dynamic config from Laravel
    let config;
    try {
        const configRes = await fetch(`${LARAVEL_API_URL}/artefacts`);
        if (!configRes.ok) {
            console.warn(`   [Bot ${botIndex + 1}] Warning: Failed to fetch artefacts (${configRes.status}). Using defaults.`);
            config = { security_coefficient: 1000, smart_contract_fee_percentage: 2.5 };
        } else {
            config = await configRes.json();
        }
    } catch (e) {
        console.warn(`   [Bot ${botIndex + 1}] Warning: Error fetching artefacts (${e.message}). Using defaults.`);
        config = { security_coefficient: 1000, smart_contract_fee_percentage: 2.5 };
    }
    
    const securityCoefficient = config.security_coefficient || 1000;
    const feePercentage = config.smart_contract_fee_percentage || 2.5;

    const gameContract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    // ALIGNMENT WITH HUMAN UI: (Base Bet * Security Coefficient) + 2.5% Fee
    const stakeWei = parseEther((parseFloat(BASE_BET) * securityCoefficient).toFixed(18));
    const feeWei = (stakeWei * BigInt(Math.round(feePercentage * 100))) / 10000n; // Use feePercentage from config
    const amountToSendWei = stakeWei + feeWei;

    const baseBetWei = parseEther(BASE_BET);

    const tx = await gameContract.submitPremoveCID(baseBetWei, preMoveCid, {
        value: amountToSendWei,
        gasLimit: 500000
    });

    console.log(`   [Bot ${botIndex + 1}] TX Sent: ${tx.hash}`);
    await tx.wait();
    console.log(`   [Bot ${botIndex + 1}] TX Confirmed! Deposited ${ethers.formatEther(amountToSendWei)} ETH.`);

    return {
        address: address,
        cid: preMoveCid
    };
}




/**
 * Orchestrator
 */
async function main() {
    const args = process.argv.slice(2);
    const numBots = parseInt(args[0]) || 4;
    const startIndex = parseInt(args[1]) || 0;

    console.log(`\n===========================================`);
    console.log(`🎮 E2E Functional Test Simulation Started`);
    console.log(`🎯 Target Bots: ${numBots}`);
    console.log(`📍 Starting from Index: ${startIndex}`);
    console.log(`⏱️ Interval: ${BOT_INTERVAL_MS}ms (2 seconds)`);
    console.log(`===========================================\n`);

    const activeBots = [];

    for (let i = startIndex; i < startIndex + numBots; i++) {
        try {
            const botData = await simulateBot(i);
            activeBots.push(botData);

            if (i < startIndex + numBots - 1) {
                console.log(`⏳ Waiting ${BOT_INTERVAL_MS / 1000} seconds before next bot...`);
                await new Promise(resolve => setTimeout(resolve, BOT_INTERVAL_MS));
            }
        } catch (error) {
            console.error(`❌ Error in Bot Index ${i}:`, error.message);
        }
    }

    console.log(`\n===========================================`);
    console.log(`🎉 Simulation Phase 1 Complete!`);
    console.log(`✅ ${activeBots.length}/${numBots} Bots successfully joined the Smart Contract Pool.`);
    console.log(`👇 NOW IT IS YOUR TURN 👇`);
    console.log(`Open the frontend index.html and join the game manually.`);
    console.log(`You will be player #${activeBots.length + 1} and should trigger the pool emission.`);
    console.log(`Watch the Laravel logs (tail -f storage/logs/laravel.log) to see the money transfers!`);
    console.log(`===========================================\n`);
}

main().catch(console.error);
