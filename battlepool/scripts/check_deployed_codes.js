const { ethers } = require("hardhat");

async function main() {
    const sntAddress = "0xe7f1725E7734CE288F8367e1Bb143E90bb3F0512";
    const marketplaceAddress = "0x9fE46736679d2D9a65F0992F2272dE9f3c7fa6e0";
    const battlepoolAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";

    const provider = ethers.provider;

    const sntCode = await provider.getCode(sntAddress);
    const marketplaceCode = await provider.getCode(marketplaceAddress);
    const battlepoolCode = await provider.getCode(battlepoolAddress);

    console.log("SNT Code length:", sntCode.length);
    console.log("Marketplace Code length:", marketplaceCode.length);
    console.log("Battlepool Code length:", battlepoolCode.length);
}

main().catch(console.error);
