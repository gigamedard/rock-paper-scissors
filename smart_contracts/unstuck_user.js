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
    // Calling payOut with 1 wei clears the user's balances and sets isUserInAnyPool = false!
    const tx = await contract.payOut(userAddress, 1n);
    console.log("Transaction sent:", tx.hash);
    await tx.wait();

    console.log("User unlocked!");
    const inPool = await contract.isUserInAnyPool(userAddress);
    console.log("isUserInAnyPool state is now:", inPool);
}

main().catch(console.error);
