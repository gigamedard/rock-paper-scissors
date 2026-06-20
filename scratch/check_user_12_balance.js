import { ethers } from "ethers";

const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");

async function main() {
  const accounts = await provider.listAccounts();
  // Account 1 corresponds to User 12 (0x70997970c51812dc3a010c7d01b50e0d17dc79c8)
  const wallet1 = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
  const balance = await provider.getBalance(wallet1);
  console.log(`Wallet ${wallet1} Balance: ${ethers.formatEther(balance)} ETH`);
}

main().catch((error) => {
  console.error("Error:", error);
});
