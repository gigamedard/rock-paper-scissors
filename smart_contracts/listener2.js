import axios from 'axios';
import { JsonRpcProvider, Wallet, Contract } from "ethers"; // Updated imports from ethers v6
import { contractAddress2, privateKey2, localHardhatUrl, abi } from "./config.js"; // Import configuration
//const csrfResponse = await fetch('http://127.0.0.1:8000/csrf-token');
//const { csrfToken } = await csrfResponse.json();
// Initialize the provider
//const provider = new JsonRpcProvider(`https://eth-sepolia.g.alchemy.com/v2/${privateKey}`);
const provider = new JsonRpcProvider(localHardhatUrl);//0x5FbDB2315678afecb367f032d93F642f64180aa3

// Initialize the wallet
const wallet = new Wallet(privateKey2, provider);
// Define the contract details

// Replace with your deployed contract address
const interSC1 = new Contract(contractAddress2, abi, wallet);
async function main() {

  
// Initialize the contract

// get the counter
let count = await interSC1.counter();
//await count.wait();
console.log(parseFloat(count));
interSC1.on("CounterUpdated", async (newCounter) => {

  let counter  = parseFloat(newCounter.toString());

  console.log(`Counter updated to: ${counter}`);

  try {
      const response = await fetch(`http://127.0.0.1:8000/update-counter?counter=${counter}&action=update_counter`);
      console.log("Response status:", response.status);
      console.log("Response body:", response.message);
      if (response.ok) {
          console.log('Database updated successfully.');
      } else {
        console.error('Failed to update database:', await response.text());
      }
  } catch (error) {
      console.error('Error updating database:', error.message);
  }
});
}

// Run the script
main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
