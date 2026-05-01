import { JsonRpcProvider, Wallet, Contract } from 'ethers';
import { contracts, LOCAL_HARDHAT_URL, GAME_WALLET_PK } from './config.js';

async function testSetCoefficient() {
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const wallet = new Wallet(GAME_WALLET_PK, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    const newCoeff = 500;
    console.log(`🚀 Changing Security Coefficient to ${newCoeff}...`);

    try {
        const tx = await contract.setSecurityCoefficient(newCoeff);
        console.log(`⏳ Transaction sent: ${tx.hash}`);
        await tx.wait();
        console.log(`✅ Transaction confirmed!`);
    } catch (error) {
        console.error(`❌ Error:`, error);
    }
}

testSetCoefficient();
