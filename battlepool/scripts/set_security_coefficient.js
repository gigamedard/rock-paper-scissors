const { ethers } = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
    const [deployer] = await ethers.getSigners();
    console.log("Using account:", deployer.address);

    const envPath = path.join(__dirname, "..", "..", ".env");
    const envContent = fs.readFileSync(envPath, "utf8");
    const contractAddress = envContent.match(/BATTLEPOOL_ADDRESS=(0x[a-fA-F0-9]*)/)[1];
    
    console.log("Connecting to Battlepool at:", contractAddress);
    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.attach(contractAddress);

    const newCoefficient = 100;
    console.log(`Setting securityCoefficient to ${newCoefficient}...`);
    const tx = await battlepool.setSecurityCoefficient(newCoefficient);
    await tx.wait();
    console.log("✅ securityCoefficient updated on-chain.");
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
