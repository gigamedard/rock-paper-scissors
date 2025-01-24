import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers"; // Updated import
import { contractAddress3, privateKey3, localHardhatUrl, abi3 } from "./config.js"; // Import configuration

// Initialize the provider
const provider = new JsonRpcProvider(localHardhatUrl);

const wallet = new Wallet(privateKey3, provider);

// Define the contract details
const battlepool = new Contract(contractAddress3, abi3, wallet);

// Example in JavaScript (Hardhat/Truffle)
const users = [
  "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266", // Example address 1
  "0x70997970C51812dc3A010C7d01b50e0d17dc79C8", // Example address 2
];
const premoveCIDs = ["QmTestCID1", "QmTestCID2"];
const poolId = 1;
const baseBet = parseEther("0.0000033");
const poolSalt = "Akpkohou"; // Now passed as a string

async function triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt) {
  try {
    const tx = await battlepool.triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt);
    await tx.wait();
    console.log(`Pool emitted event triggered successfully for pool ID: ${poolId}`);
  } catch (error) {
    console.error("Error triggering pool emitted event:", error);
  }
}

await triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt);