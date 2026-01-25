
import fs from 'fs';
import { JsonRpcProvider, formatEther, Wallet } from 'ethers';
import { FUJI_RPC_URL } from './smart_contracts/config.js';

async function checkBlockchainBalances() {
    console.log(`🚀 Checking AVAX balances on Blockchain (${FUJI_RPC_URL})...`);

    if (!fs.existsSync("smart_contracts/simulation_accounts.json")) {
        // Try root if not in smart_contracts
        if (!fs.existsSync("simulation_accounts.json")) {
            console.error("simulation_accounts.json not found!");
            return;
        }
    }

    // Load accounts (handle both paths)
    let accountsPath = "simulation_accounts.json";
    if (fs.existsSync("smart_contracts/simulation_accounts.json")) accountsPath = "smart_contracts/simulation_accounts.json";

    const accounts = JSON.parse(fs.readFileSync(accountsPath)).slice(0, 10); // Check first 10
    const provider = new JsonRpcProvider(FUJI_RPC_URL);

    console.log(`Checking ${accounts.length} accounts...`);

    for (const account of accounts) {
        try {
            const balanceWei = await provider.getBalance(account.address);
            const balanceEth = formatEther(balanceWei);
            console.log(`Address: ${account.address} | Balance: ${balanceEth} AVAX`);
        } catch (error) {
            console.error(`Error querying ${account.address}: ${error.message}`);
        }
    }
}

checkBlockchainBalances();
