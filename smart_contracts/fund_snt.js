// Distribue des SNT via fetch RPC brut + ethers Wallet pour signer
import { Wallet, parseEther } from "ethers";

const SNT_ADDRESS = "0xDc64a140Aa3E981100a9becA4E685f962f0cF6C9";
const RPC_URL = "http://127.0.0.1:8546";
const OWNER_PK = "***REMOVED***";
const CHAIN_ID = 31337;

const ACCOUNTS = [
    "0x70997970C51812dc3A010C7d01b50e0d17dc79C8",
    "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC",
    "0x90F79bf6EB2c4f870365E785982E1f101E93b906",
    "0x15d34AAf54267DB7D7c367839AAf71A00a2C6A65",
    "0x9965507D1a55bcC2695C58ba16FB37d819B0A4dc",
    "0x976EA74026E726554dB657fA54763abd0C3a0aa9",
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
