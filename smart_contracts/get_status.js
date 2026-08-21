// Fichier : get_status.js
// Rôle : Lire et afficher l'état actuel de votre contrat sur la blockchain.

import { JsonRpcProvider, Contract, formatEther, parseEther } from "ethers";
import { 
    FUJI_RPC_URL, 
    contracts 
} from "./config.js";

// ===================================
// == ⚠️ CONFIGURATION REQUISE ⚠️
// ===================================

// Le 'baseBet' du pool que vous voulez inspecter (en format ETH)
// Doit correspondre à la mise de votre simulateur (ex: "0.01")
const POOL_TO_INSPECT_ETH = "0.001";

// ===================================


async function main() {
    console.log(`🚀 Connexion au réseau Fuji (${FUJI_RPC_URL})...`);

    // 1. Connexion au Provider (en lecture seule)
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    
    // 2. Connexion au contrat (il doit avoir le NOUVEL ABI)
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

    console.log(`Lecture de l'état du contrat à l'adresse : ${contracts.game.address}`);
    
    try {
        // === 1. LECTURE DES PARAMÈTRES GLOBAUX ===
        console.log("\n--- ⚙️ État Global du Contrat ---");

        const [owner, balanceWei, nextPoolId, coefficient, defaultSize] = await Promise.all([
            contract.owner(),
            contract.getContractBalance(), // Utilise votre fonction custom
            contract.nextPoolId(),
            contract.securityCoefficient(),
            contract.defaultPoolMaxSize() // Utilise votre nouvelle variable
        ]);

        console.log(`  Propriétaire (Owner)  : ${owner}`);
        console.log(`  Solde du Contrat      : ${formatEther(balanceWei)} AVAX`);
        console.log(`  Prochain Pool ID      : ${nextPoolId.toString()}`);
        console.log(`  Security Coefficient  : ${coefficient.toString()}`);
        console.log(`  Default Pool Max Size : ${defaultSize.toString()}`);

        // === 2. LECTURE D'UN POOL SPÉCIFIQUE ===
        console.log(`\n--- 🏊 État du Pool (Mise: ${POOL_TO_INSPECT_ETH} ETH) ---`);
        
        const baseBetWei = parseEther(POOL_TO_INSPECT_ETH);

        // On récupère les infos du pool (sauf la liste 'users')
        const poolInfo = await contract.pools(baseBetWei);
        // On récupère la liste 'users' séparément avec votre fonction
        const poolUsers = await contract.getPoolUsers(baseBetWei);

        if (poolInfo.poolId.toString() === "0") {
            console.log("  Statut: Ce pool n'a pas encore été créé.");
        } else {
            console.log(`  ID du Pool            : ${poolInfo.poolId.toString()}`);
            console.log(`  Taille Max (maxSize)  : ${poolInfo.maxSize.toString()}`);
            console.log(`  Nombre d'utilisateurs : ${poolUsers.length}`);
            console.log(`  Utilisateurs dans le pool:`);
            if (poolUsers.length > 0) {
                poolUsers.forEach((user, index) => {
                    console.log(`    ${index + 1}: ${user}`);
                });
            } else {
                console.log("    (aucun)");
            }
        }

    } catch (error) {
        console.error("\n❌ ERREUR lors de la lecture du contrat :");
        console.error("   Avez-vous bien redéployé et mis à jour l'ABI dans config.js ?");
        console.error(`   Message: ${error.message}`);
    }
}

main();