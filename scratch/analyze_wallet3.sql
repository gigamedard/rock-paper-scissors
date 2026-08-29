SELECT @uid := id FROM users WHERE LOWER(wallet_address)='0x3c44cdddb6a900fa2b585dd299e03d12fa4293bc';

SELECT '=== SCHEMA transactions ===' AS section;
SHOW COLUMNS FROM transactions;

SELECT '=== BILAN FIGHTS ===' AS section;
SELECT
  COUNT(*) AS total,
  SUM(CASE WHEN (f.user1_id=@uid AND f.result='user1_win') OR (f.user2_id=@uid AND f.result='user2_win') THEN 1 ELSE 0 END) AS victoires,
  SUM(CASE WHEN (f.user1_id=@uid AND f.result='user2_win') OR (f.user2_id=@uid AND f.result='user1_win') THEN 1 ELSE 0 END) AS defaites,
  SUM(CASE WHEN f.result='draw' THEN 1 ELSE 0 END) AS draws
FROM fights f WHERE (f.user1_id=@uid OR f.user2_id=@uid) AND f.status='completed';

SELECT '=== CADENCE par minute ===' AS section;
SELECT DATE_FORMAT(created_at, '%H:%i') AS minute, COUNT(*) n
FROM fights WHERE user1_id=@uid OR user2_id=@uid GROUP BY minute ORDER BY minute;

SELECT '=== EVENEMENTS BLOCKCHAIN (indexeur) ===' AS section;
SHOW COLUMNS FROM processed_blockchain_events;