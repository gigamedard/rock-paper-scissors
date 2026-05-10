const { ethers } = require("hardhat");

async function main() {
    const address = "0x6D58073AeeB28068c5925D618DA9f4c4F35727b3";
    const balance = await ethers.provider.getBalance(address);
    console.log(`Balance of ${address}: ${ethers.formatEther(balance)} ETH`);
}

main().catch(console.error);
