import { JsonRpcProvider, formatEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const balance = await provider.getBalance(contracts.game.address);
    console.log("Contract Balance:", formatEther(balance), "ETH");
    
    // Check one of the bot's balance
    const botAddress = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const botBalance = await provider.getBalance(botAddress);
    console.log(`Bot #1 (${botAddress}) Balance:`, formatEther(botBalance), "ETH");
}
main().catch(console.error);
