import { execSync } from "child_process";

const indices = [0, 2, 3, 4];

async function run() {
    for (const i of indices) {
        console.log(`Starting Bot with index ${i}...`);
        try {
            // We need a way to run simulateBot for a specific index.
            // I'll just modify simulation_bots.js to accept an index range.
        } catch (e) {
            console.error(e);
        }
    }
}
