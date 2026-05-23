import { JsonRpcProvider, Wallet, Contract, parseEther, ethers, formatEther } from "ethers";
import fetch from "node-fetch";
import { execSync } from "child_process";
import {
    LARAVEL_API_URL,
    LOCAL_HARDHAT_URL,
    contracts,
} from "./config.js";

const INTERNAL_API_SECRET = "0x7c852118294e51e653712a81e05800f419141751be58f605c371e18990756086";
const SNT_OWNER = "0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D";

const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);

// Standard phrase for Hardhat accounts
const mnemonic = "test test test test test test test test test test test junk";

function getWalletForIndex(index) {
    const hdNode = ethers.HDNodeWallet.fromPhrase(mnemonic, undefined, `m/44'/60'/0'/0/${index}`);
    return new Wallet(hdNode.privateKey, provider);
}

// Helper to interact with PHP database helper
function runDbHelper(action, ...args) {
    const cmd = `php db_helper.php ${action} ${args.join(" ")}`;
    // db_helper.php sits at the project root; resolve from this script's location
    const projectRoot = new URL("../", import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, "$1");
    try {
        const output = execSync(cmd, { cwd: projectRoot }).toString();
        return JSON.parse(output);
    } catch (e) {
        console.error(`❌ DB Helper command failed: ${cmd}`, e.message);
        throw e;
    }
}

// Authentication function for bots
async function authenticateBot(botIndex, wallet) {
    const address = wallet.address.toLowerCase();
    
    // Step A: message
    const msgRes = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ wallet_address: address })
    });
    const msgData = await msgRes.json();
    if (!msgData.message) throw new Error(`Generate message failed: ` + JSON.stringify(msgData));

    // Step B: sign
    const signature = await wallet.signMessage(msgData.message);

    // Step C: verify
    const verifyRes = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({
            wallet_address: address,
            signature: signature,
            message: msgData.message
        })
    });
    const tokenData = await verifyRes.json();
    if (!tokenData.token) throw new Error(`Verification failed: ` + JSON.stringify(tokenData));

    return {
        token: tokenData.token,
        userId: tokenData.user.id
    };
}

// IPFS upload and submit moves to Laravel
async function registerBotMoves(botIndex, wallet, authToken, userId, moves, betAmount = "0.01") {
    const address = wallet.address.toLowerCase();
    
    // IPFS upload
    const pinataData = {
        user_id: userId,
        wallet_address: address,
        moves: moves,
        timestamp: new Date().toISOString()
    };
    const ipfsRes = await fetch(`${LARAVEL_API_URL}/ipfs/upload`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "Authorization": `Bearer ${authToken}` },
        body: JSON.stringify({ data: pinataData })
    });
    const ipfsData = await ipfsRes.json();
    if (!ipfsData.IpfsHash) throw new Error(`IPFS upload failed: ` + JSON.stringify(ipfsData));
    const preMoveCid = ipfsData.IpfsHash;

    // Store in Laravel
    const movesRes = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${authToken}`
        },
        body: JSON.stringify({
            user_id: userId,
            pre_moves: moves,
            cid: preMoveCid,
            bet_amount: betAmount
        })
    });
    if (!movesRes.ok) {
        const err = await movesRes.json();
        throw new Error(`Laravel store moves failed: ` + JSON.stringify(err));
    }
    return preMoveCid;
}

// Call on-chain submitPremoveCID
async function submitPremoveOnChain(wallet, baseBet, preMoveCid) {
    const gameContract = new Contract(contracts.game.address, contracts.game.abi, wallet);
    
    // Read parameters dynamically
    let config = { security_coefficient: 100, smart_contract_fee_percentage: 2.5 };
    try {
        const res = await fetch(`${LARAVEL_API_URL}/artefacts`);
        if (res.ok) {
            config = await res.json();
        }
    } catch (e) {}

    const securityCoefficient = config.security_coefficient || 100;
    const feePercentage = config.smart_contract_fee_percentage || 2.5;

    const stakeWei = parseEther((parseFloat(baseBet) * securityCoefficient).toFixed(18));
    const feeWei = (stakeWei * BigInt(Math.round(feePercentage * 100))) / 10000n;
    const amountToSendWei = stakeWei + feeWei;

    const tx = await gameContract.submitPremoveCID(parseEther(baseBet), preMoveCid, {
        value: amountToSendWei,
        gasLimit: 500000
    });
    await tx.wait();
}

// Execute Matchmaking Cycles
async function runMatchmakerCycle() {
    // 1. internal-pools
    await fetch(`${LARAVEL_API_URL}/internal/internal-pools`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Internal-Secret': INTERNAL_API_SECRET
        },
        body: JSON.stringify({ base_bet: "0.01" })
    });
    await fetch(`${LARAVEL_API_URL}/internal/internal-pools`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Internal-Secret': INTERNAL_API_SECRET
        },
        body: JSON.stringify({ base_bet: "0.02" })
    });

    // 2. batch-processing-all
    await fetch(`${LARAVEL_API_URL}/internal/batch-processing-all`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Internal-Secret': INTERNAL_API_SECRET
        },
        body: JSON.stringify({})
    });
}

// Wait for a user's status to become 'available' (after bridge processes block)
async function waitForUserAvailable(walletAddress, maxAttempts = 30) {
    for (let i = 0; i < maxAttempts; i++) {
        const u = runDbHelper("get_user", walletAddress);
        if (u.status === "available") {
            return u;
        }
        await new Promise(r => setTimeout(r, 1000));
    }
    throw new Error(`Timeout waiting for user ${walletAddress} to become available`);
}

// Wait for a user's status to become a specific status
async function waitForUserStatus(walletAddress, targetStatus, maxAttempts = 30) {
    for (let i = 0; i < maxAttempts; i++) {
        const u = runDbHelper("get_user", walletAddress);
        if (u.status === targetStatus) {
            return u;
        }
        await new Promise(r => setTimeout(r, 1000));
    }
    throw new Error(`Timeout waiting for user ${walletAddress} to become ${targetStatus}`);
}

// Fund an account with SNT using Hardhat Impersonation
async function fundAccountWithSnt(toAddress, amountToken) {
    const defaultAccount = getWalletForIndex(11);
    // Ensure SNT owner has gas money
    const nonce = await provider.getTransactionCount(defaultAccount.address, "pending");
    const fundTx = await defaultAccount.sendTransaction({
        to: SNT_OWNER,
        value: parseEther("1.0"),
        nonce: nonce
    });
    await fundTx.wait();

    // Impersonate owner
    await provider.send("hardhat_impersonateAccount", [SNT_OWNER]);
    const signer = await provider.getSigner(SNT_OWNER);

    const sntAbi = [
        ...contracts.snt.abi,
        { "constant": false, "inputs": [{ "name": "to", "type": "address" }, { "name": "amount", "type": "uint256" }], "name": "transfer", "outputs": [{ "name": "", "type": "bool" }], "payable": false, "stateMutability": "nonpayable", "type": "function" }
    ];
    const sntContract = new Contract(contracts.snt.address, sntAbi, signer);

    const tx = await sntContract.transfer(toAddress, parseEther(amountToken));
    await tx.wait();

    // Stop impersonating
    await provider.send("hardhat_stopImpersonatingAccount", [SNT_OWNER]);
}

// Perform SNT purchase of card via shop API
async function buyCard(wallet, cardId, priceToken, authToken) {
    const address = wallet.address.toLowerCase();

    // Transfer SNT to SNT owner address on-chain
    const sntAbi = [
        ...contracts.snt.abi,
        { "constant": false, "inputs": [{ "name": "to", "type": "address" }, { "name": "amount", "type": "uint256" }], "name": "transfer", "outputs": [{ "name": "", "type": "bool" }], "payable": false, "stateMutability": "nonpayable", "type": "function" }
    ];
    const sntContract = new Contract(contracts.snt.address, sntAbi, wallet);
    const tx = await sntContract.transfer(SNT_OWNER, parseEther(priceToken));
    await tx.wait();

    // Hit Laravel shop buy API
    const res = await fetch(`${LARAVEL_API_URL}/shop/buy`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": `Bearer ${authToken}`
        },
        body: JSON.stringify({
            card_id: cardId,
            tx_hash: tx.hash
        })
    });
    const data = await res.json();
    if (!res.ok) {
        throw new Error(`Shop buy API failed: ` + JSON.stringify(data));
    }
    return data;
}

async function main() {
    console.log("⚡ Starting E2E Simulation Complex Test Suite...");

    // Configure contract defaultPoolMaxSize to 2 for simulation
    const ownerWallet = getWalletForIndex(0);
    const gameContractWithOwner = new Contract(contracts.game.address, contracts.game.abi, ownerWallet);
    console.log("   - Setting defaultPoolMaxSize to 2...");
    const nonce = await provider.getTransactionCount(ownerWallet.address, "pending");
    const setTx = await gameContractWithOwner.setDefaultPoolMaxSize(2, { nonce });
    await setTx.wait();

    const gameContract = new Contract(contracts.game.address, contracts.game.abi, provider);

    // =========================================================================
    // SCENARIO A: Pas de Cartes Activées (Nominal Cooldown 2 min)
    // =========================================================================
    console.log("\n========================================================");
    console.log("✊ SCENARIO A: Nominal Session & Cooldown Verification");
    console.log("========================================================");
    
    const bot2 = getWalletForIndex(2);
    const bot6 = getWalletForIndex(6);

    const bot2Auth = await authenticateBot(2, bot2);
    const bot6Auth = await authenticateBot(6, bot6);

    // Set moves: Bot #2 wins, Bot #6 loses
    const bot2Moves = Array(10).fill("rock");
    const bot6Moves = Array(10).fill("scissors");

    console.log("   - Registering bot moves in Laravel...");
    const cid2 = await registerBotMoves(2, bot2, bot2Auth.token, bot2Auth.userId, bot2Moves);
    const cid6 = await registerBotMoves(6, bot6, bot6Auth.token, bot6Auth.userId, bot6Moves);

    // Force session_started=true + session_start_balance BEFORE on-chain submit
    // This prevents PoolReconstructionService from overwriting session_start_balance (line 191)
    console.log("   - Pre-marking Bot #2 session_started=true + session_start_balance=0.93 + bet_amount=0.01...");
    runDbHelper("set_balance", bot2.address, "1.0");
    runDbHelper("force_session_start", bot2.address, "0.93", "0.01");

    console.log("   - Submitting pre-moves on-chain for Bot #2 and Bot #6...");
    await submitPremoveOnChain(bot2, "0.01", cid2);
    await submitPremoveOnChain(bot6, "0.01", cid6);
    // Pool is now full (size 2) → PoolEmitted fires → Bridge → handle-pool-emited → fight + session end

    console.log("   - Waiting for Bot #2 session to complete (status: stopped)...");
    await waitForUserStatus(bot2.address, "stopped");

    // Check next allowed session time
    const nextSessionTimeA = await gameContract.nextSessionAllowedTime(bot2.address);
    const blockA = await provider.getBlock("latest");
    const diffA = Number(nextSessionTimeA) - blockA.timestamp;
    console.log(`   - Bot #2 next session allowed time: ${nextSessionTimeA} (diff: ${diffA} seconds)`);

    if (diffA > 80 && diffA <= 130) {
        console.log("💚 SCENARIO A: SUCCESS (Nominal cooldown correctly set to 2 minutes)");
    } else {
        throw new Error(`Scenario A FAIL: Cooldown diff is ${diffA} seconds, expected ~120 (got ${diffA})`);
    }

    // =========================================================================
    // SCENARIO B: Cartes Actives (Base Bet Modifier, Ceiling Increase, Cooldown Reduction)
    // =========================================================================
    console.log("\n========================================================");
    console.log("🃏 SCENARIO B: Active Advantage Cards (3 Bonus types)");
    console.log("========================================================");

    const bot3 = getWalletForIndex(3);
    const bot7 = getWalletForIndex(7);

    console.log("   - Funding Bot #3 with SNT tokens...");
    await fundAccountWithSnt(bot3.address, "1000.0");

    const bot3Auth = await authenticateBot(3, bot3);
    const bot7Auth = await authenticateBot(7, bot7);

    console.log("   - Bot #3 purchasing Advantage Cards...");
    await buyCard(bot3, 1, "50.0", bot3Auth.token); // Card 1: base_bet_modifier (+0.01)
    await buyCard(bot3, 2, "100.0", bot3Auth.token); // Card 2: ceiling_increase (+0.50)
    await buyCard(bot3, 3, "150.0", bot3Auth.token); // Card 3: cooldown_reduction (-50%)

    console.log("   - Granting base bet modifier card to Bot #7 so it can match the 0.02 tier...");
    runDbHelper("grant_card", bot7.address, 1); // grant Card 1 to Bot #7

    // Verify DB card state
    const user3Db = runDbHelper("get_user", bot3.address);
    console.log(`   - Bot #3 active limits in DB:`, user3Db.active_limits);

    // Bot #3 plays at 0.02 base bet tier
    const cid3 = await registerBotMoves(3, bot3, bot3Auth.token, bot3Auth.userId, Array(10).fill("rock"), "0.02");
    const cid7 = await registerBotMoves(7, bot7, bot7Auth.token, bot7Auth.userId, Array(10).fill("scissors"), "0.02");

    // Force session_started=true + session_start_balance BEFORE on-chain submit
    console.log("   - Pre-marking Bot #3 session_started=true + session_start_balance=1.2 + bet_amount=0.02...");
    runDbHelper("set_balance", bot3.address, "2.0");
    runDbHelper("force_session_start", bot3.address, "1.2", "0.02");

    console.log("   - Submitting pre-moves on-chain for Bot #3 and Bot #7...");
    await submitPremoveOnChain(bot3, "0.02", cid3);
    await submitPremoveOnChain(bot7, "0.02", cid7);
    // Pool full (size 2) → PoolEmitted → Bridge handles fight+session automatically

    console.log("   - Waiting for Bot #3 session to complete (status: stopped)...");
    await waitForUserStatus(bot3.address, "stopped");

    // Check next allowed session time
    const nextSessionTimeB = await gameContract.nextSessionAllowedTime(bot3.address);
    const blockB = await provider.getBlock("latest");
    const diffB = Number(nextSessionTimeB) - blockB.timestamp;
    console.log(`   - Bot #3 next session allowed time: ${nextSessionTimeB} (diff: ${diffB} seconds)`);

    if (diffB > 25 && diffB <= 70) {
        console.log("💚 SCENARIO B: SUCCESS (Active cards, ceiling target increased, cooldown reduced to 1 minute)");
    } else {
        throw new Error(`Scenario B FAIL: Cooldown diff is ${diffB} seconds, expected ~60 (got ${diffB})`);
    }

    // =========================================================================
    // SCENARIO C: Expiration & Consommation des Cartes
    // =========================================================================
    console.log("\n========================================================");
    console.log("⏳ SCENARIO C: Card Expiration and Consumption");
    console.log("========================================================");

    const bot4 = getWalletForIndex(4);
    const bot8 = getWalletForIndex(8);

    console.log("   - Funding Bot #4 with SNT tokens...");
    await fundAccountWithSnt(bot4.address, "1000.0");

    const bot4Auth = await authenticateBot(4, bot4);
    const bot8Auth = await authenticateBot(8, bot8);

    console.log("   - Bot #4 purchasing 1-session card (Card 1: base bet modifier)...");
    await buyCard(bot4, 1, "50.0", bot4Auth.token);

    // Grant Card 1 to Bot #8 to match 0.02 tier
    runDbHelper("grant_card", bot8.address, 1);

    const cid4 = await registerBotMoves(4, bot4, bot4Auth.token, bot4Auth.userId, Array(10).fill("rock"), "0.02");
    const cid8 = await registerBotMoves(8, bot8, bot8Auth.token, bot8Auth.userId, Array(10).fill("scissors"), "0.02");

    // Force session_started=true + session_start_balance BEFORE on-chain submit
    console.log("   - Pre-marking Bot #4 session_started=true + session_start_balance=1.2 + bet_amount=0.02...");
    runDbHelper("set_balance", bot4.address, "2.0");
    runDbHelper("force_session_start", bot4.address, "1.2", "0.02");

    await submitPremoveOnChain(bot4, "0.02", cid4);
    await submitPremoveOnChain(bot8, "0.02", cid8);
    // Pool full → PoolEmitted → Bridge auto-handles fight + session end

    console.log("   - Waiting for Bot #4 session to complete (status: stopped)...");
    await waitForUserStatus(bot4.address, "stopped");

    // Check that Bot #4 card status has become 'consumed'
    const bot4UserDb = runDbHelper("get_user", bot4.address);
    const cardStatus = bot4UserDb.cards[0].status;
    console.log(`   - Bot #4 card status after 1 session: ${cardStatus}`);

    if (cardStatus !== "consumed") {
        throw new Error(`Scenario C FAIL: expected card to be consumed, got ${cardStatus}`);
    }

    console.log("   - Bot #4 purchasing 1-hour temporal card (Card 4)...");
    await buyCard(bot4, 4, "200.0", bot4Auth.token);

    console.log("   - Forcing card expiration in database...");
    runDbHelper("expire_time_cards", bot4.address);

    const bot4UserDbAfterExpire = runDbHelper("get_user", bot4.address);
    console.log(`   - Bot #4 active limits after expiration:`, bot4UserDbAfterExpire.active_limits);

    if (bot4UserDbAfterExpire.active_limits.min_cooldown !== 2) {
        throw new Error(`Scenario C FAIL: expected cooldown limit to return to 2 (2 min), got ${bot4UserDbAfterExpire.active_limits.min_cooldown}`);
    }
    console.log("💚 SCENARIO C: SUCCESS (Session card consumed, hourly card successfully expired & ignored)");

    // =========================================================================
    // SCENARIO D: Martingale & Ruine
    // =========================================================================
    console.log("\n========================================================");
    console.log("📉 SCENARIO D: Martingale Doubling and Ruin");
    console.log("========================================================");

    // We set security_coefficient to 10 so required capital is 0.10 ETH for 0.01 ETH base bet
    console.log("   - Setting security_coefficient to 10 for Scenario D...");
    runDbHelper("set_setting", "security_coefficient", "10");
    const setCoefTx = await gameContractWithOwner.setSecurityCoefficient(10);
    await setCoefTx.wait();

    const bot5 = getWalletForIndex(5);
    const bot9 = getWalletForIndex(9);

    const bot5Auth = await authenticateBot(5, bot5);
    const bot9Auth = await authenticateBot(9, bot9);

    // Bot 5 plays scissors, Bot 9 plays rock (Bot 5 loses)
    const cid5 = await registerBotMoves(5, bot5, bot5Auth.token, bot5Auth.userId, Array(10).fill("scissors"));
    const cid9 = await registerBotMoves(9, bot9, bot9Auth.token, bot9Auth.userId, Array(10).fill("rock"));

    // Force Bot #5 balance to 0.10 and force session start BEFORE submission so after the fight loss,
    // its balance is 0.09 ETH, which is insufficient for the doubled bet (0.02 ETH * 10 coefficient = 0.20 ETH)
    console.log("   - Pre-marking Bot #5 session_started=true + session_start_balance=0.10 + bet_amount=0.01...");
    runDbHelper("set_balance", bot5.address, "0.10");
    runDbHelper("force_session_start", bot5.address, "0.10", "0.01");

    await submitPremoveOnChain(bot5, "0.01", cid5);
    await submitPremoveOnChain(bot9, "0.01", cid9);
    // Pool full → PoolEmitted → Bridge auto-handles fight. Bot #5 loses, Martingale tries to double to 0.02 but balance = 0.01 → Ruin

    console.log("   - Waiting for Bot #5 to be stopped (ruined)...");
    await waitForUserStatus(bot5.address, "stopped");

    // Check DB status: should be stopped
    const bot5UserDb = runDbHelper("get_user", bot5.address);
    console.log(`   - Bot #5 status in DB: ${bot5UserDb.status}`);
    console.log(`   - Bot #5 balance in DB: ${bot5UserDb.balance}`);

    if (bot5UserDb.status !== "stopped") {
        throw new Error(`Scenario D FAIL: expected user to be stopped (ruined), got ${bot5UserDb.status}`);
    }
    console.log("💚 SCENARIO D: SUCCESS (User is stopped and ruined, funds auto-payout triggered)");

    console.log("\n========================================================");
    console.log("🎉 ALL E2E SIMULATION COMPLEX SCENARIOS PASSED WITH SUCCESS!");
    console.log("========================================================\n");
}

main().catch(error => {
    console.error("❌ Simulation complex script failed:", error);
    process.exit(1);
});
