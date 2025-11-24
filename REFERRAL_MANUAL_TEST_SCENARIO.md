# Referral System - Manual Test Scenario

## Prerequisites ✅

- [ ] Laravel server running (`php artisan serve`)
- [ ] Node.js worker running (`node app.js`)
- [ ] Two different wallet accounts (Account A and Account B)

---

## Test Scenario: Referral Flow

### PART 1: Get Referral Code (User A)

1. **Login with Account A** (Referrer).
2. Navigate to **"Referrals"** menu.
3. **Verify:**
   - "Your Referral Code" is displayed (e.g., `REF-12345`).
   - Statistics (Total, Pending, Validated) are visible (likely 0).
4. **Action:** Click **"Copy"** to copy your code.

### PART 2: Apply Referral Code (User B)

1. **Logout** from Account A.
2. **Login with Account B** (Referee).
   - *Note: Ensure Account B is a new user or hasn't applied a code yet.*
3. **Action:**
   - If prompted on login "Have a Referral Code?", enter User A's code.
   - OR: Navigate to `http://127.0.0.1:8000/?ref=CODE_FROM_USER_A` and login.
4. **Verify:**
   - System accepts the code.
   - You are redirected to the Game or Autoplay page.

### PART 3: Verify Pending Status (User A)

1. **Logout** User B.
2. **Login with Account A**.
3. Go to **"Referrals"**.
4. **Verify:**
   - "Pending Referrals" count should be **1**.
   - "Total Referrals" count should be **1**.
   - "Validated Referrals" count should be **0**.

### PART 4: Validate Referral (Simulation or Real Trade)

*Validation happens automatically when User B holds at least **5 SNT**.*

**Option A: Real Trade (Recommended)**
1.  **Login as User B**.
2.  Go to **Marketplace**.
3.  **Buy at least 5 SNT** (e.g., find an offer for 5 SNT or more).
4.  Once the trade is fulfilled, the system checks your balance.
5.  If Balance >= 5 SNT, validation triggers automatically.

**Option B: Manual Validation (For Testing)**
1.  **Login as User A**.
2.  In the **"Manual Validation Panel"** (yellow box):
   - Enter **User B's ID**.
3.  Click **"🚀 Validate Referral"**.
4. **Verify:**
   - Success message appears: "✅ Success: Referral validated."
   - "Pending Referrals" count drops to **0**.
   - "Validated Referrals" count rises to **1**.
   - "Rewards" might update (depending on reward logic).

### PART 5: Leaderboard Check

1. Scroll down to **"Referral Leaderboard"**.
2. **Verify:**
   - User A should appear in the list.
   - Rank and referral count should reflect the recent validation.

---

## Troubleshooting

- **"Referral code not found"**: Ensure you copied the exact code.
- **"User already referred"**: Account B has already used a code. Create a new user/wallet.
- **"Validation failed"**: Ensure you entered the correct User ID for User B.

