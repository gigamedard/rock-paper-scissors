SELECT '=== TRADES ===' AS section;
SELECT id, blockchain_offer_id, seller_address, snt_amount, avax_amount, status, created_at FROM trades ORDER BY id DESC LIMIT 5;

SELECT '=== EVENEMENTS INDEXEUR (offre) ===' AS section;
SELECT id, event_name, block_number, status, created_at FROM processed_blockchain_events WHERE event_name LIKE '%Offer%' ORDER BY id DESC LIMIT 5;

SELECT '=== SYNC STATES ===' AS section;
SELECT contract_address, last_processed_block, updated_at FROM blockchain_sync_states;

SELECT '=== DERNIERS EVENTS TRAITES ===' AS section;
SELECT id, event_name, block_number, status FROM processed_blockchain_events ORDER BY id DESC LIMIT 5;