// Fichier: test_event.js
// Un script de test minimal pour forcer l'émission d'un événement.

import { JsonRpcProvider, Wallet, Contract, parseEther } from "ethers";
import { LOCAL_HARDHAT_URL, contracts ,FUJI_RPC_URL} from "./config.js";

// --- Configuration ---
// Nous n'avons besoin que d'un seul compte pour payer le gaz
const TEST_ACCOUNT = {
    privateKey: "8351c039abec71bfb0338862fcbc129b487108e8e84a7aa8957f407a1c1d2162",
    address: "0xb8195e6e7761ab2758803dcf2fd38016b2bea079"
};

// Données factices (dummy data) pour l'événement
const DUMMY_DATA = {
    poolId: 999, // Un ID de pool facile à repérer
    baseBet: parseEther("0.001"),
    users: [
        "0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266",
        "0x70997970C51812dc3A010C7d01b50e0d17dc79C8"
    ],
    premoveCIDs: ["cid_test_1", "cid_test_2"],
    poolSalt: "0x_test_salt_123456789"
};
// ---------------------

async function main() {
    console.log("🚀 Lancement du test d'émission d'événement direct...");

    // 1. Connexion au nœud Hardhat
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    
    // 2. Création du portefeuille pour signer la transaction
    const wallet = new Wallet(TEST_ACCOUNT.privateKey, provider);
    
    // 3. Connexion au contrat
    // Nous le connectons directement au 'wallet' pour pouvoir envoyer une transaction
    const contract = new Contract(contracts.game.address, contracts.game.abi, wallet);

    console.log(`   - Contrat: ${contracts.game.address}`);
    console.log(`   - Appelant: ${wallet.address}`);

    // 4. Appel de la fonction de test sur le contrat
    try {
        console.log("\nAppel de 'triggerPoolEmittedEventForTesting'...");
        
        const tx = await contract.triggerPoolEmittedEventForTesting(
            DUMMY_DATA.poolId,
            DUMMY_DATA.baseBet,
            DUMMY_DATA.users,
            DUMMY_DATA.premoveCIDs,
            DUMMY_DATA.poolSalt
        );

        await tx.wait(); // Attendre la confirmation
        
        console.log(`✅ Transaction confirmée ! Hash: ${tx.hash}`);
        console.log("---");
        console.log(`👉 L'événement 'PoolEmitted' (ID: ${DUMMY_DATA.poolId}) a été émis sur la blockchain.`);
        console.log("👉 Vérifiez la console de votre worker 'app.js' (Terminal 4).");

    } catch (error) {
        console.error("\n❌ ERREUR lors de l'appel de la fonction de test:");
        console.error(error.message);
    }
}

main();