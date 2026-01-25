
import fetch from 'node-fetch';
import { LARAVEL_API_URL, INTERNAL_API_SECRET } from "./config.js";

async function run() {
    console.log(`Using URL: ${LARAVEL_API_URL}/internal/payout`);
    console.log(`Using Secret: ${INTERNAL_API_SECRET}`);

    try {
        const response = await fetch(`${LARAVEL_API_URL}/internal/payout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify({
                wallet_address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
                amount: "0.001"
            })
        });

        console.log(`Status: ${response.status}`);
        const text = await response.text();
        console.log(`Raw Body: ${text}`);
    } catch (e) {
        console.error(e);
    }
}

run();
