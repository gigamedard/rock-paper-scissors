import { provider, contract } from "./config.js";
import { formatEther } from "ethers";

async function check() {
    try {
        const address = await contract.getAddress();
        const balance = await provider.getBalance(address);
        console.log("Contract Address:", address);
        console.log("Contract Balance:", formatEther(balance), "ETH");
    } catch (e) {
        console.error(e);
    }
}
check();
