const { ethers } = require("hardhat");
const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

async function main() {
    console.log("Starting deployment...");
    const [deployer] = await ethers.getSigners();
    console.log("Deploying with account:", deployer.address);

    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.deploy({ gasLimit: 10000000 });
    await battlepool.waitForDeployment();
    const gameAddr = await battlepool.getAddress();
    console.log("Battlepool deployed to:", gameAddr);
    
    console.log("Setting Security Coefficient to 100...");
    const txCoeff = await battlepool.setSecurityCoefficient(100);
    await txCoeff.wait();
    console.log("✅ Security Coefficient set to 100.");
    
    console.log("Setting Default Min Cooldown to 10 seconds...");
    const txCooldown = await battlepool.setDefaultMinCooldown(10);
    await txCooldown.wait();
    console.log("✅ Default Min Cooldown set to 10 seconds.");
    
    const SNTToken = await ethers.getContractFactory("SNTToken");
    const sntToken = await SNTToken.deploy({ gasLimit: 5000000 });
    await sntToken.waitForDeployment();
    const sntAddr = await sntToken.getAddress();
    console.log("SNTToken deployed to:", sntAddr);
    
    const MarketplaceEscrow = await ethers.getContractFactory("MarketplaceEscrow");
    const marketplaceEscrow = await MarketplaceEscrow.deploy(sntAddr, deployer.address, { gasLimit: 5000000 });
    await marketplaceEscrow.waitForDeployment();
    const marketplaceAddr = await marketplaceEscrow.getAddress();
    console.log("MarketplaceEscrow deployed to:", marketplaceAddr);
    
    console.log("Deployment complete!");

    // --- AUTOMATION: Transfer 1000 SNT to Account #0 (TB testing) ---
    console.log("Transferring 1000 SNT to Account #0 for testing...");
    const ownerAddress = "0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D";
    await hre.network.provider.request({
        method: "hardhat_impersonateAccount",
        params: [ownerAddress],
    });
    await hre.network.provider.send("hardhat_setBalance", [
        ownerAddress,
        "0x56BC75E2D63100000", // 100 ETH
    ]);
    const ownerSigner = await ethers.getSigner(ownerAddress);
    const sntTokenAsOwner = sntToken.connect(ownerSigner);
    await sntTokenAsOwner.transfer(deployer.address, ethers.parseEther("1000"));
    await hre.network.provider.request({
        method: "hardhat_stopImpersonatingAccount",
        params: [ownerAddress],
    });
    console.log("✅ Transferred 1000 SNT to Account #0 for TB testing.");

    // --- AUTOMATION: Update smart_contracts/config.js ---
    const configPath = path.join(__dirname, "..", "smart_contracts", "config.js");
    if (fs.existsSync(configPath)) {
        let configContent = fs.readFileSync(configPath, "utf8");
        configContent = configContent.replace(/game:\s*\{\s*address:\s*"0x[a-fA-F0-9]+"/g, `game: {\n    address: "${gameAddr}"`);
        configContent = configContent.replace(/snt:\s*\{\s*address:\s*"0x[a-fA-F0-9]+"/g, `snt: {\n    address: "${sntAddr}"`);
        configContent = configContent.replace(/marketplace:\s*\{\s*address:\s*"0x[a-fA-F0-9]+"/g, `marketplace: {\n    address: "${marketplaceAddr}"`);
        fs.writeFileSync(configPath, configContent);
        console.log("✅ Updated smart_contracts/config.js with new addresses.");
    }

    // --- AUTOMATION: Update Laravel .env ---
    const envPath = path.join(__dirname, "..", ".env");
    if (fs.existsSync(envPath)) {
        let envContent = fs.readFileSync(envPath, "utf8");
        envContent = envContent.replace(/BATTLEPOOL_ADDRESS=0x[a-fA-F0-9]*/g, `BATTLEPOOL_ADDRESS=${gameAddr}`);
        envContent = envContent.replace(/SNT_TOKEN_ADDRESS=0x[a-fA-F0-9]*/g, `SNT_TOKEN_ADDRESS=${sntAddr}`);
        envContent = envContent.replace(/MARKETPLACE_ESCROW_ADDRESS=0x[a-fA-F0-9]*/g, `MARKETPLACE_ESCROW_ADDRESS=${marketplaceAddr}`);
        fs.writeFileSync(envPath, envContent);
        console.log("✅ Updated Laravel .env with new addresses.");
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
