import { JsonRpcProvider, Wallet, parseEther } from "ethers";
import { FUJI_RPC_URL, GAME_WALLET_PK } from "./config.js";
import 'dotenv/config';

const BOT_KEYS = (process.env.SIMULATION_BOT_KEYS || "").split(",").filter(k => k);

async function fundBots() {
    if (BOT_KEYS.length === 0) {
        console.error("No SIMULATION_BOT_KEYS found in .env");
        process.exit(1);
    }

    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const mainWallet = new Wallet(GAME_WALLET_PK, provider);
    const mainBalance = await provider.getBalance(mainWallet.address);

    console.log(`Main Wallet: ${mainWallet.address} | Balance: ${mainBalance.toString()} wei`);

    for (let key of BOT_KEYS) {
        const bot = new Wallet(key, provider);
        const botBal = await provider.getBalance(bot.address);

        console.log(`Bot Wallet: ${bot.address} | Balance: ${botBal.toString()} wei`);

        // If bot has less than 0.1 AVAX, fund it with 0.2 AVAX
        if (botBal < parseEther("0.1")) {
            console.log(`Funding ${bot.address} with 0.2 AVAX...`);
            const tx = await mainWallet.sendTransaction({
                to: bot.address,
                value: parseEther("0.2")
            });
            console.log(`TX Sent: ${tx.hash}`);
            await tx.wait();
            console.log(`Funding confirmed for ${bot.address}.`);
        } else {
            console.log(`Bot ${bot.address} already has sufficient funds.`);
        }
    }

    console.log("All bots funded successfully.");
}

fundBots().catch(console.error);
