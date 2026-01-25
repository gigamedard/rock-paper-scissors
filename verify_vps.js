
import fetch from 'node-fetch';

const VPS_URL = "https://srv1198092.hstgr.cloud/api";
const SECRET = "0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086";

async function verifyInternalAuth() {
    console.log("Testing VPS connection...");

    // Test data for update-balance
    const payload = {
        wallet_address: "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266", // Test address
        balance: "1000000000000000000" // 1 ETH in Wei
    };

    try {
        const response = await fetch(`${VPS_URL}/internal/update-balance`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Internal-Secret': SECRET,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        console.log(`Status Code: ${response.status}`);
        const text = await response.text();
        console.log(`Response Body: ${text.substring(0, 500)}...`);

        if (response.status === 500) {
            console.error("❌ FAILURE: Received 500 Server Error. The fix is NOT on the VPS (or failed).");
        } else if (response.status === 200) {
            console.log("✅ SUCCESS: Received 200 OK. The VPS accepted the request.");
        } else {
            console.log(`⚠️ RECEIVED ${response.status}. This is not 500, so the specific middleware crash might be fixed.`);
        }

    } catch (error) {
        console.error("❌ EXECUTION ERROR:", error.message);
    }
}

verifyInternalAuth();
