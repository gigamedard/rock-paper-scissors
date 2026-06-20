import { JsonRpcProvider, Wallet, Contract } from "ethers";
import { contracts } from "./config.js";
import dotenv from "dotenv";
dotenv.config();

const LOCAL_HARDHAT_URL = "http://127.0.0.1:8545";
const LOCAL_OWNER_PK = "***REMOVED***";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet(LOCAL_OWNER_PK, provider);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    console.log("Setting securityCoefficient to 100...");
    const tx = await gameContract.setSecurityCoefficient(100);
    await tx.wait();
    console.log("Tx hash:", tx.hash);

    const newCoef = await gameContract.securityCoefficient();
    console.log("New securityCoefficient:", newCoef.toString());
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
