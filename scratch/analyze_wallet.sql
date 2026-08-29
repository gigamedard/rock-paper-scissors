SELECT '=== USER ===' AS section;
SELECT id, wallet_address, balance, status, autoplay_active, bet_amount, pool_id, created_at, updated_at
FROM users WHERE LOWER(wallet_address)='0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc'\G

SELECT '=== USER_ID pour jointures ===' AS section;
SELECT @uid := id FROM users WHERE LOWER(wallet_address)='0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc';

SELECT '=== PRE_MOVES ===' AS section;
SELECT id, user_id, cid, current_index, created_at FROM pre_moves WHERE user_id=@uid ORDER BY id DESC LIMIT 5;

SELECT '=== POOLS (actuelles) ===' AS section;
SELECT p.id, p.base_bet, p.status, p.pool_size, p.created_at
FROM pools p WHERE JSON_VALID(p.users) AND p.users LIKE '%3c44cdddb6a900fa2b585dd299e03d12fa4293bc%'
ORDER BY p.id DESC LIMIT 10;

SELECT '=== FIGHTS (impliqué) ===' AS section;
SELECT f.id, f.pool_id, f.status, f.winner_id, f.loser_id, f.bet_amount, f.created_at
FROM fights f WHERE f.user1_id=@uid OR f.user2_id=@uid OR f.winner_id=@uid OR f.loser_id=@uid
ORDER BY f.id DESC LIMIT 15;

SELECT '=== FIGHTS stats ===' AS section;
SELECT status, COUNT(*) n FROM fights WHERE user1_id=@uid OR user2_id=@uid GROUP BY status;

SELECT '=== TRANSACTIONS ===' AS section;
SELECT id, type, amount, status, created_at FROM transactions WHERE user_id=@uid ORDER BY id DESC LIMIT 15;

SELECT '=== SESSIONS (historique) ===' AS section;
SELECT COUNT(*) n FROM sessions WHERE user_id=@uid;

SELECT '=== MATCH_HISTORIES ===' AS section;
SELECT id, user_id, created_at FROM match_histories WHERE user_id=@uid ORDER BY id DESC LIMIT 5;

SELECT '=== F_HISTS ===' AS section;
SELECT COUNT(*) n FROM f_hists WHERE user_id=@uid;