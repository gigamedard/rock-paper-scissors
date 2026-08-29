SELECT '=== USER 2 (toutes colonnes) ===' AS section;
SELECT * FROM users WHERE id=2\G

SELECT '=== SESSIONS user 2 ===' AS section;
SELECT id, user_id, status, base_bet, created_at, updated_at FROM sessions WHERE user_id=2 ORDER BY id DESC LIMIT 3;