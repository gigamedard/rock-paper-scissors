import { JsonRpcProvider, Wallet, Contract, formatEther } from "ethers";
import { contractAddress3, privateKey3, localHardhatUrl, abi3 } from "./config.js";

// Initialize provider, wallet, and contract
const provider = new JsonRpcProvider(localHardhatUrl);
const wallet = new Wallet(privateKey3, provider);
const contract = new Contract(contractAddress3, abi3, wallet);

// Function to update user balance in the backend
async function updateUserBalance(user, amount) {
  try {
    const url = `http://127.0.0.1:8000/update-balance?balance=${formatEther(amount)}&wallet_address=${user}`;
    const response = await fetch(url);

    if (response.ok) {
      console.log(`✅ Balance updated successfully for user: ${user}`);
    } else {
      const errorText = await response.text();
      console.error(`❌ Failed to update balance for user: ${user}. Response: ${errorText}`);
    }
  } catch (error) {
    console.error(`🚨 Error while updating balance for user ${user}:`, error.message);
  }
}

// Main function to listen for DepositReceived events
async function main() {
  try {
    console.log("⏳ Listening for DepositReceived events...");

    contract.on("DepositReceived", async (user, amount) => {
      console.log(`🔔 DepositReceived Event Detected:`);
      console.log(`- User: ${user}`);
      console.log(`- Amount: ${formatEther(amount)} ETH`);

      // Update balance in the backend
      let userBalance = await contract.getUserBalance(user);
      await updateUserBalance(user, userBalance);
    });
  } catch (error) {
    console.error("🚨 Error in main function:", error.message);
  }
}

// Run the script
main().catch((error) => {
  console.error("🚨 Unexpected script error:", error.message);
  process.exit(1);
});
