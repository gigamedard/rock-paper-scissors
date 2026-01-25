
import fetch from 'node-fetch';
import { LARAVEL_API_URL, INTERNAL_API_SECRET } from './smart_contracts/config.js';

const INTERNAL_URL = LARAVEL_API_URL.replace('/api', '/api/internal');
const BASE_BET = 0.001; // Match the simulation base bet
const INTERVAL = 5000; // 5 seconds

async function triggerEndpoint(endpoint, payload) {
    try {
        const url = `${INTERNAL_URL}/${endpoint}`;
        // console.log(`Triggering ${endpoint}...`);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify(payload)
        });

        if (response.ok) {
            const data = await response.json();
            // Only log significant events
            if (data.created_pools > 0 || data.processed_batches > 0 || (data.message && !data.message.includes('No pools'))) {
                console.log(`✅ [${endpoint}]`, JSON.stringify(data));
            }
        } else {
            const txt = await response.text();
            console.error(`❌ [${endpoint}] Failed ${response.status}: ${txt}`);
        }
    } catch (e) {
        console.error(`❌ [${endpoint}] Error: ${e.message}`);
    }
}

async function runWorker() {
    console.log(`🚀 Starting Backend Worker Simulation...`);
    console.log(`Target: ${INTERNAL_URL}`);

    setInterval(async () => {
        // 1. Group users into pools
        await triggerEndpoint('internal-pools', { base_bet: BASE_BET });

        // 2. Process batches (matches)
        await triggerEndpoint('batch-processing', { base_bet: BASE_BET });

    }, INTERVAL);
}

runWorker();
