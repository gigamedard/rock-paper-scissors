const hre = require("hardhat");

async function main() {
  const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
  const Battlepool = await hre.ethers.getContractAt("Battlepool", contractAddress);

  // Check some common base bets
  const tiers = [
    hre.ethers.parseEther("0.01"),
    hre.ethers.parseEther("0.05"),
    hre.ethers.parseEther("0.1")
  ];

  for (const tier of tiers) {
    const info = await Battlepool.getPoolInfo(tier);
    console.log(`Tier: ${hre.ethers.formatEther(tier)} ETH`);
    console.log(`  Pool ID: ${info.poolId.toString()}`);
    console.log(`  Max Size: ${info.maxSize.toString()}`);
    console.log(`  User Count: ${info.userCount.toString()}`);
    console.log(`  Is Locked: ${info.isLocked}`);
    
    if (info.userCount > 0) {
        const users = await Battlepool.getPoolUsers(tier);
        console.log(`  Users: ${users.join(", ")}`);
    }
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
