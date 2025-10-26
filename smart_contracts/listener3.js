import { JsonRpcProvider, Wallet, Contract, formatEther } from "ethers";
import { contractAddress3, privateKey3, localHardhatUrl, abi3 ,backendUrl, INNER_SCRIPT_TOKEN} from "./_config.js";

// Initialize provider, wallet, and contract
const provider = new JsonRpcProvider(localHardhatUrl);
const wallet = new Wallet(privateKey3, provider);
const contract = new Contract(contractAddress3, abi3, wallet);

// Function to update user balance in the backend
async function updateUserBalance(user, balance) {
  try {
    const url = `http://127.0.0.1:8000/update-balance?balance=${formatEther(balance)}&wallet_address=${user}`;
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

// Function to submit to handle pool emoted event
async function submitToHandlePoolEmitedEvent(poolId, baseBet,users,premoveCIDs,poolSalt) {

  try {
    const url = `http://${backendUrl}/handle-pool-emited?token=${INNER_SCRIPT_TOKEN}&pool_id=${poolId}&base_bet=${baseBet}&users=${users}&premove_cids=${premoveCIDs}&pool_salt=${poolSalt}`;
    const response = await fetch(url);
    console.log(url);

    if (response.ok) {
      console.log(`✅ Submitted to handle pool emited event successfully for poolId: ${poolId}`);
    } else {
      const errorText = await response.text();
      console.error(`❌ Failed to submit to handle pool emited event for poolId: ${poolId}. Response: ${errorText}`);
    }
  } catch (error) {
    console.error(`🚨 Error while submitting to handle pool emoted event:`, error.message);
  }


}




// Main function to listen for DepositReceived events
async function main() {
  try {
    console.log("🔊 Listening for events...");
    contract.on("DepositReceived", async (user, balance) => {
      console.log(`🔔 DepositReceived Event Detected:`);
      console.log(`-👨 User: ${user}`);
      console.log(`- 💰balance: ${formatEther(balance)} ETH`);

      // Update balance in the backend
    
      await updateUserBalance(user, balance);
    });

    contract.on("MatchHistoryCIDUpdated", async (poolId, cid) => {
      console.log(`🔔 MatchHistoryCIDUpdated Event Detected:`);
      console.log(`--- poolId: ${poolId}`);
      console.log(`- 💰cid: ${cid}`);
    });



    contract.on("PoolEmitted", async (poolId, baseBet,users,premoveCIDs,poolSalt) => {

      console.log(`🔔 PoolEmitted Event Detected:`);
      console.log(`- User: ${users}`);
      console.log(`- baseBet: ${baseBet}`);
      console.log(`- poolId: ${poolId}`);
      console.log(`- premoveCIDs: ${premoveCIDs}`);
      console.log(`- poolSalt: ${poolSalt}`);

      // submit to handle pool emoted event
      await submitToHandlePoolEmitedEvent(poolId, baseBet,users,premoveCIDs,poolSalt);
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
