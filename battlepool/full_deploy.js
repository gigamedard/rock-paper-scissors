const { ethers } = require("hardhat");

async function main() {
    console.log("Starting deployment...");
    const [deployer] = await ethers.getSigners();
    console.log("Deploying with account:", deployer.address);

    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.deploy();
    await battlepool.waitForDeployment();
    console.log("Battlepool deployed to:", await battlepool.getAddress());

    const SNTToken = await ethers.getContractFactory("SNTToken");
    const sntToken = await SNTToken.deploy();
    await sntToken.waitForDeployment();
    console.log("SNTToken deployed to:", await sntToken.getAddress());

    const MarketplaceEscrow = await ethers.getContractFactory("MarketplaceEscrow");
    const marketplaceEscrow = await MarketplaceEscrow.deploy(await sntToken.getAddress(), deployer.address);
    await marketplaceEscrow.waitForDeployment();
    console.log("MarketplaceEscrow deployed to:", await marketplaceEscrow.getAddress());

    console.log("Deployment complete!");
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
