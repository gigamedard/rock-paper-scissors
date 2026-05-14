const { ethers } = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
    const [deployer] = await ethers.getSigners();
    const envPath = path.join(__dirname, "..", "..", ".env");
    const envContent = fs.readFileSync(envPath, "utf8");
    const contractAddress = envContent.match(/BATTLEPOOL_ADDRESS=(0x[a-fA-F0-9]*)/)[1];
    
    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.attach(contractAddress);

    const newCoefficient = 1000;
    console.log(`Reverting securityCoefficient to ${newCoefficient}...`);
    const tx = await battlepool.setSecurityCoefficient(newCoefficient);
    await tx.wait();
    console.log("✅ securityCoefficient reverted to 1000 on-chain.");
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
