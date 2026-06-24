import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

const LOCAL_OWNER_PK = "***REMOVED***";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet(LOCAL_OWNER_PK, provider);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    console.log("Setting default max base bet to 100 ether...");
    const tx = await gameContract.setDefaultMaxBaseBet(parseEther("100"));
    await tx.wait();
    console.log("Tx hash:", tx.hash);

    const newMax = await gameContract.defaultMaxBaseBet();
    console.log("New default max base bet:", newMax.toString());
}

main().catch(console.error);
