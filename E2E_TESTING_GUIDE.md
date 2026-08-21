# E2E Testing Guide - Rock Paper Scissors Game

## Quick Reference
This guide walks you through testing the complete end-to-end flow of users joining pools and playing the game on the Avalanche Fuji testnet.

---

## Prerequisites

### Required Services Running
```bash
# Terminal 1: Laravel Server
cd d:\dev\PHP\rock-paper-scissors
php artisan serve

# Terminal 2: Node.js Worker
cd d:\dev\PHP\rock-paper-scissors\smart_contracts
node app.js
```

### Environment Check
- ✅ MetaMask or Avalanche Core Wallet installed
- ✅ Fuji testnet AVAX in your wallet (for gas fees)
- ✅ Pinata API keys configured in `smart_contracts/config.js`

---

## Step-by-Step Test Flow

### Step 1: Deploy New Contract (Optional)
> **When**: Only needed if you want a fresh contract or made contract changes

```bash
cd d:\dev\PHP\rock-paper-scissors\battlepool
npx hardhat ignition deploy ignition/modules/Battlepool.js --network fuji
```

**Output**: You'll get a new contract address like:
```
Deployed Battlepool to: 0xa991a873cdfe6B58Affd21697D977C79Fa7ba356
```

**Action**: Copy this address for the next step.

---

### Step 2: Update Contract Configuration

**File**: `smart_contracts/config.js`

**Location**: Line ~28 (in the `contracts.game` object)

**Update**:
```javascript
export const contracts = {
  game: {
    address: "0xa991a873cdfe6B58Affd21697D977C79Fa7ba356", // ← Paste your new address here
    abi: [ /* ... keep existing ABI ... */ ]
  },
  // ...
};
```

**Save the file** and **restart the Node.js worker**:
```bash
# In Terminal 2 (where node app.js is running)
# Press Ctrl+C to stop
node app.js  # Restart
```

---

### Step 3: Set Contract Parameters

**Script**: `smart_contracts/set_parameters.js`

**Configuration** (lines 16-19):
```javascript
const NOUVEAU_COEFFICIENT = 1;      // Security coefficient (1 = 1x deposit)
const NOUVELLE_TAILLE_MAX = 3;      // Pool size (2-10 players)
```

**Run**:
```bash
cd d:\dev\PHP\rock-paper-scissors\smart_contracts
node set_parameters.js
```

**Expected Output**:
```
🚀 Connexion au réseau Fuji...
   - Contrat: 0xa991a873cdfe6B58Affd21697D977C79Fa7ba356
   - Propriétaire (Owner): 0x13681EbA8A5eFDBB53e5689c16C86014eA2DBe16

--- Mise à jour du Security Coefficient ---
   Valeur actuelle : 1
   Valeur déjà à jour.

--- Mise à jour de la Default Pool Max Size ---
   Valeur actuelle : 3
   Valeur déjà à jour.

🏁 Paramètres mis à jour !
```

---

### Step 4: Run Headless Simulation

**Script**: `smart_contracts/simulate_headless.js`

**Configuration** (lines 26-27):
```javascript
const BASE_BET_ETH = "0.001";        // Base bet amount
const SECURITY_COEFFICIENT = 1n;     // Must match contract setting
```

**Test Accounts**: The script uses 5 predefined test accounts (lines 30-51)

**Run**:
```bash
cd d:\dev\PHP\rock-paper-scissors\smart_contracts
node simulate_headless.js
```

**Expected Output** (per user):
```
--- Simulation pour l'utilisateur: 0xdded5d7d8171b68b6105236164bca7a45839d150 ---
  [1/5] Authentification...
  [1/5] Authentification réussie. (Token obtenu)
  [2/5] Upload sur Pinata...
  [2/5] Upload Pinata réussi. CID: QmU6YamnoPDWE2WUc7AYH1qHvAoNG8zn29Y4XvF51v1Doe
  [3/5] Soumission au backend Laravel...
  [3/5] Soumission au backend réussie.
  [4/5] Soumission à la Blockchain...
  [4/5] Transaction envoyée. En attente de confirmation...
  [5/5] Transaction confirmée ! Hash: 0xa80a356b18efeb0b3b3dc5c20...
    💎 NOUVEAU SOLDE DU CONTRAT: 0.001 ETH
✅ SIMULATION RÉUSSIE pour 0xdded5d7d8171b68b6105236164bca7a45839d150
```

**Success Criteria**:
- ✅ All 5 users complete without errors
- ✅ Contract balance increases: 0.001 → 0.002 → 0.003 → 0.004 → 0.005 ETH
- ✅ Each transaction gets a hash (can verify on [Snowtrace Testnet](https://testnet.snowtrace.io/))

---

### Step 5: Verify Results

#### A. Check Laravel Logs
```bash
cd d:\dev\PHP\rock-paper-scissors
powershell -Command "Get-Content storage\logs\laravel.log -Tail 50"
```

**Look for**:
```
User balance updated: Address: 0xdded..., Balance: 0.001000000000000000 ETH (from 1000000000000000 wei)
```

#### B. Check Database
```bash
php artisan tinker
```

```php
// Check users
User::whereIn('wallet_address', [
    '0xdded5d7d8171b68b6105236164bca7a45839d150',
    '0xdafe2be78f32d151f45ef18bcf7e32cb9e6da506'
])->get(['wallet_address', 'balance']);

// Check pre-moves
PreMove::latest()->take(5)->get(['user_id', 'cid', 'created_at']);

// Check pools
Pool::latest()->take(3)->get(['id', 'base_bet', 'pool_size', 'status']);
```

#### C. Check Blockchain
Visit [Snowtrace Fuji Testnet](https://testnet.snowtrace.io/) and search for:
- Your contract address
- Transaction hashes from the simulation output

```

---

## Quick Test (Single User)

For a faster test with just one user:

**File**: `smart_contracts/test_balance_conversion.js`

```bash
cd d:\dev\PHP\rock-paper-scissors\smart_contracts
node test_balance_conversion.js
```

**Time**: ~30 seconds vs ~2-3 minutes for full simulation

---

## Test Variations

### Test Different Pool Sizes
**Edit**: `smart_contracts/set_parameters.js` line 19
```javascript
const NOUVELLE_TAILLE_MAX = 2;  // Try: 2, 3, 5, 10
```

### Test Different Bet Amounts
**Edit**: `smart_contracts/simulate_headless.js` line 26
```javascript
const BASE_BET_ETH = "0.01";  // Try: 0.001, 0.01, 0.1
```

### Test Different Security Coefficients
**Edit both files**:
- `smart_contracts/set_parameters.js` line 16
- `smart_contracts/simulate_headless.js` line 27

```javascript
const NOUVEAU_COEFFICIENT = 100;     // set_parameters.js
const SECURITY_COEFFICIENT = 100n;   // simulate_headless.js
```

---

## Expected Timeline

| Step | Time | Notes |
|------|------|-------|
| Deploy Contract | 1-2 min | Only if needed |
| Update Config | 30 sec | Manual edit |
| Set Parameters | 30 sec | 2 transactions |
| Run Simulation (5 users) | 2-3 min | 5 transactions |
| Verify Results | 1 min | Check logs/DB |
| **Total** | **4-7 min** | Full E2E test |

---

## Success Checklist

After running the simulation, verify:

- [ ] All 5 users show "✅ SIMULATION RÉUSSIE"
- [ ] Contract balance = 0.005 ETH (for 5 users × 0.001 ETH)
- [ ] Laravel logs show balance conversions (wei → ETH)
- [ ] Database has 5 new users with correct balances
- [ ] Database has 5 new pre-moves with IPFS CIDs
- [ ] Transactions visible on Snowtrace Fuji
- [ ] No errors in Laravel logs
- [ ] No errors in Node.js worker console

---

## Cleanup (Optional)

### Reset Database
```bash
php artisan migrate:fresh --seed
```

### Clear Logs
```bash
# Windows PowerShell
Clear-Content storage\logs\laravel.log
```

### Deploy Fresh Contract
```bash
cd battlepool
npx hardhat ignition deploy ignition/modules/Battlepool.js --network fuji
```

---

## Quick Command Reference

```bash
# Start servers
php artisan serve                    # Terminal 1
node app.js                          # Terminal 2 (in smart_contracts/)

# Run tests
node set_parameters.js               # Configure contract
node simulate_headless.js            # Full E2E test (5 users)
node test_balance_conversion.js      # Quick test (1 user)

# Check results
php artisan tinker                   # Database inspection
Get-Content storage\logs\laravel.log -Tail 50  # View logs
```

---

## Notes

- **Network**: All tests run on Avalanche Fuji Testnet
- **Gas Fees**: Paid in AVAX (testnet tokens are free)
- **IPFS**: Files stored on Pinata (permanent storage)
- **Database**: Local MySQL/MariaDB
- **Test Accounts**: 5 predefined accounts with known private keys (for testing only!)

⚠️ **Security Warning**: Never use test account private keys on mainnet!

---

## Support

If you encounter issues:
1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Node.js console output
3. Verify all services are running
4. Check Fuji testnet status: [status.avax.network](https://status.avax.network/)

---

**Last Updated**: 2025-11-22
**Contract Address**: `0xa991a873cdfe6B58Affd21697D977C79Fa7ba356`
**Network**: Avalanche Fuji Testnet
