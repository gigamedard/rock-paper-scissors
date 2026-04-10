import { JsonRpcProvider, Contract } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);
    
    const sc = await gameContract.securityCoefficient();
    console.log("Contract Security Coefficient:", sc.toString());
}
main().catch(console.error);
