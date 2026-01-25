
import fs from 'fs';
import fetch from 'node-fetch';
import { Wallet } from 'ethers';

const VPS_URL = "https://srv1198092.hstgr.cloud/api";

// Helper function: Login and get token
async function loginAndGetToken(wallet) {
    try {
        // 1. Get message to sign
        let response = await fetch(`${VPS_URL}/wallet/generate-message`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, locale: 'en' })
        });

        if (!response.ok) throw new Error(`Generate Message Failed: ${response.status}`);
        let data = await response.json();
        const message = data.message;

        // 2. Sign message
        const signature = await wallet.signMessage(message);

        // 3. Verify signature to get token
        response = await fetch(`${VPS_URL}/wallet/verify-signature`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ wallet_address: wallet.address, signature: signature, locale: 'en' })
        });

        if (!response.ok) throw new Error(`Verify Signature Failed: ${response.status}`);
        data = await response.json();
        return data.token;
    } catch (e) {
        console.error(`Login failed for ${wallet.address}: ${e.message}`);
        return null;
    }
}

async function checkBalances() {
    console.log("🚀 Checking specific user balances on VPS...");

    // We'll check the first 5 accounts from simulation_accounts.json
    if (!fs.existsSync("simulation_accounts.json")) {
        console.error("simulation_accounts.json not found!");
        return;
    }

    const accounts = JSON.parse(fs.readFileSync("simulation_accounts.json")).slice(0, 5);

    for (const account of accounts) {
        const wallet = new Wallet(account.privateKey);
        const token = await loginAndGetToken(wallet);

        if (token) {
            // Fetch User Data (which contains balance)
            try {
                const response = await fetch(`${VPS_URL}/user`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const user = await response.json();
                    console.log(`✅ User ${wallet.address.substring(0, 8)}... | Balance: ${user.balance} | Token Balance: ${user.token_balance} | ID: ${user.id}`);
                } else {
                    console.error(`❌ Failed to fetch user data for ${wallet.address}: ${response.status}`);
                }
            } catch (e) {
                console.error(`Error fetching user data: ${e.message}`);
            }
        }
    }
}

checkBalances();
