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
  const owner = await gameContract.owner();
  console.log("Contract Owner Address:", owner);
  
  // Let's verify what the private key ***REMOVED*** derives to
  const pk = "***REMOVED***";
  const wallet = new ethers.Wallet(pk);
  console.log("Derived Address from GAME_WALLET_PK:", wallet.address);
}

main().catch((error) => {
  console.error("Error:", error);
});
