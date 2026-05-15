import { JsonRpcProvider, Wallet, parseEther, ethers } from "ethers";

const provider = new JsonRpcProvider("http://127.0.0.1:8545");
const faucetWallet = new Wallet("***REMOVED***", provider);

async function fundBots() {
    console.log("--- Funding 30 Bots ---");
    for (let i = 0; i < 30; i++) {
        const hdNode = ethers.HDNodeWallet.fromPhrase(
            "test test test test test test test test test test test junk",
            undefined,
            `m/44'/60'/0'/0/${i}`
        );
        const botAddress = hdNode.address;
        const balance = await provider.getBalance(botAddress);
        
        if (balance < parseEther("10")) {
            console.log(`Funding Bot #${i+1} (${botAddress})...`);
            const tx = await faucetWallet.sendTransaction({
                to: botAddress,
                value: parseEther("100")
            });
            await tx.wait();
        } else {
            console.log(`Bot #${i+1} (${botAddress}) already has ${ethers.formatEther(balance)} ETH.`);
        }
    }
    console.log("--- Funding Completed ---");
}

fundBots().catch(console.error);
