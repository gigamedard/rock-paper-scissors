import fetch from 'node-fetch';

const LARAVEL_API_URL = "http://127.0.0.1:8000/api";
const INTERNAL_API_SECRET = "0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086";

async function testPoolEmitedEndpoint() {
    console.log("🧪 Testing /internal/handle-pool-emited endpoint...");

    const url = `${LARAVEL_API_URL}/internal/handle-pool-emited`;
    const body = {
        pool_id: "999",
        base_bet: "1000000000000000",
        users: ["0x1234567890123456789012345678901234567890", "0xabcdefabcdefabcdefabcdefabcdefabcdefabcd"],
        premove_cids: ["QmTest1", "QmTest2"],
        pool_salt: "test_salt_123"
    };

    console.log(`   URL: ${url}`);
    console.log(`   Secret: ${INTERNAL_API_SECRET.substring(0, 20)}...`);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Internal-Secret': INTERNAL_API_SECRET
            },
            body: JSON.stringify(body)
        });

        console.log(`   Status: ${response.status}`);
        const text = await response.text();
        console.log(`   Response: ${text}`);

        if (response.ok) {
            console.log("   ✅ SUCCESS!");
        } else {
            console.log("   ❌ FAILED!");
        }
    } catch (error) {
        console.error(`   ❌ ERROR: ${error.message}`);
    }
}

testPoolEmitedEndpoint();
