// Fichier : simulate_headless.js
// À placer dans le même dossier que ton app.js et config.js

import { JsonRpcProvider, Wallet, Contract, parseEther, formatEther } from "ethers";
import fetch from 'node-fetch';
import {
    LARAVEL_API_URL,
    LOCAL_HARDHAT_URL,
    FUJI_RPC_URL,
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
const BASE_BET_ETH = "0.001"; // La mise de base (ex: 0.01 ETH)
const SECURITY_COEFFICIENT = 1n; // Le 'n' le transforme en BigInt

// 4. Liste des comptes de test (pris de ton ancien script)
const predefinedAccounts = [
  {
    address: "0xdded5d7d8171b68b6105236164bca7a45839d150",
    privateKey: "400e1b043832260518588f42125acf9b974f6365f78b8ab6899eefe350b228b4"
  },
  {
    address: "0xdafe2be78f32d151f45ef18bcf7e32cb9e6da506",
    privateKey: "5004f3cf7cf0ad38cd112ce50dd6fbc416172dd25ba92bdd96b0d32002a9af06"
  },
  {
    address: "0x89aaa8574c6450fa2380d6a4ce413ac184080f43",
    privateKey: "4f17b5c561e77f086465826e904db1409192573e9fe795a33112b40dde049b15"
  },
  {
    address: "0x2802e00882f6a8c8958160864ee81e623b9abe57",
    privateKey: "52f322890993e91ed245059810067871bb1e3c5460303bb798bab5eee29abbd4"
  },
  {
    address: "0x2da239fddfdb298dde0ec3c4e96294506da7e2df",
    privateKey: "a25b6801aaa2b3d1e99fe0684c5801b270bd6ed9ed3dafddb82b01b453aa0a43"
  },
];



/*
Private Key: 400e1b043832260518588f42125acf9b974f6365f78b8ab6899eefe350b228b4
Address: 0xdded5d7d8171b68b6105236164bca7a45839d150

Private Key: 5004f3cf7cf0ad38cd112ce50dd6fbc416172dd25ba92bdd96b0d32002a9af06
Address: 0xdafe2be78f32d151f45ef18bcf7e32cb9e6da506


Private Key: 4f17b5c561e77f086465826e904db1409192573e9fe795a33112b40dde049b15
Address: 0x89aaa8574c6450fa2380d6a4ce413ac184080f43

Private Key: 52f322890993e91ed245059810067871bb1e3c5460303bb798bab5eee29abbd4
Address: 0x2802e00882f6a8c8958160864ee81e623b9abe57

Private Key: a25b6801aaa2b3d1e99fe0684c5801b270bd6ed9ed3dafddb82b01b453aa0a43
Address: 0x2da239fddfdb298dde0ec3c4e96294506da7e2df
*/



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
    const provider = new JsonRpcProvider(FUJI_RPC_URL); // Utilise le RPC Fuji
    const contract = new Contract(contracts.game.address, contracts.game.abi, provider);

    // Calcul des montants
    const baseBetWei = parseEther(BASE_BET_ETH);
    const depositWei = baseBetWei * SECURITY_COEFFICIENT;

    console.log(`Configuration:
  - Réseau: ${FUJI_RPC_URL}
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