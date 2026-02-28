import { Wallet } from "ethers";
import axios from "axios";
import 'dotenv/config';

const BACKEND_URL = "http://127.0.0.1:8000/api";
const botKey = process.env.SIMULATION_BOT_KEYS.split(',')[0];
const bot = new Wallet(botKey);

async function testAuth() {
    try {
        console.log(`Testing auth for ${bot.address}...`);

        console.log("1. Requesting challenge...");
        const challengeRes = await axios.post(`${BACKEND_URL}/wallet/generate-message`, {
            wallet_address: bot.address,
            locale: 'en'
        });
        const message = challengeRes.data.message;
        console.log("Challenge:", message);

        console.log("2. Signing message...");
        const signature = await bot.signMessage(message);
        console.log("Signature:", signature);

        console.log("3. Verifying signature...");
        const verifyRes = await axios.post(`${BACKEND_URL}/wallet/verify-signature`, {
            wallet_address: bot.address,
            signature: signature
        });

        console.log("Success! Token:", verifyRes.data.token);
    } catch (e) {
        console.error("Auth failed!");
        if (e.response) {
            console.error("Status:", e.response.status);
            console.error("Data:", JSON.stringify(e.response.data));
        } else {
            console.error("Error Message:", e.message);
            console.error("Code:", e.code);
        }
    }
}

testAuth();
