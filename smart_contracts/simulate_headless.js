// Fichier : simulate_headless.js
// À placer dans le même dossier que ton app.js et config.js

import { JsonRpcProvider, Wallet, Contract, parseEther, formatEther } from "ethers";
import fetch from 'node-fetch';
import {
    LARAVEL_API_URL,
    LOCAL_HARDHAT_URL,
    pinata,
    contracts
} from "./config.js";

// --- CONFIGURATION DE LA SIMULATION ---

// 1. URL de ton API Laravel


// 2. Clés Pinata (Normalement, elles viennent de l'API, mais pour un script, on peut les mettre ici)
// REMPLACE AVEC TES VRAIES CLÉS
const PINATA_API_KEY = pinata.PINATA_API_KEY;
const PINATA_API_SECRET = pinata.PINATA_SECRET;
const PINATA_API_URL = 'https://api.pinata.cloud/pinning/pinJSONToIPFS';

// 3. Paramètres du jeu
const BASE_BET_ETH = "0.01"; // La mise de base (ex: 0.01 ETH)
const SECURITY_COEFFICIENT = 1n; // Le 'n' le transforme en BigInt

// 4. Liste des comptes de test (pris de ton ancien script)
const predefinedAccounts = [
  {
    address: "0xa0Ee7A142d267C1f36714E4a8F75612F20a7972",
    privateKey: "0x2a871d0798f97d79848a013d4936a73bf4cc922c825d33c1cf7073dff6d409c6"
  },
  {
    address: "0xBcd4042DE499D14e55001CcbB24a551F3b954096",
    privateKey: "0xf214f2b2cd398c806f84e317254e0f0b801d0643303237d97a22a48e01628897"
  },
  {
    address: "0xFABB0ac9d68B0B445fB7357272Ff202C5651694a",
    privateKey: "0xa267530f49f8280200edf313ee7af6b827f2a8bce2897751d06a843f644967b1"
  },
  {
    address: "0x71bE63f3384f5fb98995898A86B02Fb2426c5788",
    privateKey: "0x701b615bbdfb9de65240bc28bd21bbc0d996645a3dd57e7b12bc2bdf6f192c82"
  },
  {
    address: "0x1CBd3b2770909D4e10f157cABC84C7264073C9Ec",
    privateKey: "0x47c99abed3324a2707c28affff1267e45918ec8c3f20b8aa892e8b065d2942dd"
  },
];
// ------------------------------------


/**
 * Étape 1: Simule le login d'un utilisateur en signant un message.
 * C'est le flux d'authentification sécurisé.
 */
async function login(wallet) {
    console.log(`  [1/5] Authentification...`);
    // 1. Demander le message à signer
    let response = await fetch(`${LARAVEL_API_URL}/wallet/generate-message`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ wallet_address: wallet.address, locale: 'en' })
    });
    let data = await response.json();
    const message = data.message;

    // 2. Signer le message
    const signature = await wallet.signMessage(message);

    // 3. Vérifier la signature et obtenir le token
    response = await fetch(`${LARAVEL_API_URL}/wallet/verify-signature`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ wallet_address: wallet.address, signature: signature, locale: 'en' })
    });

    data = await response.json();
    if (!response.ok || !data.token) {
        throw new Error("Échec de l'authentification : " + data.message);
    }
    
    console.log(`  [1/5] Authentification réussie. (Token obtenu)`);
    return { token: data.token, userId: data.user.id };
}

/**
 * Étape 2: Simule l'upload des coups sur Pinata.
 */
async function uploadToPinata(moves, userAddress) {
    console.log(`  [2/5] Upload sur Pinata...`);
    const pinataData = {
        pinataContent: {
            wallet_address: userAddress,
            moves: moves,
            timestamp: new Date().toISOString()
        },
        pinataMetadata: {
            name: `Simulated-Pre-Move-${userAddress}-${Date.now()}`
        }
    };

    const response = await fetch(PINATA_API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'pinata_api_key': PINATA_API_KEY,
            'pinata_secret_api_key': PINATA_API_SECRET
        },
        body: JSON.stringify(pinataData)
    });

    const data = await response.json();
    if (!response.ok || !data.IpfsHash) {
        throw new Error("Échec de l'upload Pinata: " + (data.error || "Réponse invalide"));
    }
    
    console.log(`  [2/5] Upload Pinata réussi. CID: ${data.IpfsHash}`);
    return data.IpfsHash;
}

/**
 * Étape 3: Simule l'envoi des pre-moves au backend Laravel.
 */
async function submitToBackend(userId, moves, cid, bet, token) {
    console.log(`  [3/5] Soumission au backend Laravel...`);
    const body = {
        user_id: userId,
        pre_moves: moves, // Envoie les strings ["rock", "paper", ...]
        cid: cid,
        bet_amount: bet
    };

    const response = await fetch(`${LARAVEL_API_URL}/user/pre-moves`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify(body)
    });

    const data = await response.json();
    if (!response.ok) {
        throw new Error("Échec de la soumission au backend: " + data.message);
    }
    
    console.log(`  [3/5] Soumission au backend réussie.`);
}

/**
 * Étape 4: Simule la soumission finale à la blockchain.
 */
async function submitToBlockchain(wallet, contract, betWei, cid, depositWei) {
    console.log(`  [4/5] Soumission à la Blockchain...`);
    const contractWithSigner = contract.connect(wallet);

    const tx = await contractWithSigner.submitPremoveCID(
        betWei,
        cid,
        { value: depositWei }
    );
    
    console.log(`  [4/5] Transaction envoyée. En attente de confirmation...`);
    const receipt = await tx.wait();
    console.log(`  [5/5] Transaction confirmée ! Hash: ${receipt.hash}`);
    return receipt;
}


/**
 * Fonction Principale
 */
async function main() {
    console.log("--- Démarrage de la Simulation Headless ---");

    // Initialisation du Provider et Contrat (en lecture seule d'abord)
    const provider = new JsonRpcProvider(LOCAL_HARDHAT_URL); // Utilise le RPC local
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

    // Calcul des montants
    const baseBetWei = parseEther(BASE_BET_ETH);
    const depositWei = baseBetWei * SECURITY_COEFFICIENT;

    console.log(`Configuration:
  - Réseau: ${LOCAL_HARDHAT_URL}
  - Contrat: ${contracts.game.address}
  - Mise de Base: ${formatEther(baseBetWei)} ETH
  - Dépôt Total: ${formatEther(depositWei)} ETH (Coefficient: ${SECURITY_COEFFICIENT}x)
`);

    // Boucle sur chaque compte de test
    for (const account of predefinedAccounts) {
        console.log(`\n--- Simulation pour l'utilisateur: ${account.address} ---`);
        try {
            // Crée le "signer" Ethers
            const signer = new Wallet(account.privateKey, provider);

            // 1. Authentification
            const { token, userId } = await login(signer);

            // 2. Définir les coups (on peut les randomiser plus tard)
            const moves = ["rock", "paper", "scissors", "rock", "paper"]; 
            
            // 3. Upload Pinata
            const cid = await uploadToPinata(moves, signer.address);

            // 4. Soumettre au Backend Laravel
            await submitToBackend(userId, moves, cid, BASE_BET_ETH, token);

            // 5. Soumettre à la Blockchain
            await submitToBlockchain(signer, contract, baseBetWei, cid, depositWei);
            
            // === LIGNE AJOUTÉE ===
            // 'contract.target' est la nouvelle façon d'accéder à l'adresse dans Ethers v6
            const contractBalanceWei = await provider.getBalance(contract.target);
            console.log(`    💎 NOUVEAU SOLDE DU CONTRAT: ${formatEther(contractBalanceWei)} ETH`);
            // =====================

            console.log(`✅ SIMULATION RÉUSSIE pour ${account.address}`);

        } catch (error) {
            console.error(`❌ ERREUR pour ${account.address}:`, error.message);
        }
    }

    console.log("\n--- Simulation Terminée ---");
}

main().catch(error => {
    console.error("Erreur fatale dans le script de simulation:", error);
    process.exit(1);
});