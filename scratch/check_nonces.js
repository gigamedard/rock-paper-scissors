import { ethers } from "ethers";
import fs from "fs";

const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");

const configPath = "smart_contracts/config.js";
let configContent = fs.readFileSync(configPath, "utf8");

const gameAddressMatch = configContent.match(/address:\s*['"](0x[a-fA-F0-9]{40})['"]/);
if (!gameAddressMatch) {
  console.error("Could not find game contract address in config!");
  process.exit(1);
}
const gameAddress = gameAddressMatch[1];

const artifactPath = "battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json";
const artifact = JSON.parse(fs.readFileSync(artifactPath, "utf8"));
const abi = artifact.abi;

async function main() {
  const gameContract = new ethers.Contract(gameAddress, abi, provider);
  const targetWallet = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
  
  const nonce = await gameContract.nonces(targetWallet);
  const userBalance = await gameContract.userBalances(targetWallet);
  const isUserInAnyPool = await gameContract.isUserInAnyPool(targetWallet);

  console.log(`targetWallet: ${targetWallet}`);
  console.log(`On-chain Nonce: ${nonce.toString()}`);
  console.log(`On-chain UserBalance: ${ethers.formatEther(userBalance)} ETH`);
  console.log(`On-chain isUserInAnyPool: ${isUserInAnyPool}`);
}

main().catch((error) => {
  console.error("Error:", error);
});
