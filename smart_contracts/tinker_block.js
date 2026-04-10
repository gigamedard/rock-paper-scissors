import { JsonRpcProvider } from "ethers";
import { LOCAL_HARDHAT_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const blockNumber = await provider.getBlockNumber();
    console.log("Current Block Number:", blockNumber);
}
main().catch(console.error);
