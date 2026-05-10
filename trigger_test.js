const TRIGGER_URL = 'http://127.0.0.1:8001/api/debug/trigger-event';
// Default wallet (User ID 2 in your DB)
let DEFAULT_WALLET = '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266';

async function trigger(type, value = null, customWallet = null) {
    const wallet = customWallet || DEFAULT_WALLET;
    console.log(`🚀 Dispatching debug signal: ${type} for wallet ${wallet}...`);
    
    try {
        const response = await fetch(TRIGGER_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: type,
                wallet: wallet,
                value: value
            })
        });

        const data = await response.json();
        
        if (response.ok) {
            console.log(`✅ DISPATCHED: ${data.message}`);
        } else {
            console.error(`❌ ERROR: ${data.error}`);
        }
    } catch (e) {
        console.error(`🚨 FATAL: ${e.message}`);
    }
}

const args = process.argv.slice(2);
if (args.length === 0) {
    console.log("Usage:");
    console.log("  node trigger_test.js balance 123.45 [wallet] ");
    console.log("  node trigger_test.js fight win|loss [wallet] ");
    console.log("  node trigger_test.js discovery      [wallet] ");
    process.exit(1);
}

// node trigger_test.js [type] [value] [wallet]
trigger(args[0], args[1], args[2]);
