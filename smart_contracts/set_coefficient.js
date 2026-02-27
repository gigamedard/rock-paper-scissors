import { JsonRpcProvider, Wallet, Contract } from "ethers";
import { contracts, GAME_WALLET_PK, FUJI_RPC_URL } from "./config.js";

async function main() {
    console.log("Connecting to Fuji Testnet...");
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const wallet = new Wallet(GAME_WALLET_PK, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    console.log("Current security coefficient:", await contract.securityCoefficient());

    console.log("Setting security coefficient to 1...");
    const tx = await contract.setSecurityCoefficient(1);
    console.log("Transaction sent:", tx.hash);
    await tx.wait();

    console.log("Updated security coefficient:", await contract.securityCoefficient());
}

main().catch(console.error);
