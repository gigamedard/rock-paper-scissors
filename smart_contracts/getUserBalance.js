import { ethers } from "ethers";

// Connect to the local Hardhat node
const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");

async function main() {
  // Get the list of accounts provided by Hardhat
  const accounts = await provider.listAccounts();
  console.log("Available Accounts:", accounts);

  // Fetch the balance of the first account
  const balance = await provider.getBalance(accounts[0]);
  console.log(`Account Balance: ${ethers.formatEther(balance)} ETH`);
}

main().catch((error) => {
  console.error("Error:", error);
});
