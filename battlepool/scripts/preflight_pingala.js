/**
 * preflight_pingala.js — Contrôle avant déploiement réel (lecture seule).
 *
 * Vérifie, SANS jamais afficher de clé privée :
 *  1. battlepool/.env contient PINGALA_DEPLOYER_PK (présence seulement) ;
 *  2. la clé dérivée correspond EXACTEMENT à l'adresse de deployer attendue ;
 *  3. le RPC Pingala répond : chainId 99999, solde, nonce, gaz.
 *
 * Usage : node scripts/preflight_pingala.js          (depuis battlepool/)
 *         (ou npx hardhat run scripts/preflight_pingala.js --network pingala)
 */
const { ethers } = require("ethers");
const fs = require("fs");
const path = require("path");

// Charge battlepool/.env (gitignored) — dotenv n'écrase jamais une var existante.
require("dotenv").config({ path: path.join(__dirname, "..", ".env") });

const RPC = process.env.PINGALA_RPC_URL || process.argv[2];
const EXPECTED_DEPLOYER = "0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee";

function readSecretF(envVarName) {
  const secretPath = path.join("/run/secrets", envVarName.toLowerCase());
  try { if (fs.existsSync(secretPath)) return fs.readFileSync(secretPath, "utf8").trim(); } catch (_) {}
  return process.env[envVarName];
}

async function main() {
  console.log("=== PREFLIGHT PINGALA (lecture seule, clés jamais affichées) ===");

  // 1) Présence de la clé
  const pk = readSecretF("PINGALA_DEPLOYER_PK");
  if (!pk) {
    console.error("❌ PINGALA_DEPLOYER_PK absente.");
    console.error("   → Déposer la clé dans battlepool/.env (gitignored) : ligne 'PINGALA_DEPLOYER_PK=0x...'");
    process.exit(2);
  }
  console.log("✓ PINGALA_DEPLOYER_PK présente (valeur non affichée)");

  // 2) La clé dérive-t-elle vers l'adresse de deployer attendue ?
  let wallet;
  try { wallet = new ethers.Wallet(pk.trim()); } catch (e) {
    console.error("❌ PINGALA_DEPLOYER_PK invalide (format) :", e.message);
    process.exit(2);
  }
  if (wallet.address.toLowerCase() !== EXPECTED_DEPLOYER.toLowerCase()) {
    console.error(`❌ MISMATCH : la clé fournie dérive vers ${wallet.address}, attendu ${EXPECTED_DEPLOYER}`);
    console.error("   → Ne PAS déployer avec une autre clé : STOP.");
    process.exit(2);
  }
  console.log(`✓ clé ↔ adresse OK : ${wallet.address}`);

  // 3) RPC
  if (!RPC) { console.error("❌ PINGALA_RPC_URL manquant (battlepool/.env)."); process.exit(2); }
  const provider = new ethers.JsonRpcProvider(RPC, undefined, { staticNetwork: true });
  const [net, chainRaw, block, fee] = await Promise.all([
    provider.getNetwork(), provider.send("eth_chainId", []), provider.getBlockNumber(), provider.getFeeData(),
  ]);
  if (BigInt(chainRaw) !== 99999n) {
    console.error(`❌ chainId du RPC = ${chainRaw} (attendu 99999 / 0x1869f). STOP.`);
    process.exit(2);
  }
  const bal = await provider.getBalance(wallet.address);
  const nonceLatest = await provider.getTransactionCount(wallet.address, "latest");
  const noncePending = await provider.getTransactionCount(wallet.address, "pending");
  console.log(`✓ chainId 99999 | bloc ${block}`);
  console.log(`  gasPrice ≈ ${fee.gasPrice ? (Number(fee.gasPrice) / 1e9).toFixed(3) + " gwei" : "n/a"}`,
    `| EIP-1559: ${fee.maxFeePerGas ? "OK" : "ABSENT"}`);
  console.log(`  balance deployer : ${ethers.formatEther(bal)} TST`);
  console.log(`  nonce latest=${nonceLatest} pending=${noncePending}`, nonceLatest === noncePending ? "✓" : "❌ TX PENDANTE — résoudre avant deploy");
  if (bal === 0n) {
    console.error("❌ Balance deployer nulle — financer l'adresse avant déploiement (STOP).");
    process.exit(2);
  }
  console.log("=== PREFLIGHT OK — prêt pour : npx hardhat run full_deploy_pingala.js --network pingala ===");
}

main().catch((e) => {
  console.error("❌ PREFLIGHT ÉCHOUÉ :", e && e.message ? e.message : e);
  process.exit(1);
});