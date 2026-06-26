const { ethers } = require("ethers");

async function checkBalance() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const wallet = "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";
    const balance = await provider.getBalance(wallet);
    console.log(`Balance of ${wallet}: ${ethers.formatEther(balance)} ETH`);
}

checkBalance();
