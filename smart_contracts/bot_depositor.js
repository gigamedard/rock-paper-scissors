import { JsonRpcProvider, Wallet, Contract, parseEther, ethers } from "ethers";
import fetch from "node-fetch";
import { LARAVEL_API_URL, LOCAL_HARDHAT_URL, contracts } from "./config.js";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);

async function main() {
    const args = process.argv.slice(2);
    const numBots = parseInt(args[0]) || 40;
    const startIndex = parseInt(args[1]) || 2; // Account 2 by default
    const baseBet = args[2] || "0.05";
    
    console.log(`===========================================`);
    console.log(`?? Funding Blockchain Bots for Autoplay`);
    console.log(`?? Target Bots: ${numBots}`);
    console.log(`?? Starting from Index: ${startIndex}`);
    console.log(`?? Base Bet: ${baseBet} ETH`);
    console.log(`===========================================`);

    let config;
    try {
        const configRes = await fetch(`${LARAVEL_API_URL}/artefacts`);
        if (!configRes.ok) throw new Error();
        config = await configRes.json();
    } catch (e) {
        config = { security_coefficient: 1000, smart_contract_fee_percentage: 2.5 };
    }
    
    const securityCoefficient = config.security_coefficient || 1000;
    const feePercentage = config.smart_contract_fee_percentage || 2.5;

    // Calculate required deposit amount (Security margin + Fees)
    const stakeWei = parseEther((parseFloat(baseBet) * securityCoefficient).toFixed(18));
    const feeBasisPoints = BigInt(Math.round(feePercentage * 100));
    // The exact same formula as the frontend and simulation_bots.js to pass the contract require
    const amountToSendWei = (stakeWei * (10000n + feeBasisPoints)) / 10000n + 1n;

    console.log(`\nEach bot will deposit: ${ethers.formatEther(amountToSendWei)} ETH to start autoplay`);

    const activeBots = [];
    
    for (let i = startIndex; i < startIndex + numBots; i++) {
        try {
            const hdNode = ethers.HDNodeWallet.fromPhrase(
                "test test test test test test test test test test test junk",
                undefined,
                `m/44'/60'/0'/0/${i}`
            );
            const botWallet = new Wallet(hdNode.privateKey, provider);
            const gameContract = new Contract(contracts.game.address, contracts.game.abi, botWallet);
            
            console.log(`   [Bot ${i}] ${botWallet.address} sending deposit...`);
            
            // Just call the deposit() function on the smart contract
            const tx = await gameContract.deposit({ value: amountToSendWei });
            await tx.wait();
            
            console.log(`   ? [Bot ${i}] Deposited successfully (tx: ${tx.hash})`);
            activeBots.push(botWallet.address);
            
        } catch (error) {
            console.error(`   ? [Bot ${i}] Error:`, error.message);
        }
    }

    console.log(`\n===========================================`);
    console.log(`?? Bot Deposit Phase Complete!`);
    console.log(`? ${activeBots.length}/${numBots} Bots deposited successfully.`);
    console.log(`app.js will catch the DepositReceived events and update Laravel DB.`);
    console.log(`===========================================\n`);
}

main().catch(console.error);

