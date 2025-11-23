import fetch from 'node-fetch';
import { LARAVEL_API_URL, INTERNAL_API_SECRET } from "./config.js";

const INTERVAL = 5000; // Run every 5 seconds

console.log("🔄 Starting Batch Processor Simulation...");
console.log(`   Target: ${LARAVEL_API_URL}/internal/batch-processing`);
console.log(`   Interval: ${INTERVAL}ms`);

async function runBatch() {
    try {
        const response = await fetch(`${LARAVEL_API_URL}/internal/batch-processing`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            }
        });

        const status = response.status;
        const text = await response.text();

        let logMsg = `   [${new Date().toLocaleTimeString()}] Status: ${status}`;

        if (status === 200) {
            try {
                const json = JSON.parse(text);
                if (json.message) logMsg += ` | Msg: ${json.message}`;
            } catch (e) {
                logMsg += ` | Body: ${text.substring(0, 50)}...`;
            }
            console.log(logMsg);
        } else {
            console.log(`${logMsg} | Error: ${text.substring(0, 100)}`);
        }

    } catch (error) {
        console.error(`   ❌ Connection Error: ${error.message}`);
    }
}

// Run immediately then interval
runBatch();
setInterval(runBatch, INTERVAL);
