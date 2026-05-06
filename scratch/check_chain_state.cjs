const { ethers } = require("hardhat");

async function main() {
    const address = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    
    const Battlepool = await ethers.getContractAt("Battlepool", contractAddress);
    
    const inPool = await Battlepool.isUserInAnyPool(address);
    console.log(`User ${address} in any pool: ${inPool}`);
    
    const balance = await Battlepool.userBalances(address);
    console.log(`User balance: ${ethers.formatEther(balance)} ETH`);
    
    const poolInfo = await Battlepool.getPoolInfo(ethers.parseEther("0.01"));
    console.log(`Pool Info (0.01): ID=${poolInfo[0]}, Users=${poolInfo[2]}, Locked=${poolInfo[3]}`);
}

main().catch(console.error);
