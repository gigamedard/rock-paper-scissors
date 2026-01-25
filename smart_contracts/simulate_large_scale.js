import { JsonRpcProvider, Wallet, Contract, parseEther, formatEther } from "ethers";
import { randomInt } from "crypto";
import fs from "fs";
import fetch from 'node-fetch';
import {
    LARAVEL_API_URL,
    FUJI_RPC_URL,
    pinata,
    contracts
} from "./config.js";

// Configuration
const BATCH_SIZE = 1; // Process 1 user at a time for local stability
const DELAY_BETWEEN_BATCHES = 2000; // 2 seconds
const BASE_BET_ETH = "0.001";
const MOVES_OPTIONS = ["rock", "paper", "scissors"];

async function main() {
    console.log("🚀 Starting Large Scale Simulation...");
    console.log("Global Config URL:", LARAVEL_API_URL);

    // 1. Load Accounts
    if (!fs.existsSync("simulation_accounts.json")) {
        console.error("❌ Error: simulation_accounts.json not found. Run prepare_simulation.js first.");
        return;
    }
    const accounts = JSON.parse(fs.readFileSync("simulation_accounts.json"));
    console.log(`   Loaded ${accounts.length} accounts.`);

    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);

    // 2. Process in Batches
    for (let i = 0; i < accounts.length; i += BATCH_SIZE) {
        const batch = accounts.slice(i, i + BATCH_SIZE);
        console.log(`\n📦 Processing Batch ${Math.floor(i / BATCH_SIZE) + 1} (Users ${i + 1}-${Math.min(i + BATCH_SIZE, accounts.length)})...`);

        await Promise.all(batch.map(account => processUser(account, provider, gameContract)));

        if (i + BATCH_SIZE < accounts.length) {
            console.log(`   ⏳ Waiting ${DELAY_BETWEEN_BATCHES}ms...`);
            await new Promise(r => setTimeout(r, DELAY_BETWEEN_BATCHES));
        }
    }

    console.log("\n🏁 Simulation Requests Completed!");
}

async function processUser(account, provider, gameContract) {
    const wallet = new Wallet(account.privateKey, provider);
    const shortAddr = wallet.address.substring(0, 8) + "...";

    try {
        // A. Login
        // console.log(`   [${shortAddr}] Logging in...`);
        let response = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, locale: 'en' })
        });
        let data = await response.json();
        if (!data.message) throw new Error("Failed to get login message");

        const signature = await wallet.signMessage(data.message);

        response = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, signature: signature, locale: 'en' })
        });
        data = await response.json();
        const token = data.token;
        const userId = data.user.id;

        // B. Generate Random Moves (Using crypto for better randomness)
        const moves = [
            MOVES_OPTIONS[randomInt(0, 3)],
            MOVES_OPTIONS[randomInt(0, 3)],
            MOVES_OPTIONS[randomInt(0, 3)]
        ];

        // Log moves to verify randomness
        // console.log(`   [${shortAddr}] Moves: ${moves.join(', ')}`);

        // C. Upload to Pinata
        // console.log(`   [${shortAddr}] Uploading moves: ${moves.join(', ')}`);
        const pinataData = {
            pinataContent: { wallet_address: wallet.address, moves: moves, timestamp: new Date().toISOString() },
            pinataMetadata: { name: `Sim-${wallet.address}-${Date.now()}` }
        };

        // response = await fetch('https://api.pinata.cloud/pinning/pinJSONToIPFS', {
        //     method: 'POST',
        //     headers: {
        //         'Content-Type': 'application/json',
        //         'pinata_api_key': pinata.PINATA_API_KEY,
        //         'pinata_secret_api_key': pinata.PINATA_SECRET
        //     },
        //     body: JSON.stringify(pinataData)
        // });
        // const pinataResult = await response.json();
        // const cid = pinataResult.IpfsHash;
        // if (!cid) throw new Error("Pinata upload failed");

        const cid = "QmDummyCidForSimulationBypassPinataRateLimit" + Date.now(); // Mock CID

        // D. Submit to Backend
        response = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                user_id: userId,
                pre_moves: moves,
                cid: cid,
                bet_amount: BASE_BET_ETH
            })
        });
        if (!response.ok) throw new Error(`Backend submission failed: ${response.status}`);

        // E. Submit to Blockchain
        const baseBetWei = parseEther(BASE_BET_ETH);
        const contractWithSigner = gameContract.connect(wallet);

        // Check if already in a pool? (Optional, but good for robustness)
        // For now, assume fresh start or multiple joins allowed (if logic permits)

        const tx = await contractWithSigner.submitPremoveCID(baseBetWei, cid, { value: baseBetWei }); // Assuming 1x deposit for simplicity

        console.log(`   ✅ [${shortAddr}] Joined! Tx: ${tx.hash.substring(0, 10)}...`);

        // We don't wait for tx confirmation here to speed up the simulation loop
        // await tx.wait(); 

    } catch (error) {
        console.error(`   ❌ [${shortAddr}] Error: ${error.message}`);
    }
}

main().catch(console.error);
