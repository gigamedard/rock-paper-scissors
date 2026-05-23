// Fichier : set_params.js
// Rôle : Modifier les paramètres globaux (admin) du contrat.

import { JsonRpcProvider, Wallet, Contract } from "ethers";
import {
    LOCAL_HARDHAT_URL, // Target local node
    contracts
} from "./config.js";

// ===================================
// == ⚠️ CONFIGURATION REQUISE ⚠️
// ===================================

// La nouvelle valeur pour le coefficient de sécurité (ex: 1)
const NOUVEAU_COEFFICIENT = 100;

// La nouvelle valeur pour la taille maximale par défaut des pools (ex: 5)
const NOUVELLE_TAILLE_MAX = 2;

// Account #0 of Hardhat default accounts
const LOCAL_OWNER_PK = "***REMOVED***";

// ===================================


async function main() {
    console.log("🚀 Connexion au réseau Fuji...");

    // 1. Connexion au Provider et au Wallet (doit être le 'owner')
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL);
    const ownerWallet = new Wallet(LOCAL_OWNER_PK, provider);

    // 2. Connexion au contrat (il doit avoir le NOUVEL ABI)
    const contract = new Contract(contracts.game.address, contracts.game.abi, ownerWallet);

    console.log(`   - Contrat: ${contracts.game.address}`);
    console.log(`   - Propriétaire (Owner): ${ownerWallet.address}`);

    try {
        // === 1. MISE À JOUR DU COEFFICIENT ===
        console.log("\n--- Mise à jour du Security Coefficient ---");
        const oldCoefficient = await contract.securityCoefficient();
        console.log(`   Valeur actuelle : ${oldCoefficient.toString()}`);

        if (oldCoefficient.toString() !== NOUVEAU_COEFFICIENT.toString()) {
            const tx1 = await contract.setSecurityCoefficient(NOUVEAU_COEFFICIENT);
            await tx1.wait();
            console.log(`   ✅ Transaction confirmée : ${tx1.hash}`);
        } else {
            console.log("   Valeur déjà à jour.");
        }

        // === 2. MISE À JOUR DE LA TAILLE MAX PAR DÉFAUT ===
        console.log("\n--- Mise à jour de la Default Pool Max Size ---");
        const oldSize = await contract.defaultPoolMaxSize(); // Appel de la nouvelle fonction
        console.log(`   Valeur actuelle : ${oldSize.toString()}`);

        if (oldSize.toString() !== NOUVELLE_TAILLE_MAX.toString()) {
            const tx2 = await contract.setDefaultPoolMaxSize(NOUVELLE_TAILLE_MAX); // Appel de la nouvelle fonction
            await tx2.wait();
            console.log(`   ✅ Transaction confirmée : ${tx2.hash}`);
        } else {
            console.log("   Valeur déjà à jour.");
        }

        // === 3. VÉRIFICATION FINALE ===
        console.log("\n--- Vérification finale ---");
        const finalCoefficient = await contract.securityCoefficient();
        const finalSize = await contract.defaultPoolMaxSize();

        console.log(`   Nouveau Coefficient : ${finalCoefficient.toString()}`);
        console.log(`   Nouvelle Taille Max : ${finalSize.toString()}`);
        console.log("\n🏁 Paramètres mis à jour !");

    } catch (error) {
        console.error("\n❌ ERREUR lors de l'appel de la fonction :");
        console.error("   Avez-vous bien redéployé le contrat et mis à jour l'ABI dans config.js ?");
        console.error(`   Message: ${error.message}`);
    }
}

main();