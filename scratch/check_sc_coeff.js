import { JsonRpcProvider, Contract, formatEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts } from "../smart_contracts/config.js";

async function main() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);

    const coeff = await gameContract.securityCoefficient();
    const owner = await gameContract.owner();
    const balance = await provider.getBalance(contracts.game.address);
    const botAddress = "0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc";
    const botBalance = await provider.getBalance(botAddress);

    console.log("========================================");
    console.log(`Contract Address: ${contracts.game.address}`);
    console.log(`Owner:            ${owner}`);
    console.log(`Security Coeff:   ${coeff.toString()}`);
    console.log(`Contract Balance: ${formatEther(balance)} ETH`);
    console.log(`Bot 3 Balance:    ${formatEther(botBalance)} ETH`);
    console.log("========================================");
}

main().catch(console.error);
