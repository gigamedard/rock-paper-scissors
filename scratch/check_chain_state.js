import pkg from 'hardhat';
const { ethers } = pkg;

async function main() {
    const address = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    
    // We use getContractAt from ethers
    const Battlepool = await ethers.getContractAt("Battlepool", contractAddress);
    
    const inPool = await Battlepool.isUserInAnyPool(address);
    console.log(`User ${address} in any pool: ${inPool}`);
    
    const balance = await Battlepool.userBalances(address);
    console.log(`User balance: ${ethers.formatEther(balance)} ETH`);
    
    const poolInfo = await Battlepool.getPoolInfo(ethers.parseUnits("0.01", "ether"));
    console.log(`Pool Info (0.01): ID=${poolInfo[0]}, Users=${poolInfo[2]}, Locked=${poolInfo[3]}`);
    
    const users = await Battlepool.getPoolUsers(ethers.parseUnits("0.01", "ether"));
    console.log(`Users in pool: ${users.join(', ')}`);
}

main().catch(console.error);
