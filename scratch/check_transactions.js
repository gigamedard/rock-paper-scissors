import { ethers } from "ethers";
import fs from "fs";

const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");

// Read contract address and ABI
// We can locate them in smart_contracts/config.js or load from battlepool artifacts.
const configPath = "smart_contracts/config.js";
let configContent = fs.readFileSync(configPath, "utf8");

// Extract contract address using regex
const gameAddressMatch = configContent.match(/address:\s*['"](0x[a-fA-F0-9]{40})['"]/);
if (!gameAddressMatch) {
  console.error("Could not find game contract address in config!");
  process.exit(1);
}
const gameAddress = gameAddressMatch[1];
console.log("Game contract address:", gameAddress);

// Let's load the ABI from battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json
const artifactPath = "battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json";
if (!fs.existsSync(artifactPath)) {
  console.error("Battlepool artifact not found at:", artifactPath);
  process.exit(1);
}
const artifact = JSON.parse(fs.readFileSync(artifactPath, "utf8"));
const abi = artifact.abi;

async function main() {
  const gameContract = new ethers.Contract(gameAddress, abi, provider);
  const targetWallet = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
  
  const currentBlock = await provider.getBlockNumber();
  console.log("Current block number:", currentBlock);

  // Query DepositReceived events
  const depositFilter = gameContract.filters.DepositReceived(targetWallet);
  const deposits = await gameContract.queryFilter(depositFilter, 0, currentBlock);
  console.log(`\n--- DEPOSITS for ${targetWallet} (${deposits.length} events) ---`);
  for (const event of deposits) {
    console.log(`Block ${event.blockNumber} | Amount: ${ethers.formatEther(event.args[1])} ETH`);
  }

  // Query PayoutProcessed events
  const payoutFilter = gameContract.filters.PayoutProcessed(targetWallet);
  const payouts = await gameContract.queryFilter(payoutFilter, 0, currentBlock);
  console.log(`\n--- PAYOUTS for ${targetWallet} (${payouts.length} events) ---`);
  for (const event of payouts) {
    console.log(`Block ${event.blockNumber} | Amount: ${ethers.formatEther(event.args[1])} ETH`);
  }

  // Query PlayerClaimed events
  const claimFilter = gameContract.filters.PlayerClaimed(targetWallet);
  const claims = await gameContract.queryFilter(claimFilter, 0, currentBlock);
  console.log(`\n--- CLAIMS for ${targetWallet} (${claims.length} events) ---`);
  for (const event of claims) {
    console.log(`Block ${event.blockNumber} | Amount: ${ethers.formatEther(event.args[1])} ETH | Nonce: ${event.args[2]}`);
  }
}

main().catch((error) => {
  console.error("Error:", error);
});
