import { JsonRpcProvider, Wallet, parseEther, ethers } from "ethers";

const LOCAL_HARDHAT_URL = "http://127.0.0.1:8545";
const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);

// Account #1 from the "junk" mnemonic (Avoids conflict with Bridge on Account #0)
const mainWallet = new Wallet("***REMOVED***", provider);

async function fundHardhatBots() {
    console.log("Starting funding for bots 20-159...");
    let nonce = await provider.getTransactionCount(mainWallet.address);
    
    for (let i = 20; i < 160; i++) {
        const hdNode = ethers.HDNodeWallet.fromPhrase(
            "test test test test test test test test test test test junk",
            undefined,
            `m/44'/60'/0'/0/${i}`
        );
        const botWallet = new Wallet(hdNode.privateKey, provider);
        
        console.log(`Funding Bot #${i + 1} (${botWallet.address}) with 50 ETH (Nonce: ${nonce})...`);
        
        try {
            const tx = await mainWallet.sendTransaction({
                to: botWallet.address,
                value: parseEther("50.0"),
                nonce: nonce++
            });
            await tx.wait();
            console.log(`✅ Funded ${botWallet.address}`);
            await new Promise(r => setTimeout(r, 100)); // Small delay
        } catch (e) {
            console.error(`❌ Failed to fund ${botWallet.address}: ${e.message}`);
            // Recalculate nonce on error just in case
            nonce = await provider.getTransactionCount(mainWallet.address);
        }
    }
    
    console.log("All bots 20-159 funded!");
}

fundHardhatBots().catch(console.error);
