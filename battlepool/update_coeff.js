const { ethers } = require("hardhat");

async function main() {
    const battlepoolAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.attach(battlepoolAddress);

    console.log("Setting Security Coefficient to 1000...");
    const tx = await battlepool.setSecurityCoefficient(1000, { gasLimit: 100000 });
    await tx.wait();
    
    console.log("Success!");
    
    const coeff = await battlepool.securityCoefficient();
    console.log("New Security Coefficient:", coeff.toString());
}

main().catch(console.error);
