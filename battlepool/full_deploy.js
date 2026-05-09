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

    // --- AUTOMATION: Update smart_contracts/config.js ---
    const fs = require("fs");
    const path = require("path");
    const configPath = path.join(__dirname, "..", "smart_contracts", "config.js");
    
    if (fs.existsSync(configPath)) {
        let configContent = fs.readFileSync(configPath, "utf8");
        
        const gameAddr = await battlepool.getAddress();
        const sntAddr = await sntToken.getAddress();
        const marketplaceAddr = await marketplaceEscrow.getAddress();

        // Regex to replace addresses
        configContent = configContent.replace(/game: \{\s+address: "0x[a-fA-F0-0-9]+"/, `game: {\n    address: "${gameAddr}"`);
        configContent = configContent.replace(/snt: \{\s+address: "0x[a-fA-F0-0-9]+"/, `snt: {\n    address: "${sntAddr}"`);
        configContent = configContent.replace(/marketplace: \{\s+address: "0x[a-fA-F0-0-9]+"/, `marketplace: {\n    address: "${marketplaceAddr}"`);
        
        fs.writeFileSync(configPath, configContent);
        console.log("✅ Updated smart_contracts/config.js with new addresses.");
    }
}


main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
