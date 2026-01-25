
import fs from 'fs';
import fetch from 'node-fetch';
import { Wallet } from 'ethers';
import { LARAVEL_API_URL } from './smart_contracts/config.js';

// Configuration
const POLL_INTERVAL = 3000; // 3 seconds
const DURATION = 60000; // Monitor for 60 seconds (adjust as needed)

async function loginAndGetToken(wallet) {
    try {
        let response = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, locale: 'en' })
        });
        if (!response.ok) return null;
        let data = await response.json();

        const signature = await wallet.signMessage(data.message);

        response = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, signature: signature, locale: 'en' })
        });
        if (!response.ok) return null;
        data = await response.json();
        return data.token;
    } catch (e) {
        console.error(`Login failed for ${wallet.address}: ${e.message}`);
        return null;
    }
}

async function monitor() {
    console.log("🚀 Starting Simulation Monitor...");

    if (!fs.existsSync("simulation_accounts.json") && !fs.existsSync("smart_contracts/simulation_accounts.json")) {
        console.error("simulation_accounts.json not found!");
        return;
    }
    const accountsPath = fs.existsSync("simulation_accounts.json") ? "simulation_accounts.json" : "smart_contracts/simulation_accounts.json";
    const accounts = JSON.parse(fs.readFileSync(accountsPath)).slice(0, 10); // Monitor first 10 users

    // Pre-login
    const users = [];
    console.log("🔑 Logging in users for monitoring...");
    for (const acc of accounts) {
        const wallet = new Wallet(acc.privateKey);
        const token = await loginAndGetToken(wallet);
        if (token) {
            users.push({ wallet, token, history: [] });
        }
    }
    console.log(`✅ Monitoring ${users.length} users.`);

    const startTime = Date.now();

    const interval = setInterval(async () => {
        const now = Date.now();
        if (now - startTime > DURATION) {
            clearInterval(interval);
            generateReport(users);
            return;
        }

        console.log(`\n--- Poll at ${new Date().toISOString()} ---`);
        for (const user of users) {
            try {
                const res = await fetch(`${LARAVEL_API_URL}/user`, {
                    headers: { 'Authorization': `Bearer ${user.token}`, 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    const status = data.status; // e.g., 'available', 'in_pool', 'waiting_for_result'
                    const poolId = data.pool_id;
                    const balance = data.balance;

                    // Detect Change
                    const lastState = user.history[user.history.length - 1];
                    if (!lastState || lastState.status !== status || lastState.poolId !== poolId) {
                        console.log(`User ${user.wallet.address.substring(0, 6)}... | Status: ${status} | Pool: ${poolId} | Bal: ${balance}`);
                        user.history.push({ timestamp: now, status, poolId, balance });
                    }
                }
            } catch (e) {
                console.error(`Error polling user: ${e.message}`);
            }
        }
    }, POLL_INTERVAL);
}

function generateReport(users) {
    console.log("\n\n📊 MONITORING REPORT 📊");
    let recycledCount = 0;
    let stuckCount = 0;

    users.forEach(u => {
        const addr = u.wallet.address.substring(0, 8);
        const pools = new Set(u.history.map(h => h.poolId).filter(p => p));
        console.log(`User ${addr}: Visited Pools: [${Array.from(pools).join(', ')}] | Transitions: ${u.history.length}`);

        if (pools.size > 1) recycledCount++;
        if (u.history.length === 1 && u.history[0].status === 'in_pool') stuckCount++;
    });

    console.log(`\nSummary:`);
    console.log(`- Recycled Users (Changed Pools): ${recycledCount}`);
    console.log(`- Potentially Stuck Users: ${stuckCount}`);
}

monitor();
