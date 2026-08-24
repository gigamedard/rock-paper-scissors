// Distribue des SNT via fetch RPC brut + ethers Wallet pour signer
import { Wallet, parseEther } from "ethers";
import { contracts } from "./config.js";

// Adresse SNT lue dynamiquement depuis config.js (mis à jour par full_deploy.js à chaque déploiement)
const SNT_ADDRESS = contracts.snt.address;
const RPC_URL = "http://127.0.0.1:8546";
// SECURITY: The SNT tokens are minted to Hardhat #0 (the deployer) at deployment.
// This is a TEST-ONLY script that uses the well-known Hardhat #0 key to distribute
// SNT to test accounts. In production, the deployer would use their own key.
// The Hardhat #0 key is a publicly-known test key — accepted residual risk for local dev.
const OWNER_PK = "0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80";
const CHAIN_ID = 31337;

// Uniquement les comptes #1 et #2 (réservés aux tests utilisateur)
const ACCOUNTS = [
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8", // Account #1
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC", // Account #2
];

const AMOUNT = "100"; // 100 SNT par compte (owner a 1000)

async function rpc(method, params) {
    const res = await fetch(RPC_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ jsonrpc: '2.0', method, params, id: 1 })
    });
    const data = await res.json();
    if (data.error) throw new Error(JSON.stringify(data.error));
    return data.result;
}

function padLeft(hex) {
    return hex.replace('0x', '').padStart(64, '0');
}

async function balanceOf(addr) {
    const data = '0x70a08231' + padLeft(addr);
    const result = await rpc('eth_call', [{ to: SNT_ADDRESS, data }, 'latest']);
    return BigInt(result);
}

async function main() {
    const ownerAddr = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
    console.log(`Owner: ${ownerAddr}`);
    const ownerBal = await balanceOf(ownerAddr);
    console.log(`Owner SNT balance: ${Number(ownerBal) / 1e18}`);

    const wallet = new Wallet(OWNER_PK);

    for (const addr of ACCOUNTS) {
        const balBefore = await balanceOf(addr);
        if (balBefore > 0n) {
            console.log(`${addr}: already has ${Number(balBefore) / 1e18} SNT, skipping`);
            continue;
        }
        console.log(`Transferring ${AMOUNT} SNT to ${addr}...`);

        const transferData = '0xa9059cbb' + padLeft(addr) + padLeft(parseEther(AMOUNT).toString(16).replace('0x', ''));
        const nonce = await rpc('eth_getTransactionCount', [ownerAddr, 'latest']);
        const gasPrice = await rpc('eth_gasPrice', []);

        const tx = {
            from: ownerAddr,
            to: SNT_ADDRESS,
            data: transferData,
            gasLimit: '0x' + (100000).toString(16),
            gasPrice: gasPrice,
            nonce: nonce,
            chainId: CHAIN_ID,
            type: 0,
        };

        const signedTx = await wallet.signTransaction(tx);
        const txHash = await rpc('eth_sendRawTransaction', [signedTx]);
        console.log(`  Tx: ${txHash}`);

        let receipt = null;
        for (let i = 0; i < 30; i++) {
            await new Promise(r => setTimeout(r, 1000));
            receipt = await rpc('eth_getTransactionReceipt', [txHash]);
            if (receipt) break;
        }
        if (!receipt) {
            console.log(`  ⚠️ Pas de receipt après 30s`);
            continue;
        }

        const balAfter = await balanceOf(addr);
        console.log(`  Done. Balance: ${Number(balAfter) / 1e18} SNT`);
    }

    console.log("\nAll accounts funded!");
}

main().catch(console.error);
