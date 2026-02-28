import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import axios from "axios";
import { contracts, FUJI_RPC_URL } from "./config.js";
import 'dotenv/config';

const BACKEND_URL = "http://127.0.0.1:8000/api";
const BOT_KEYS = (process.env.SIMULATION_BOT_KEYS || "").split(",").filter(k => k);
const POLL_INTERVAL_MS = 10000;

if (BOT_KEYS.length === 0) {
    console.error("No SIMULATION_BOT_KEYS found in .env");
    process.exit(1);
}

const provider = new JsonRpcProvider(FUJI_RPC_URL);
// Create wallet instances
const bots = BOT_KEYS.map(key => new Wallet(key, provider));
const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

// Store API tokens and user details
const authTokens = {};

async function authenticateBot(bot) {
    if (authTokens[bot.address]) return authTokens[bot.address];

    try {
        console.log(`[Bot ${bot.address}] Authenticating with backend...`);
        // 1. Get challenge
        const challengeRes = await axios.post(`${BACKEND_URL}/wallet/generate-message`, {
            wallet_address: bot.address,
            locale: 'en'
        });
        const message = challengeRes.data.message;

        // 2. Sign challenge
        const signature = await bot.signMessage(message);

        // 3. Verify signature
        const verifyRes = await axios.post(`${BACKEND_URL}/wallet/verify-signature`, {
            wallet_address: bot.address,
            signature: signature
        });

        const token = verifyRes.data.token;
        const user = verifyRes.data.user;
        authTokens[bot.address] = { token, user };
        console.log(`[Bot ${bot.address}] Authenticated successfully. User ID: ${user.id}`);
        return authTokens[bot.address];
    } catch (e) {
        console.error(`[Bot ${bot.address}] Auth failed:`, e.response?.data || e.message);
        return null;
    }
}

async function prepareAndSubmitBot(bot, baseBet) {
    try {
        const authData = await authenticateBot(bot);
        if (!authData) return;
        const { token, user } = authData;

        console.log(`[Bot ${bot.address}] Preparing 3 random moves...`);
        // Generate 3 random moves as strings to match frontend (rock, paper, scissors)
        const possibleMoves = ['rock', 'paper', 'scissors'];
        const moves = [
            possibleMoves[Math.floor(Math.random() * 3)],
            possibleMoves[Math.floor(Math.random() * 3)],
            possibleMoves[Math.floor(Math.random() * 3)]
        ];

        // Ensure bot is registered for autoplay or just let the pre-moves endpoint do it.
        // Actually, the frontend calls /ipfs/upload and then /user/pre-moves.

        console.log(`[Bot ${bot.address}] Uploading moves to IPFS via backend proxy...`);
        const pinataData = {
            user_id: user.id,
            wallet_address: bot.address,
            moves: moves,
            timestamp: new Date().toISOString()
        };

        const ipfsRes = await axios.post(`${BACKEND_URL}/ipfs/upload`, {
            data: pinataData
        }, {
            headers: { Authorization: `Bearer ${token}` }
        });
        const cid = ipfsRes.data.IpfsHash;

        console.log(`[Bot ${bot.address}] CID received: ${cid}. Registering pre-moves...`);
        const preMovesRes = await axios.post(`${BACKEND_URL}/user/pre-moves`, {
            user_id: user.id,
            pre_moves: moves,
            bet_amount: Number(baseBet) / 1e18, // Convert wei back to AVAX for backend
            cid: cid
        }, {
            headers: { Authorization: `Bearer ${token}` }
        });

        if (preMovesRes.data.message === 'Pre-moves stored successfully!') {
            console.log(`[Bot ${bot.address}] Backend registered successfully. Submitting to Smart Contract...`);

            // Connect to contract with bot wallet
            const botContract = contract.connect(bot);

            // Security coefficient logic (from app defaults)
            const requiredBalance = (baseBet * 1n); // Assuming coefficient is 1 now
            const feeBasisPoints = 250n;
            const msgValue = (requiredBalance * (10000n + feeBasisPoints)) / 10000n;

            console.log(`[Bot ${bot.address}] Depositing ${msgValue.toString()} wei to match ${baseBet.toString()} wei pool.`);

            const tx = await botContract.submitPremoveCID(baseBet, cid, { value: msgValue });
            console.log(`[Bot ${bot.address}] TX Sent: ${tx.hash}. Waiting for confirmation...`);
            await tx.wait();
            console.log(`[Bot ${bot.address}] TX Confirmed! Bot has officially joined the pool.`);
        } else {
            console.error(`[Bot ${bot.address}] Pre-moves registration failed:`, preMovesRes.data);
        }

    } catch (e) {
        console.error(`[Bot ${bot.address}] Execution failed:`, e.response?.data || e.message);
    }
}

async function monitorPools() {
    try {
        // Find a popular base bet from the contract logic. Typically 0.05 AVAX is base.
        const baseBetWei = parseEther("0.05");

        const users = await contract.getPoolUsers(baseBetWei);
        console.log(`[Engine] Polled Pool(0.05 AVAX) -> Users waiting: ${users.length}`);

        // If there is at least 1 user waiting, but the pool is not full (max Size is usually 2 or 5).
        // For simplicity, if > 0 users, let's just inject 1 bot to help fill it (if bot isn't already in it)
        if (users.length > 0) {
            // Find a bot that isn't already in the pool
            const availableBots = bots.filter(b => !users.includes(b.address));

            if (availableBots.length > 0) {
                // Find first funded bot
                let executingBot = null;
                for (let bot of availableBots) {
                    const bal = await provider.getBalance(bot.address);
                    if (bal >= parseEther("0.06")) {
                        executingBot = bot;
                        break;
                    } else {
                        console.error(`[Engine] Bot ${bot.address} lacks funds (Bal: ${bal.toString()}). Skipping...`);
                    }
                }

                if (executingBot) {
                    console.log(`[Engine] Detecting sparse pool! Deploying bot ${executingBot.address}...`);
                    // We execute asynchronously so we don't block the loop
                    prepareAndSubmitBot(executingBot, baseBetWei);
                } else {
                    console.error(`[Engine] No bots with sufficient funds available! Please run fund_bots.js`);
                }
            } else {
                console.log(`[Engine] Pool has users, but no available bots left to deploy.`);
            }
        }
    } catch (e) {
        console.error("[Engine] Polling error:", e);
    }
}

console.log("==========================================");
console.log("   🚀 SEMI-DEMO SIMULATION ENGINE STARTED ");
console.log("==========================================");
console.log(`Tracking ${bots.length} Bot Wallets.`);
bots.forEach(b => console.log(`- ${b.address}`));

// Read the user ID parameter from backend later if needed, but the JWT auth handled it smoothly.
setInterval(monitorPools, POLL_INTERVAL_MS);
monitorPools(); // Initial call
