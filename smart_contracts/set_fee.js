import { JsonRpcProvider, Wallet, Contract } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

const LOCAL_OWNER_PK = "***REMOVED***";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet(LOCAL_OWNER_PK, provider);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    console.log("Setting fee to 2.5% (250 basis points)...");
    const tx = await gameContract.setFeeBasisPoints(250);
    await tx.wait();
    console.log("Tx hash:", tx.hash);

    const newFee = await gameContract.feeBasisPoints();
    console.log("New fee basis points:", newFee.toString());
}

main().catch(console.error);
