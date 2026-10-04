/**
 * redeploy_prana_marketplace.js — RE-DÉPLOIEMENT CIBLÉ sur Pingala Chain
 * (chainId 99999). Une seule intervention séquentielle : await sur chaque tx.
 *
 * CORRECTIF AUDIT : PranaToken mintait 1M PRANA au placeholder Hardhat
 * INITIAL_OWNER_ADDRESS = 0x8C3229EC…43D (compte sans PK ni gas) => économie
 * PRANA inopérable. Correctif Orchestrateur :
 *   1) PranaToken corrigé (constructeur : deployer = owner, mint 1M au deployer)
 *      -> RE-DÉPLOI sur Pingala ;
 *   2) MarketplaceEscrow lie le token via `immutable sntToken` (aucun setter)
 *      -> RE-DÉPLOI du marketplace APRÈS le token (dépendance constructeur +
 *      ordonnancement nonce : PranaToken D'ABORD, Marketplace APRÈS).
 *   3) Funding PRANA : 100 000 -> PAYOUT_OPERATOR, 50 000 -> DEV.
 *
 * Battlepool (0x0790…62EF) et WAVAX (0x0a32…fD4) NE SONT PAS touchés (leurs
 * adresses restent, code vérifié après coup).
 *
 * Clés : PINGALA_DEPLOYER_PK lue depuis battlepool/.env (gitignored, chargé par
 * hardhat.config.js). Aucune clé loggée, écrite dans un fichier tracké ou
 * persistée dans un livrable.
 *
 * Usage : npx hardhat run scripts/redeploy_prana_marketplace.js --network pingala
 */
const { ethers, network } = require("hardhat");
const fs = require("fs");
const path = require("path");

const DEPLOYMENTS_FILE = path.join(__dirname, "..", "pingala_deployments.json");
const CONFIG_PATH = path.join(__dirname, "..", "..", "smart_contracts", "config.js");

// --- Adresses INVARIANTS (non touchées par ce correctif) ---
const BATTLEPOOL = "0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF";
const WAVAX = "0x0a324396FB40C9cf10f665bFDb21FFceBe66CfD4";
// --- Bénéficiaires du funding PRANA ---
const PAYOUT_OPERATOR = "0x9A60327ce58A94a411987119047A75A8F1076663";
const DEV_WALLET = "0x153d0C405a415F3E01188F46dAA80D1fcb2C2166";
// --- Anciennes adresses (deviennent mortes après le correctif) ---
const OLD_PRANA = "0x4D3f9aAC71Ba10f40f5054d034004254108bD90B";
const OLD_MARKETPLACE = "0x4Da46c76d47D67e3Eb6977E9022Fa857D6fD64De";

const PAYOUT_PRANA = ethers.parseEther("100000"); // 100 000 PRANA -> PAYOUT_OPERATOR
const DEV_PRANA = ethers.parseEther("50000");     // 50 000 PRANA  -> DEV

async function main() {
  console.log(`=== RE-DÉPLOIEMENT PRANA + MARKETPLACE — ${network.name} ===`);
  if (network.name !== "pingala") {
    throw new Error(`Script réservé au réseau pingala (actuel : ${network.name})`);
  }
  const [deployer] = await ethers.getSigners();
  console.log("deployer:", deployer.address);
  const chainId = (await ethers.provider.getNetwork()).chainId;
  console.log("chainId:", chainId.toString(), "(attendu 99999)");
  if (chainId.toString() !== "99999") throw new Error("MAUVAISE CHAÎNE");

  const balance = await ethers.provider.getBalance(deployer.address);
  console.log("balance deployer :", ethers.formatEther(balance), "TST");

  // --- 0) Contrôles avant toute tx (lecture seule) ---
  const oldMarket = await ethers.getContractAt("MarketplaceEscrow", OLD_MARKETPLACE);
  const oldToken = await oldMarket.sntToken();
  if (oldToken.toLowerCase() !== OLD_PRANA.toLowerCase()) {
    throw new Error(`CONTRADICTION : MarketplaceEscrow.sntToken()=${oldToken} ≠ ancien Prana — STOP avant dépense.`);
  }
  console.log("✓ Marketplace actuel lié à l'ancien Prana :", oldToken);
  console.log("  (sntToken immutable, aucun setter -> re-déploiement marketplace validé)");
  const nextOfferId = await oldMarket.nextOfferId();
  if (nextOfferId !== 1n) throw new Error(`nextOfferId=${nextOfferId} != 1 : escrow actif sur l'ancien marketplace — STOP.`);
  console.log("✓ nextOfferId == 1 : aucun escrow actif, abandon de l'ancien marketplace sans perte.");

  // --- 1) Deploy PranaToken (owner = deployer, mint 1M au deployer) ---
  console.log("\n--- 1) Deploy PranaToken ---");
  const balanceBefore = balance;
  const PranaToken = await ethers.getContractFactory("PranaToken");
  const pranaToken = await PranaToken.deploy();
  await pranaToken.waitForDeployment();
  const pranaAddr = await pranaToken.getAddress();
  const pranaTxHash = pranaToken.deploymentTransaction().hash;
  console.log("✓ PranaToken :", pranaAddr);
  console.log("  tx:", pranaTxHash);
  const balAfterPrana = await ethers.provider.getBalance(deployer.address);
  console.log("  gas dépensé (deploy Prana):", ethers.formatEther(balanceBefore - balAfterPrana), "TST");

  // --- 2) Deploy MarketplaceEscrow (sntToken = NOUVEAU Prana, owner = deployer) ---
  console.log("\n--- 2) Deploy MarketplaceEscrow ---");
  const MarketplaceEscrow = await ethers.getContractFactory("MarketplaceEscrow");
  const marketplaceEscrow = await MarketplaceEscrow.deploy(pranaAddr, deployer.address);
  await marketplaceEscrow.waitForDeployment();
  const marketplaceAddr = await marketplaceEscrow.getAddress();
  const marketTxHash = marketplaceEscrow.deploymentTransaction().hash;
  console.log("✓ MarketplaceEscrow :", marketplaceAddr);
  console.log("  tx:", marketTxHash);
  const balAfterMarket = await ethers.provider.getBalance(deployer.address);
  console.log("  gas dépensé (deploy marketplace):", ethers.formatEther(balAfterPrana - balAfterMarket), "TST");

  // --- 3) Funding PRANA : PAYOUT_OPERATOR (100k) puis DEV (50k) — séquentiel ---
  console.log("\n--- 3) Funding PRANA (séquentiel) ---");
  const txPayout = await pranaToken.connect(deployer).transfer(PAYOUT_OPERATOR, PAYOUT_PRANA);
  await txPayout.wait();
  console.log("✓ transfer 100 000 PRANA -> PAYOUT_OPERATOR | tx:", txPayout.hash);
  const txDev = await pranaToken.connect(deployer).transfer(DEV_WALLET, DEV_PRANA);
  await txDev.wait();
  console.log("✓ transfer 50 000 PRANA -> DEV | tx:", txDev.hash);

  // --- 4) Vérifications on-chain (lecture seule) ---
  console.log("\n--- 4) Vérifications on-chain ---");
  const [name, symbol, pranaOwner, supply] = await Promise.all([
    pranaToken.name(), pranaToken.symbol(), pranaToken.owner(), pranaToken.totalSupply(),
  ]);
  console.log("name/symbol      :", name + "/" + symbol, "(attendu Prana/PRANA)");
  console.log("owner            :", pranaOwner, pranaOwner.toLowerCase() === deployer.address.toLowerCase() ? "== deployer ✓" : "❌ ERREUR");
  console.log("totalSupply      :", ethers.formatEther(supply), "PRANA (attendu 1000000)");
  const [bPayout, bDev, bDeployer] = await Promise.all([
    pranaToken.balanceOf(PAYOUT_OPERATOR),
    pranaToken.balanceOf(DEV_WALLET),
    pranaToken.balanceOf(deployer.address),
  ]);
  console.log("balanceOf(payout):", ethers.formatEther(bPayout), "PRANA (attendu 100000)");
  console.log("balanceOf(dev)   :", ethers.formatEther(bDev), "PRANA (attendu 50000)");
  console.log("balanceOf(deployer):", ethers.formatEther(bDeployer), "PRANA (restant)");
  const mpOwner = await marketplaceEscrow.owner();
  const mpToken = await marketplaceEscrow.sntToken();
  console.log("marketplace.owner   :", mpOwner, mpOwner.toLowerCase() === deployer.address.toLowerCase() ? "== deployer ✓" : "❌");
  console.log("marketplace.sntToken:", mpToken, mpToken.toLowerCase() === pranaAddr.toLowerCase() ? "== NOUVEAU Prana ✓" : "❌ LIEN INCORRECT");
  const bpCode = await ethers.provider.getCode(BATTLEPOOL);
  const wavaxCode = await ethers.provider.getCode(WAVAX);
  const bpOk = bpCode !== "0x";
  const wavaxOk = wavaxCode !== "0x";
  console.log("invariants: Battlepool présent:", bpOk, "| WAVAX présent:", wavaxOk, "(adresses inchangées)");
  if (!bpOk || !wavaxOk) throw new Error("INVARIANT VIOLÉ : Battlepool ou WAVAX absent — STOP.");

  // --- 5) Resync smart_contracts/config.js (adresses + ABIs depuis artifacts) ---
  console.log("\n--- 5) Sync smart_contracts/config.js ---");
  syncBridgeConfig({ gameAddr: BATTLEPOOL, pranaAddr, marketplaceAddr, wavaxAddr: WAVAX });
  console.log("note: game/wavax réécrits à l'IDENTIQUE (invariants), snt+marketplace remplacés.");

  // --- 6) Mise à jour pingala_deployments.json (adresses + tx hashes, 0 PK) ---
  console.log("\n--- 6) pingala_deployments.json ---");
  const dep = JSON.parse(fs.readFileSync(DEPLOYMENTS_FILE, "utf8"));
  dep.deployedAt = new Date().toISOString();
  dep.contracts.pranaToken = { address: pranaAddr, alias: "snt (config.js)", deployTxHash: pranaTxHash, replaced: OLD_PRANA };
  dep.contracts.marketplaceEscrow = { address: marketplaceAddr, deployTxHash: marketTxHash, replaced: OLD_MARKETPLACE };
  dep.pranaInitialOwnerNote =
    "Correctif audit appliqué : constructeur PranaToken = Ownable(msg.sender) + mint 1M au deployer " +
    "(0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee). Ex-placeholder INITIAL_OWNER_ADDRESS 0x8C3229EC…43D abrogé. " +
    `Anciennes adresses mortes : prana=${OLD_PRANA} marketplace=${OLD_MARKETPLACE}. ` +
    "Funding initial : 100000 PRANA -> PAYOUT_OPERATOR, 50000 -> DEV.";
  fs.writeFileSync(DEPLOYMENTS_FILE, JSON.stringify(dep, null, 2));
  console.log("✅ pingala_deployments.json mis à jour (adresses + tx hashes, aucune clé).");

  console.log("\n=== RÉCAP ===");
  console.log("PRANA (nouveau)     :", pranaAddr);
  console.log("MARKETPLACE (nouveau):", marketplaceAddr);
  console.log("BATTLEPOOL (invariant):", BATTLEPOOL);
  console.log("WAVAX (invariant)     :", WAVAX);
}

/**
 * Resynchronise smart_contracts/config.js : adresses game/snt/marketplace/wavax
 * + ABIs depuis les artifacts (pattern findArrayEnd/replaceAbi, identique à
 * full_deploy_pingala.js / full_deploy.js). game et wavax sont réécrits à
 * l'identique (invariants) ; snt (alias PranaToken) et marketplace remplacés.
 */
function syncBridgeConfig({ gameAddr, pranaAddr, marketplaceAddr, wavaxAddr }) {
  if (!fs.existsSync(CONFIG_PATH)) {
    console.warn("⚠️ config.js introuvable à", CONFIG_PATH, "— pas de sync (à faire manuellement)");
    return;
  }
  const artifactsDir = path.join(__dirname, "..", "artifacts", "contracts");
  const bpArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "Battlepool.sol", "Battlepool.json"), "utf8"));
  const pranaArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "PranaToken.sol", "PranaToken.json"), "utf8"));
  const mpArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "MarketplaceEscrow.sol", "MarketplaceEscrow.json"), "utf8"));
  const wavaxArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "WAVAX.sol", "WAVAX.json"), "utf8"));

  let c = fs.readFileSync(CONFIG_PATH, "utf8");

  // --- Adresses (clés alias `game`/`snt`/`marketplace` conservées) ---
  c = c.replace(/game:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `game: {\n${ind}address: "${gameAddr}"`);
  c = c.replace(/snt:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `snt: {\n${ind}address: "${pranaAddr}"`);
  c = c.replace(/marketplace:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `marketplace: {\n${ind}address: "${marketplaceAddr}"`);
  c = c.replace(/wavax:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `wavax: {\n${ind}address: "${wavaxAddr}"`);

  // --- ABIs depuis les artifacts (pattern findArrayEnd/replaceAbi, identique à full_deploy_pingala.js) ---
  const findArrayEnd = (str, startIdx) => {
    let depth = 0, inString = false, escape = false;
    for (let i = startIdx; i < str.length; i++) {
      const ch = str[i];
      if (escape) { escape = false; continue; }
      if (ch === "\\") { escape = true; continue; }
      if (ch === '"') { inString = !inString; continue; }
      if (inString) continue;
      if (ch === "[") depth++;
      else if (ch === "]") { depth--; if (depth === 0) return i; }
    }
    return -1;
  };
  const replaceAbi = (str, sectionName, abi) => {
    const secIdx = str.indexOf(sectionName + ": {");
    if (secIdx === -1) throw new Error("section " + sectionName + " introuvable dans config.js");
    const abiIdx = str.indexOf("abi:", secIdx);
    const arrStart = str.indexOf("[", abiIdx);
    const arrEnd = findArrayEnd(str, arrStart);
    if (arrEnd === -1) throw new Error("fin de tableau ABI introuvable pour " + sectionName);
    return str.slice(0, arrStart) + JSON.stringify(abi, null, 2) + str.slice(arrEnd + 1);
  };
  c = replaceAbi(c, "game", bpArtifact.abi);
  c = replaceAbi(c, "snt", pranaArtifact.abi);
  c = replaceAbi(c, "marketplace", mpArtifact.abi);
  c = replaceAbi(c, "wavax", wavaxArtifact.abi);

  fs.writeFileSync(CONFIG_PATH, c);
  console.log(`✅ config.js synchronisé : snt(Prana)=${pranaAddr} marketplace=${marketplaceAddr} | game=${gameAddr} wavax=${wavaxAddr} (invariants réécrits à l'identique)`);
  console.log(`   ABIs : game(${bpArtifact.abi.length}), snt(${pranaArtifact.abi.length}), marketplace(${mpArtifact.abi.length}), wavax(${wavaxArtifact.abi.length})`);
}

main().catch((error) => {
  console.error("❌ REDEPLOY ÉCHOUÉ :", error && error.message ? error.message : error);
  process.exitCode = 1;
});