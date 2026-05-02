import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import fetch from "node-fetch";
import { contracts, LOCAL_HARDHAT_URL, LARAVEL_API_URL } from "./config.js";

async function run() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet("***REMOVED***", provider); // Account 0
    const address = wallet.address;

    console.log("0. Authenticating...");
    const msgRes = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ wallet_address: address })
    });
    const { message } = await msgRes.json();
    const signature = await wallet.signMessage(message);
    const authRes = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ wallet_address: address, signature: signature, message: message })
    });
    const { token, user } = await authRes.json();
    console.log(`✅ Authenticated as User ID: ${user.id}`);

    const moves = ['rock', 'rock', 'rock', 'paper', 'paper', 'paper', 'scissors', 'scissors', 'scissors', 'rock'];

    console.log("1. Submitting moves to Backend...");
    const backendRes = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
        method: "POST",
        headers: { 
            "Content-Type": "application/json",
            "Authorization": `Bearer ${token}`
        },
        body: JSON.stringify({
            user_id: user.id,
            pre_moves: moves,
            cid: "bag-final-test-cid",
            bet_amount: 0.01
        })
    });
    
    if (!backendRes.ok) {
        const err = await backendRes.text();
        throw new Error("Backend failed: " + err);
    }
    console.log("✅ Backend moves stored.");

    console.log("2. Joining Smart Contract Pool...");
    const game = new Contract(contracts.game.address, contracts.game.abi, wallet);
    
    const tx = await game.submitPremoveCID(parseEther("0.01"), "bag-final-test-cid", { value: parseEther("0.011") });
    console.log("✅ Blockchain join TX sent:", tx.hash);
    await tx.wait();
    console.log("🏁 TX Confirmed. Pool should emit now!");
}

run().catch(console.error);
