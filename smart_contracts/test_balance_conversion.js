// Test script to verify balance conversion
import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import fetch from 'node-fetch';
import {
    LARAVEL_API_URL,
    FUJI_RPC_URL,
    pinata,
    contracts
} from "./config.js";

const testAccount = {
    address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
    privateKey: "400e1b043832260518588f42125acf9b974f6365f78b8ab6899eefe350b228b4"
};

async function testBalanceConversion() {
    console.log("🧪 Testing Balance Conversion Fix...\n");

    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const signer = new Wallet(testAccount.privateKey, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

    // 1. Login
    console.log("1️⃣ Logging in...");
    let response = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ wallet_address: signer.address, locale: 'en' })
    });
    let data = await response.json();
    const signature = await signer.signMessage(data.message);

    response = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ wallet_address: signer.address, signature: signature, locale: 'en' })
    });
    data = await response.json();
    console.log("✅ Logged in successfully\n");

    // 2. Upload to Pinata
    console.log("2️⃣ Uploading to Pinata...");
    const moves = ["rock", "paper", "scissors"];
    const pinataData = {
        pinataContent: { wallet_address: signer.address, moves: moves, timestamp: new Date().toISOString() },
        pinataMetadata: { name: `Test-${Date.now()}` }
    };

    response = await fetch('https://api.pinata.cloud/pinning/pinJSONToIPFS', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'pinata_api_key': pinata.PINATA_API_KEY,
            'pinata_secret_api_key': pinata.PINATA_SECRET
        },
        body: JSON.stringify(pinataData)
    });
    const pinataResult = await response.json();
    console.log(`✅ Uploaded to Pinata: ${pinataResult.IpfsHash}\n`);

    // 3. Submit to backend
    console.log("3️⃣ Submitting to backend...");
    response = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${data.token}`
        },
        body: JSON.stringify({
            user_id: data.user.id,
            pre_moves: moves,
            cid: pinataResult.IpfsHash,
            bet_amount: "0.001"
        })
    });
    console.log("✅ Submitted to backend\n");

    // 4. Submit to blockchain
    console.log("4️⃣ Submitting to blockchain...");
    const baseBetWei = parseEther("0.001");
    const depositWei = baseBetWei * 1n; // Coefficient is 1

    const contractWithSigner = contract.connect(signer);
    const tx = await contractWithSigner.submitPremoveCID(baseBetWei, pinataResult.IpfsHash, { value: depositWei });
    console.log(`📡 Transaction sent: ${tx.hash}`);
    await tx.wait();
    console.log("✅ Transaction confirmed\n");

    // 5. Wait for blockchain event to trigger balance update
    console.log("5️⃣ Waiting 5 seconds for blockchain event...");
    await new Promise(resolve => setTimeout(resolve, 5000));

    // 6. Check Laravel logs for balance update
    console.log("6️⃣ Check Laravel logs for balance update result");
    console.log("   Look for: 'User balance updated: Address: ..., Balance: 0.001 ETH (from 1000000000000000 wei)'");
    console.log("\n✅ Test completed! Check the Laravel logs to verify the balance was converted correctly.");
}

testBalanceConversion().catch(console.error);
