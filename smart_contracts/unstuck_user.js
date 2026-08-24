import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { contracts, GAME_WALLET_PK, FUJI_RPC_URL } from "./config.js";

async function main() {
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const wallet = new Wallet(GAME_WALLET_PK, provider);
    const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    const userAddress = process.argv[2] && process.argv[2].startsWith("0x") ? process.argv[2] : "0x13681eba8a5efdbb53e5689c16c86014ea2dbe16";

    console.log("Checking if contract has balance...");
    const contractBalance = await provider.getBalance(contracts.game.address);
    if (contractBalance === 0n) {
        console.log("Contract is empty. Sending 0.01 AVAX to contract to fund the payout transaction...");
        const txFund = await wallet.sendTransaction({
            to: contracts.game.address,
            value: parseEther("0.01")
        });
        await txFund.wait();
        console.log("Contract funded.");
    }

    console.log(`Force unlocking user ${userAddress}...`);
    // Fetch the user's exact on-chain balance and pay it out fully.
    // This clears userBalances to 0 and removes them from any pool.
    const userBalance = await contract.getUserBalance(userAddress);
    if (userBalance === 0n) {
        // Already zero balance but still flagged in a pool — force a 0-amount cleanup
        // by setting isUserInAnyPool directly via a 1-wei dummy deposit+withdraw cycle
        // is not possible. Use payOut with amount equal to balance (0) — but payOut requires >0.
        // Fallback: use setUserNextSessionTime to 0 and rely on checkAndRefundStagnantPool.
        console.log("User balance is 0. Setting next session time to 0 to unblock.");
        await contract.setUserNextSessionTime(userAddress, 0);
        console.log("Cooldown reset.");
    } else {
        const tx = await contract.payOut(userAddress, userBalance);
        console.log("Transaction sent:", tx.hash);
        await tx.wait();
    }

    console.log("User unlocked!");
    const inPool = await contract.isUserInAnyPool(userAddress);
    console.log("isUserInAnyPool state is now:", inPool);
}

main().catch(console.error);
