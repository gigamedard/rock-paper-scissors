import { JsonRpcProvider, Contract, formatEther } from "ethers";
import { contracts, LOCAL_HARDHAT_URL } from "../smart_contracts/config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const account0 = "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266";
    
    console.log("Checking SNT Address:", contracts.snt.address);
    const sntContract = new Contract(contracts.snt.address, contracts.snt.abi, provider);
    
    try {
        const balance = await sntContract.balanceOf(account0);
        console.log(`Balance of ${account0}: ${formatEther(balance)} SNT`);
    } catch (error) {
        console.error("Error querying SNT balance:", error);
    }
}

main();
