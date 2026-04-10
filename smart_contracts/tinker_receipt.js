import { JsonRpcProvider } from "ethers";
import { LOCAL_HARDHAT_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const txHash = "0x0e880f35bbbbad50363df9d974e11254ce6abc07cd4cf00c967dab031d4cd2e9";
    const receipt = await provider.getTransactionReceipt(txHash);
    console.log("Receipt found:", receipt !== null);
    if (receipt) {
        console.log("Logs count:", receipt.logs.length);
        receipt.logs.forEach((log, index) => {
            console.log(`Log ${index} address:`, log.address);
            console.log(`Log ${index} topics:`, log.topics);
        });
    }
}
main().catch(console.error);
