const { ethers } = require("hardhat");

async function main() {
    const battlepoolAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.attach(battlepoolAddress);

    const coeff = await battlepool.securityCoefficient();
    const fee = await battlepool.feeBasisPoints();
    
    console.log("Security Coefficient:", coeff.toString());
    console.log("Fee Basis Points:", fee.toString());

    const baseBet = ethers.parseEther("0.01");
    const [poolId, maxSize, userCount, isLocked] = await battlepool.getPoolInfo(baseBet);
    const users = await battlepool.getPoolUsers(baseBet);

    console.log(`Pool Info (0.01): ID=${poolId}, MaxSize=${maxSize}, UserCount=${userCount}, Locked=${isLocked}`);
    console.log("Users in pool:", users);
}

main().catch(console.error);
