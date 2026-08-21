// Fichier : fund_accounts.js
// Rôle : Envoyer des AVAX de test depuis un portefeuille principal vers 5 comptes de test.

import { JsonRpcProvider, Wallet, parseEther, formatEther } from "ethers";
import { FUJI_RPC_URL } from "./config.js"; // On importe l'URL RPC de Fuji

// ==========================================================
// == ⚠️ CONFIGURATION REQUISE ⚠️
// ==========================================================

// 1. Clé privée de votre portefeuille "principal" (celui qui a des AVAX de test)
//    NE COMMETEZ JAMAIS CE FICHIER SUR GIT AVEC CETTE CLÉ REMPLIE !
const MAIN_WALLET_PK = "***REMOVED***";

// 2. Le montant à envoyer à CHAQUE compte (en AVAX)
const AMOUNT_TO_SEND = "0.5"; // (ex: 0.5 AVAX)

// 3. Vos 5 comptes de test (copiés de simulate_headless.js)
const PREDEFINED_ACCOUNTS = [
  { address: "0xdded5d7d8171b68b6105236164bca7a45839d150" },
  { address: "0xdafe2be78f32d151f45ef18bcf7e32cb9e6da506" },
  { address: "0x89aaa8574c6450fa2380d6a4ce413ac184080f43" },
  { address: "0x2802e00882f6a8c8958160864ee81e623b9abe57" },
  { address: "0x2da239fddfdb298dde0ec3c4e96294506da7e2df" },
];
// ==========================================================
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

async function main() {
    if (MAIN_WALLET_PK === "COLLEZ_VOTRE_CLE_PRIVEE_PRINCIPALE_ICI") {
        console.error("❌ ERREUR : Veuillez ouvrir le script fund_accounts.js et remplir la variable MAIN_WALLET_PK.");
        return;
    }

    // 1. Connexion à Fuji
    const provider = new JsonRpcProvider(FUJI_RPC_URL);
    const funderWallet = new Wallet(MAIN_WALLET_PK, provider);
    const amountInWei = parseEther(AMOUNT_TO_SEND);

    console.log(`🚀 Démarrage du rechargement...`);
    console.log(`   - Portefeuille source : ${funderWallet.address}`);
    console.log(`   - Montant par envoi : ${AMOUNT_TO_SEND} AVAX`);
    
    // Vérifier le solde du portefeuille source
    const balanceWei = await provider.getBalance(funderWallet.address);
    const balanceAvax = formatEther(balanceWei);
    console.log(`   - Solde du source : ${balanceAvax} AVAX\n`);

    if (balanceWei < (amountInWei * BigInt(PREDEFINED_ACCOUNTS.length))) {
        console.warn("⚠️ ATTENTION : Le solde du portefeuille source est peut-être insuffisant pour tous les transferts.\n");
    }

    // 2. Boucle sur chaque compte et envoi des fonds
    for (const account of PREDEFINED_ACCOUNTS) {
        const receiverAddress = account.address;
        console.log(`---`);
        console.log(`💸 Envoi de ${AMOUNT_TO_SEND} AVAX à ${receiverAddress}...`);
        
        try {
            const tx = {
                to: receiverAddress,
                value: amountInWei
            };

            const txResponse = await funderWallet.sendTransaction(tx);
            await txResponse.wait(); // Attendre la confirmation

            console.log(`✅ Succès ! Hash de la transaction : ${txResponse.hash}`);

        } catch (error) {
            console.error(`❌ Échec pour ${receiverAddress}: ${error.message}`);
        }
    }

    console.log(`\n---`);
    console.log(`🏁 Rechargement terminé.`);
}

main().catch(console.error);