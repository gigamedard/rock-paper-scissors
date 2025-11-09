// Fichier : reset_fuji.js
// Rôle : Redéploie le contrat sur Fuji et met à jour config.js automatiquement.

import { exec } from "child_process";
import { readFile, writeFile } from "fs/promises";
import path from "path";

// --- Configuration des Chemins ---
// Chemin vers votre fichier de config
const configPath = path.resolve("../smart_contracts/config.js"); 
// Chemin vers l'ABI compilé par Hardhat
const abiPath = path.resolve("./artifacts/contracts/Battlepool.sol/Battlepool.json"); 
// La commande de déploiement
const deployCommand = "npx hardhat ignition deploy ignition/modules/Battlepool.js --network fuji";
// ---------------------------------

/**
 * Exécute une commande shell et renvoie sa sortie.
 */
function runCommand(command) {
    return new Promise((resolve, reject) => {
        exec(command, (error, stdout, stderr) => {
            if (error) {
                console.error(`Erreur Shell: ${stderr}`);
                return reject(error);
            }
            resolve(stdout);
        });
    });
}

/**
 * Fonction principale du script
 */
async function main() {
    try {
        // --- ÉTAPE 1 : Redéploiement ---
        console.log("🚀 Lancement du redéploiement sur Fuji... (cela peut prendre 1-2 minutes)");
        const stdout = await runCommand(deployCommand);
        console.log(stdout); // Affiche la sortie du déploiement

        // --- ÉTAPE 2 : Capturer la Nouvelle Adresse ---
        const addressMatch = stdout.match(/Battlepool deployed to: (0x[a-fA-F0-9]{40})/);
        if (!addressMatch || !addressMatch[1]) {
            throw new Error("❌ Erreur : Impossible de trouver la nouvelle adresse du contrat dans la sortie.");
        }
        const newAddress = addressMatch[1];
        console.log(`✅ Contrat redéployé avec succès à : ${newAddress}`);

        // --- ÉTAPE 3 : Lire le Nouvel ABI ---
        console.log(`📖 Lecture du nouvel ABI depuis ${abiPath}...`);
        const abiFile = await readFile(abiPath, "utf8");
        const newAbi = JSON.parse(abiFile).abi;
        // Convertit l'ABI en chaîne JSON formatée
        const newAbiString = JSON.stringify(newAbi, null, 12); // 12 espaces pour l'indentation

        // --- ÉTAPE 4 : Mettre à jour config.js ---
        console.log(`✍️  Mise à jour de ${configPath}...`);
        let configFileContent = await readFile(configPath, "utf8");

        // Remplacer l'adresse
        // Cible: game: { address: "0x..."
        const addressRegex = /(game: {\s*address: ")(0x[a-fA-F0-9]{40})(")/;
        configFileContent = configFileContent.replace(addressRegex, `$1${newAddress}$3`);

        // Remplacer l'ABI
        // Cible: game: { ... abi: [...]
        const abiRegex = /(game: {[\s\S]*?abi: )\[[\s\S]*?\]/s;
        configFileContent = configFileContent.replace(abiRegex, `$1${newAbiString}`);

        await writeFile(configPath, configFileContent, "utf8");

        console.log("\n🎉 RESET TERMINÉ !");
        console.log("Votre fichier config.js est maintenant à jour avec la nouvelle adresse et le nouvel ABI.");

    } catch (error) {
        console.error("\n🚨 ÉCHEC DU RESET :");
        console.error(error.message);
    }
}

main();