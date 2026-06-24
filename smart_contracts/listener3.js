import { JsonRpcProvider, Wallet, Contract, formatEther } from "ethers";


import {
  LARAVEL_API_URL,
  INTERNAL_API_SECRET,
  BACKEND_URL,
  LOCAL_HARDHAT_URL,
  FUJI_RPC_URL,
  NODE_SERVER_PORT,
  GAME_WALLET_PK,
  MARKETPLACE_WALLET_PK,
  SECURITY_COEFFICIENT,
  pinata,
  contracts
} from "./config.js";

// Initialize provider, wallet, and contract
const provider = new JsonRpcProvider(FUJI_RPC_URL);
const wallet = new Wallet(GAME_WALLET_PK, provider);
const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

// Function to update user balance in the backend
async function updateUserBalance(user, balance) {
  try {
    const url = `${LARAVEL_API_URL}/internal/update-balance`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Secret': INTERNAL_API_SECRET
      },
      body: JSON.stringify({
        wallet_address: user,
        balance: formatEther(balance)
      })
    });

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
// Function to submit to handle pool emoted event
async function submitToHandlePoolEmitedEvent(poolId, baseBet, users, premoveCIDs, poolSalt, balances) {

  try {
    const url = `${LARAVEL_API_URL}/internal/handle-pool-emited`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Secret': INTERNAL_API_SECRET
      },
      body: JSON.stringify({
        pool_id: poolId.toString(),
        base_bet: baseBet.toString(),
        users: Array.isArray(users) ? users : users.split(','),
        premove_cids: Array.isArray(premoveCIDs) ? premoveCIDs : premoveCIDs.split(','),
        pool_salt: poolSalt,
        balances: Array.isArray(balances) ? balances.map(b => b.toString()) : []
      })
    });
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




// Function to handle stagnant pool refund event
async function handleStagnantRefund(poolId, refundedCount, timestamp) {
  try {
    const url = `${LARAVEL_API_URL}/internal/handle-stagnant-refund`;
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Internal-Secret': INTERNAL_API_SECRET
      },
      body: JSON.stringify({
        pool_id: poolId.toString(),
        refunded_count: parseInt(refundedCount),
        timestamp: parseInt(timestamp)
      })
    });

    if (response.ok) {
      console.log(`✅ Stagnant pool refund handled for poolId: ${poolId}. Refunded ${refundedCount} users.`);
    } else {
      const errorText = await response.text();
      console.error(`❌ Failed to handle stagnant refund for poolId: ${poolId}. Response: ${errorText}`);
    }
  } catch (error) {
    console.error(`🚨 Error handling stagnant pool refund:`, error.message);
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



    contract.on("PoolEmitted", async (poolId, baseBet, users, premoveCIDs, poolSalt, balances) => {

      console.log(`🔔 PoolEmitted Event Detected:`);
      console.log(`- User: ${users}`);
      console.log(`- baseBet: ${baseBet}`);
      console.log(`- poolId: ${poolId}`);
      console.log(`- premoveCIDs: ${premoveCIDs}`);
      console.log(`- poolSalt: ${poolSalt}`);
      console.log(`- balances: ${balances}`);

      // submit to handle pool emoted event
      await submitToHandlePoolEmitedEvent(poolId, baseBet, users, premoveCIDs, poolSalt, balances);
    });

    contract.on("PoolStagnantRefund", async (poolId, refundedCount, timestamp) => {
      console.log(`🔔 PoolStagnantRefund Event Detected:`);
      console.log(`- poolId: ${poolId}`);
      console.log(`- refundedCount: ${refundedCount}`);
      console.log(`- timestamp: ${timestamp}`);

      // Handle stagnant pool refund
      await handleStagnantRefund(poolId, refundedCount, timestamp);
    });

    contract.on("PlayerClaimed", async (wallet, amount, nonce) => {
      console.log(`🔔 PlayerClaimed Event Detected:`);
      console.log(`- wallet: ${wallet}`);
      console.log(`- amount: ${formatEther(amount)} ETH`);
      console.log(`- nonce: ${nonce}`);

      try {
        const url = `${LARAVEL_API_URL}/handle-claim`;
        const response = await fetch(url, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ wallet_address: wallet })
        });
        if (response.ok) {
          console.log(`✅ Claim handled successfully for wallet: ${wallet}`);
        } else {
          console.error(`❌ Failed to handle claim. Response: ${await response.text()}`);
        }
      } catch (error) {
        console.error(`🚨 Error handling claim event:`, error.message);
      }
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
