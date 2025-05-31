import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers"; // Updated import
import { contractAddress3, privateKey3, localHardhatUrl, abi3 } from "./config.js"; // Import configuration

// Initialize the provider
const provider = new JsonRpcProvider(localHardhatUrl);

const wallet = new Wallet(privateKey3, provider);

// Define the contract details
const PaymentSC1 = new Contract(contractAddress3, abi3, wallet);

async function sendETH() {
  try {
    // Specify the amount of ETH to send (e.g., 0.01 ETH)
    const amountInETH = "0.01";
    const amountInWei = parseEther(amountInETH); // Correct import for ethers v6

    // Call the deposit function with ETH
    const tx = await PaymentSC1.deposit({ value: amountInWei });

    // Wait for the transaction to be mined
    await tx.wait();

    console.log(`Transaction successful! Hash: ${tx.hash}`);
    console.log(`Sent ${amountInETH} ETH to contract ${contractAddress3}`);
  } catch (error) {
    console.error("Error sending ETH:", error);
  }
}

// Example in JavaScript (Hardhat/Truffle)
/*
async function triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt) {
  try {
    const tx = await battlepool.triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt);
    await tx.wait();
    console.log(`Pool emitted event triggered successfully for pool ID: ${poolId}`);
  } catch (error) {
    console.error("Error triggering pool emitted event:", error);
  }
}
const users = ["0x123...", "0x456..."];
const premoveCIDs = ["Qm123...", "Qm456..."];
const poolId = 1;
const baseBet = ethers.utils.parseEther("0.1");
const poolSalt = "0x12345678";

triggerPoolEmittedEventForTesting(poolId, baseBet, users, premoveCIDs, poolSalt); */

sendETH();
