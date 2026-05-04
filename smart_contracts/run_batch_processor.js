import fetch from 'node-fetch';
import express from 'express';
import { LARAVEL_API_URL, INTERNAL_API_SECRET } from "./config.js";

const app = express();
const PORT = process.env.PROCESS_PORT || 3001; // Port for this worker
const INTERVAL = 10000; // Run every 10 seconds
const BASE_BET = "0.01"; // Default base bet, can be made dynamic

app.use(express.json());

console.log("🔄 Starting Hybrid Batch Processor...");
console.log(`   Target: ${LARAVEL_API_URL}`);
console.log(`   Internal Secret: ${INTERNAL_API_SECRET ? "LOADED" : "MISSING"}`);
console.log(`   Interval: ${INTERVAL}ms`);
console.log(`   Base Bet: ${BASE_BET}`);

/**
 * Orchestrates the full recycling -> matching cycle.
 * @param {string} source - 'TIMER' or 'HTTP'
 */
async function runHybridCycle(source) {
    const timestamp = new Date().toLocaleTimeString();
    console.log(`[${timestamp}] 🚀 Starting Cycle (${source})`);

    // Step 1: Recycle Available Users
    // This finds 'available' users and puts them into new pools
    try {
        console.log(`   Step 1: Recycling Users (internal-pools)...`);
        const poolResponse = await fetch(`${LARAVEL_API_URL}/internal/internal-pools`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify({ base_bet: BASE_BET })
        });

        const poolText = await poolResponse.text();
        let poolLog = `   Step 1 Status: ${poolResponse.status}`;
        try {
            const json = JSON.parse(poolText);
            if (json.message) poolLog += ` | ${json.message}`;
            if (json.created_pools > 0) poolLog += ` | Created: ${json.created_pools}`;
        } catch (e) { poolLog += ` | ${poolText.substring(0, 50)}...`; }
        console.log(poolLog);

    } catch (error) {
        console.error(`   ❌ Step 1 Error: ${error.message}`);
    }

    // Step 2: Process Batches (Matchmaking) - Round-robin across ALL bet tiers
    try {
        console.log(`   Step 2: Processing ALL bet tiers (batch-processing-all)...`);
        const batchResponse = await fetch(`${LARAVEL_API_URL}/internal/batch-processing-all`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify({})
        });

        const batchText = await batchResponse.text();
        let batchLog = `   Step 2 Status: ${batchResponse.status}`;
        try {
            const json = JSON.parse(batchText);
            if (json.message) batchLog += ` | ${json.message}`;
            if (json.tiers_processed) batchLog += ` | Tiers: ${json.tiers_processed}`;
            if (json.pools_processed) batchLog += ` | Pools: ${json.pools_processed}`;
        } catch (e) { batchLog += ` | ${batchText.substring(0, 50)}...`; }
        console.log(batchLog);

    } catch (error) {
        console.error(`   ❌ Step 2 Error: ${error.message}`);
    }

    console.log(`[${timestamp}] ✅ Cycle Complete (${source})\n`);
}

// --- HTTP ENDPOINT ---
app.post('/trigger-sync', async (req, res) => {
    console.log("⚡ HTTP Trigger Received");
    // Run cycle asynchronously (fire and forget from HTTP perspective, or await if we want to return results)
    // For simplicity and preventing timeout, we can await it.
    await runHybridCycle('HTTP');
    res.json({ success: true, message: "Hybrid cycle triggered" });
});

app.get('/health', (req, res) => res.json({ status: 'ok' }));

// --- STARTUP ---

// 1. Start Server
app.listen(PORT, () => {
    console.log(`📡 Hybrid Worker listening on port ${PORT}`);
});

// 2. Start Timer
setInterval(() => runHybridCycle('TIMER'), INTERVAL);

// 3. Run Initial
runHybridCycle('INIT');

