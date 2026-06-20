const { ethers } = require("ethers");
const fs = require("fs");

async function fixStuckPool() {
    const provider = new JsonRpcProvider("http://127.0.0.1:8545");
    const adminWallet = new ethers.Wallet("***REMOVED***", provider); // Default Hardhat Account 0
    
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const abi = [
        "function setStagnantBlockLimit(uint256) external",
        "function checkAndRefundStagnantPool(uint256) external",
        "function getPoolInfo(uint256) view returns (uint256 poolId, uint256 maxSize, uint256 userCount, bool isLocked)"
    ];
    const contract = new ethers.Contract(address, abi, adminWallet);
    
    const baseBet = ethers.parseEther("0.01");
    
    console.log("Setting stagnant limit to 0...");
    let tx = await contract.setStagnantBlockLimit(0);
    await tx.wait();
    
    console.log("Calling checkAndRefundStagnantPool...");
    tx = await contract.checkAndRefundStagnantPool(baseBet);
    await tx.wait();
    
    console.log("Restoring stagnant limit to 100...");
    tx = await contract.setStagnantBlockLimit(100);
    await tx.wait();
    
    const info = await contract.getPoolInfo(baseBet);
    console.log(`Pool info after reset: userCount=${info.userCount}`);
}

// Add JsonRpcProvider import correctly
const { JsonRpcProvider } = require("ethers");

fixStuckPool().catch(console.error);
