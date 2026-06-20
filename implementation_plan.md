# Smart Contract Cooldown Sync Plan

The user discovered that while the Laravel UI correctly retroactively reduced the cooldown, the blockchain still rejected the transaction. This occurs because the blockchain maintains its own state (`nextSessionAllowedTime`) which is normally only updated *during* a match resolution. 

## User Review Required
> [!WARNING]
> We need to modify and redeploy the smart contract (`Battlepool.sol`) to allow the backend to override the active cooldown time freely. Redeploying the contract locally will reset the game state (pools, balances) on your local blockchain, but it's required for this fix.

## Open Questions
- Are you okay with resetting the local smart contract state to apply this fix?

## Proposed Changes

### 1. Smart Contract Modification
#### [MODIFY] battlepool/contracts/Battlepool.sol
Remove the over-protective `require` statement in `setUserNextSessionTime` that prevents the owner (backend) from shortening an active cooldown.

```diff
     function setUserNextSessionTime(address user, uint256 nextTime) external onlyOwner {
-        require(nextTime == 0 || nextTime + 300 >= block.timestamp + getUserMinCooldown(user), "Cooldown is too short");
         nextSessionAllowedTime[user] = nextTime;
         emit NextSessionTimeUpdated(user, nextTime);
     }
```

### 2. Laravel Backend Update
#### [MODIFY] app/Jobs/VerifyCardPurchaseJob.php
After modifying the `cooldown_until` in Laravel, we need to explicitly push this new timestamp to the blockchain via the `Web3Helper`.

### 3. Execution & Redeployment
We will re-deploy the contracts locally:
1. `npx hardhat run full_deploy.js --network localhost`
2. Restart the Node.js Bridge and Laravel API to pick up the new contract addresses.

## Verification Plan
We will re-run the `test_retroactive_cooldown.php` script, but with the full bridge enabled, to ensure the RPC calls to `setUserNextSessionTime` succeed without throwing "Cooldown is too short".
