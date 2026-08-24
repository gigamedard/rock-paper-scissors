const { ethers } = require("hardhat");
const hre = require("hardhat");
const fs = require("fs");
const path = require("path");

/**
 * SECURITY: readSecret reads a value from a Docker secret file (/run/secrets/<name>)
 * if it exists, falling back to the process.env variable. This allows wallet keys
 * to be mounted as Docker secrets (not visible in `env` or `docker inspect`).
 */
function readSecret(envVarName, secretName) {
    const secretPath = path.join("/run/secrets", secretName || envVarName.toLowerCase());
    if (fs.existsSync(secretPath)) {
        try {
            return fs.readFileSync(secretPath, "utf8").trim();
        } catch (e) {
            console.error(`⚠️  Failed to read secret ${secretName}:`, e.message);
        }
    }
    return process.env[envVarName];
}

async function main() {
    console.log("Starting deployment...");
    const [deployer] = await ethers.getSigners();
    console.log("Deploying with account:", deployer.address);

    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = await Battlepool.deploy({ gasLimit: 10000000 });
    await battlepool.waitForDeployment();
    const gameAddr = await battlepool.getAddress();
    console.log("Battlepool deployed to:", gameAddr);

    // --- Set contract parameters BEFORE initializeRoles (deployer is still owner) ---
    // These onlyOwner calls must happen before ownership is transferred,
    // otherwise the deployer (Hardhat #0) can't call them.
    console.log("Setting Security Coefficient to 1000...");
    const txCoeff = await battlepool.setSecurityCoefficient(1000);
    await txCoeff.wait();
    console.log("✅ Security Coefficient set to 1000.");
    
    console.log("Setting Fee Basis Points to 250...");
    const txFee = await battlepool.setFeeBasisPoints(250);
    await txFee.wait();
    console.log("✓ Fee Basis Points set to 250.");

    console.log("Setting default max base bet to 100 ETH...");
    const txMaxBet = await battlepool.setDefaultMaxBaseBet(ethers.parseEther("100"));
    await txMaxBet.wait();
    console.log("✓ Default Max Base Bet set to 100 ETH.");
    
    console.log("Setting Default Min Cooldown to 10 seconds...");
    const txCooldown = await battlepool.setDefaultMinCooldown(10);
    await txCooldown.wait();
    console.log("✅ Default Min Cooldown set to 10 seconds.");

    // --- SECURITY: initialize all roles in one call (bypasses timelock, one-time only) ---
    // Read all keys from Docker secrets / env, derive addresses, and call
    // initializeRoles() which sets owner, signer, payoutOperator, and devWallet.
    // This can only be called once, within 100 blocks of deployment.
    const signerPk = readSecret("SIGNER_WALLET_PK");
    const operatorPk = readSecret("PAYOUT_OPERATOR_PK") || signerPk;
    const ownerPk = readSecret("GAME_WALLET_PK");

    const signerAddr = signerPk ? new ethers.Wallet(signerPk).address : ethers.ZeroAddress;
    const operatorAddr = operatorPk ? new ethers.Wallet(operatorPk).address : ethers.ZeroAddress;
    const ownerAddr = ownerPk ? new ethers.Wallet(ownerPk).address : ethers.ZeroAddress;

    console.log("Initializing roles:");
    console.log("  owner →", ownerAddr !== ethers.ZeroAddress ? ownerAddr : "(deployer remains)");
    console.log("  signer →", signerAddr !== ethers.ZeroAddress ? signerAddr : "(deployer remains)");
    console.log("  payoutOperator →", operatorAddr !== ethers.ZeroAddress ? operatorAddr : "(deployer remains)");

    if (ownerAddr !== ethers.ZeroAddress || signerAddr !== ethers.ZeroAddress || operatorAddr !== ethers.ZeroAddress) {
        const txInit = await battlepool.initializeRoles(
            ownerAddr !== ethers.ZeroAddress ? ownerAddr : ethers.ZeroAddress,
            signerAddr !== ethers.ZeroAddress ? signerAddr : ethers.ZeroAddress,
            operatorAddr !== ethers.ZeroAddress ? operatorAddr : ethers.ZeroAddress,
            ethers.ZeroAddress // devWallet stays as deployer (set via timelock later if needed)
        );
        await txInit.wait();
        console.log("✅ Roles initialized.");

        // Fund the new owner with ETH for gas (local dev only)
        if (ownerAddr !== ethers.ZeroAddress && (hre.network.name === "hardhat" || hre.network.name === "localhost")) {
            await hre.network.provider.send("hardhat_setBalance", [
                ownerAddr,
                "0x3635C9ADC5DEA00000", // 1000 ETH
            ]);
            console.log("✅ Funded new owner with 1000 ETH.");
        }
    } else {
        console.log("⚠️  No keys set — deployer retains all roles.");
    }
    
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
    if (hre.network.name === "hardhat" || hre.network.name === "localhost") {
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
    } else {
        console.log("Skipping local-only impersonation and balance setup on network:", hre.network.name);
    }

    // --- AUTOMATION: Update smart_contracts/config.js ---
    const configPath = path.join(__dirname, "..", "smart_contracts", "config.js");
    if (fs.existsSync(configPath)) {
        let configContent = fs.readFileSync(configPath, "utf8");
        configContent = configContent.replace(/game:\s*\{\s*\n\s*address:\s*['"]0x[a-fA-F0-9]+['"]/g, `game: {\n    address: "${gameAddr}"`);
        configContent = configContent.replace(/snt:\s*\{\s*\n\s*address:\s*['"]0x[a-fA-F0-9]+['"]/g, `snt: {\n    address: "${sntAddr}"`);
        configContent = configContent.replace(/marketplace:\s*\{\s*\n\s*address:\s*['"]0x[a-fA-F0-9]+['"]/g, `marketplace: {\n    address: "${marketplaceAddr}"`);
        fs.writeFileSync(configPath, configContent);
        console.log("✅ Updated smart_contracts/config.js with new addresses.");
    } else {
        console.warn("⚠️  config.js not found at", configPath, "- skipping address update.");
    }
}

main().catch((error) => {
    console.error(error);
    process.exitCode = 1;
});
