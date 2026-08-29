SELECT @uid := id FROM users WHERE LOWER(wallet_address)='0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc';

SELECT '=== POOLS impliqué (via premove_cids) ===' AS section;
SELECT id, pool_id, base_bet, status, pool_size, created_at
FROM pools WHERE JSON_SEARCH(premove_cids, 'one', '%3c44%') IS NOT NULL OR premove_cids LIKE '%3c44cdddb6a900fa2b585dd299e03d12fa4293bc%'
ORDER BY id DESC LIMIT 10;

SELECT '=== FIGHTS stats ===' AS section;
SELECT status, COUNT(*) n FROM fights WHERE user1_id=@uid OR user2_id=@uid GROUP BY status;

SELECT '=== FIGHTS résultats ===' AS section;
SELECT result, COUNT(*) n FROM fights WHERE (user1_id=@uid OR user2_id=@uid) AND status='completed' GROUP BY result;

SELECT '=== 12 DERNIERS FIGHTS ===' AS section;
SELECT f.id, f.pool_id, f.status, f.result, f.base_bet_amount,
       f.user1_chosed, f.user2_chosed,
       CASE WHEN f.user1_id=@uid THEN 'MOI' ELSE f.user1_id END AS user1,
       CASE WHEN f.user2_id=@uid THEN 'MOI' ELSE f.user2_id END AS user2,
       f.created_at
FROM fights f WHERE f.user1_id=@uid OR f.user2_id=@uid ORDER BY f.id DESC LIMIT 12;

SELECT '=== TRANSACTIONS ===' AS section;
SELECT id, user_id, type, amount, status, created_at FROM transactions WHERE user_id=@uid ORDER BY id DESC LIMIT 15;

SELECT '=== POOL ACTUEL 157 ===' AS section;
SELECT id, pool_id, base_bet, status, pool_size, created_at FROM pools WHERE id=157;

SELECT '=== SESSIONS ===' AS section;
SELECT COUNT(*) n FROM sessions WHERE user_id=@uid;