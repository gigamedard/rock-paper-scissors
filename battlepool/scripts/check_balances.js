const hre = require("hardhat");

async function main() {
  const Battlepool = await hre.ethers.getContractFactory("Battlepool");
  const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
  const contract = await Battlepool.attach(contractAddress);

  console.log("Checking balances for top wallets...");
  const signers = await hre.ethers.getSigners();
  
  for (let i = 0; i < 10; i++) {
    const address = signers[i].address;
    const balance = await contract.userBalances(address);
    const ethBalance = await hre.ethers.provider.getBalance(address);
    console.log(`Wallet ${i} (${address}): Contract Balance = ${hre.ethers.formatEther(balance)} ETH, Wallet Balance = ${hre.ethers.formatEther(ethBalance)} ETH`);
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
