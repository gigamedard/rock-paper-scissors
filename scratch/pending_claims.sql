SELECT '=== USERS avec signature en attente ===' AS section;
SELECT id, wallet_address, balance, battle_balance, status, session_started, cooldown_until, payout_deadline, updated_at
FROM users WHERE payout_signature IS NOT NULL\G