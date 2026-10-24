/**
 * deploy_avaxe.js — DÉPLOIEMENT AVAX.e sur Pingala Chain (chainId 99999).
 *
 * Chantier feat/avaxe-bridge. Déploie les DEUX nouveaux contrats ERC-20 :
 *   1. AVAXe            (ERC-20 "Wrapped AVAX", mint/burn opérateur bridge)
 *   2. BattlepoolV2     (port ERC-20 de Battlepool, devise = AVAXe)
 *
 * Ne touche PAS Battlepool.sol ni les contrats existants. Les rôles
 * (signer / payoutOperator / devWallet) réutilisent les clés PINGALA_*
 * déjà provisionnées et financées (mêmes acteurs off-chain).
 *
 * Sécurité des clés : identique à full_deploy_pingala.js —
 * PINGALA_DEPLOYER_PK lu depuis battlepool/.env ou /run/secrets, jamais
 * loggé ; les rôles absents sont GÉNÉRÉS et persistés dans battlepool/.env.
 *
 * Post-deploy : écrit battlepool/avaxe_deployments.json (adresses + tx
 * hashes, SANS clé).
 *
 * Modes :
 *   PINGALA_DRY_RUN=1          → aucune tx, aucun livrable (répétition).
 *   PINGALA_LOCAL_REHEARSAL=1  → flux complet sur hardhat in-process,
 *                                livrable redirigé .rehearsal.json.
 */
const { ethers, network } = require("hardhat");
const fs = require("fs");
const path = require("path");

const DEPLOYMENTS_FILE = path.join(__dirname, "avaxe_deployments.json");
const ENV_FILE = path.join(__dirname, ".env");

const DRY_RUN = process.env.PINGALA_DRY_RUN === "1";
const LOCAL_REHEARSAL = process.env.PINGALA_LOCAL_REHEARSAL === "1";
if (LOCAL_REHEARSAL && network.name !== "hardhat") {
  throw new Error("PINGALA_LOCAL_REHEARSAL=1 est réservé au réseau in-process hardhat.");
}
if (DRY_RUN && LOCAL_REHEARSAL) {
  throw new Error("PINGALA_DRY_RUN et PINGALA_LOCAL_REHEARSAL sont exclusifs.");
}
const deploymentsFile = LOCAL_REHEARSAL
  ? path.join(__dirname, "avaxe_deployments.rehearsal.json")
  : DEPLOYMENTS_FILE;

const FUND_AMOUNTS = {
  PINGALA_SIGNER_PK: ethers.parseEther("0.2"),
  PINGALA_PAYOUT_PK: ethers.parseEther("0.5"),
  PINGALA_DEV_PK: ethers.parseEther("0.2"),
};

function readSecret(envVarName) {
  const secretPath = path.join("/run/secrets", envVarName.toLowerCase());
  if (fs.existsSync(secretPath)) {
    try { return fs.readFileSync(secretPath, "utf8").trim(); } catch (e) { /* fallback env */ }
  }
  return process.env[envVarName];
}

function getOrCreateRoleWallet(roleName, envVarName) {
  const pk = readSecret(envVarName);
  if (pk) {
    const w = new ethers.Wallet(pk);
    console.log(`  ${roleName} — clé existante, adresse: ${w.address}`);
    return { wallet: w, generated: false };
  }
  if (DRY_RUN || LOCAL_REHEARSAL) {
    const w = ethers.Wallet.createRandom();
    const mode = DRY_RUN ? "DRY-RUN" : "REHEARSAL";
    console.log(`  ${roleName} — [${mode}] wallet jetable, non persisté (adresse: ${w.address})`);
    return { wallet: w, generated: true };
  }
  const w = ethers.Wallet.createRandom();
  let current = "";
  if (fs.existsSync(ENV_FILE)) {
    current = fs.readFileSync(ENV_FILE, "utf8");
    if (new RegExp(`^${envVarName}=`, "m").test(current)) {
      throw new Error(`${envVarName} déjà présent dans ${ENV_FILE} mais non chargé`);
    }
  }
  fs.appendFileSync(ENV_FILE, `${envVarName}=${w.privateKey}\n`);
  process.env[envVarName] = w.privateKey;
  console.log(`  ${roleName} — clé GÉNÉRÉE, persistée dans battlepool/.env. Adresse : ${w.address}`);
  return { wallet: w, generated: true };
}

async function sendTx(label, buildFn) {
  if (DRY_RUN) {
    console.log(`[DRY-RUN] ${label} — tx simulée, aucune signature réelle`);
    return null;
  }
  const tx = await buildFn();
  const receipt = await tx.wait();
  if (receipt.status !== 1) throw new Error(`${label} : tx REVERT — txHash=${tx.hash}`);
  console.log(`✓ ${label} — tx ${tx.hash} (bloc ${receipt.blockNumber}, gas ${receipt.gasUsed})`);
  return receipt;
}

async function main() {
  console.log(`\n=== DÉPLOIEMENT AVAX.e (AVAXe + BattlepoolV2) sur Pingala (network=${network.name}) ===\n`);
  if (!LOCAL_REHEARSAL && network.name !== "pingala") {
    throw new Error(`Ce script ne tourne que sur --network pingala (actuel : ${network.name}).`);
  }

  const [deployer] = await ethers.getSigners();
  console.log("Deployer :", deployer.address);

  // --- 0) PREFLIGHT ---
  const { chainId } = await ethers.provider.getNetwork();
  if (!LOCAL_REHEARSAL && BigInt(chainId) !== 99999n) {
    throw new Error(`chainId attendu 99999, reçu ${chainId}`);
  }
  const bal = await ethers.provider.getBalance(deployer.address);
  console.log(`chainId=${chainId} | balance=${ethers.formatEther(bal)} TST`);
  if (!DRY_RUN && !LOCAL_REHEARSAL && bal < ethers.parseEther("1.5")) {
    throw new Error(`Balance deployer (${ethers.formatEther(bal)} TST) insuffisante (~1.5 TST requis).`);
  }

  // --- 1) WALLETS DE RÔLES (réutilisés : mêmes acteurs off-chain que Battlepool) ---
  console.log("\n--- Wallets de rôles ---");
  const signerRole = getOrCreateRoleWallet("SIGNER", "PINGALA_SIGNER_PK");
  const payoutRole = getOrCreateRoleWallet("PAYOUT_OPERATOR", "PINGALA_PAYOUT_PK");
  const devRole = getOrCreateRoleWallet("DEV", "PINGALA_DEV_PK");
  const OWNER_ADDRESS = deployer.address;

  // --- 2) FUNDING des wallets de rôles (idempotent) ---
  console.log("\n--- Funding des wallets de rôles ---");
  const fundTargets = [
    { envVar: "PINGALA_SIGNER_PK", label: "SIGNER", wallet: signerRole.wallet },
    { envVar: "PINGALA_PAYOUT_PK", label: "PAYOUT_OPERATOR", wallet: payoutRole.wallet },
    { envVar: "PINGALA_DEV_PK", label: "DEV", wallet: devRole.wallet },
  ];
  for (const { envVar, label, wallet } of fundTargets) {
    const target = FUND_AMOUNTS[envVar];
    const b = await ethers.provider.getBalance(wallet.address);
    if (b >= target / 2n) {
      console.log(`  ${label} ${wallet.address} déjà financé (${ethers.formatEther(b)} TST) — skip`);
      continue;
    }
    await sendTx(`fund ${label} → ${wallet.address} (${ethers.formatEther(target)} TST)`, () =>
      deployer.sendTransaction({ to: wallet.address, value: target })
    );
  }

  // --- 3) AVAXe (ERC-20, aucun arg constructeur) ---
  console.log("\n--- Deploy AVAXe ---");
  const avaxeFactory = await ethers.getContractFactory("AVAXe");
  let avaxe, avaxeAddr, avaxeTxHash;
  if (DRY_RUN) {
    avaxeAddr = "0x0000000000000000000000000000000000000000";
    console.log("[DRY-RUN] AVAXe — déploiement simulé");
  } else {
    avaxe = await avaxeFactory.deploy({ gasLimit: 2_000_000n });
    await avaxe.waitForDeployment();
    avaxeAddr = await avaxe.getAddress();
    avaxeTxHash = avaxe.deploymentTransaction().hash;
    console.log(`✓ AVAXe déployé — tx ${avaxeTxHash}`);
    console.log("AVAXe :", avaxeAddr);
  }

  // --- 4) BattlepoolV2 (token = AVAXe) ---
  // gas déploy mesuré ≈ 5 505 489 → gasLimit 6.5M (marge), plafonné par le
  // block gas limit réel de Pingala (8 000 000, subnet-evm).
  console.log("\n--- Deploy BattlepoolV2 ---");
  const bpv2Factory = await ethers.getContractFactory("BattlepoolV2");
  const blockGasLimit = (await ethers.provider.getBlock("latest")).gasLimit;
  const bpv2GasLimit = 6_500_000n;
  if (bpv2GasLimit >= blockGasLimit) {
    throw new Error(`Gas deploy BattlepoolV2 (${bpv2GasLimit}) >= block gas limit (${blockGasLimit}).`);
  }
  let bpv2, bpv2Addr, bpv2TxHash;
  if (DRY_RUN) {
    bpv2Addr = "0x0000000000000000000000000000000000000000";
    console.log("[DRY-RUN] BattlepoolV2(AVAXe) — déploiement simulé");
  } else {
    bpv2 = await bpv2Factory.deploy(avaxeAddr, { gasLimit: bpv2GasLimit });
    await bpv2.waitForDeployment();
    bpv2Addr = await bpv2.getAddress();
    bpv2TxHash = bpv2.deploymentTransaction().hash;
    console.log(`✓ BattlepoolV2 déployé — tx ${bpv2TxHash}`);
    console.log("BattlepoolV2 :", bpv2Addr);
  }

  // --- 5) PARAMÈTRES OWNER + initializeRoles (fenêtre 100 blocs) ---
  if (!DRY_RUN) {
    console.log("\n--- Paramètres BattlepoolV2 (owner) ---");
    await sendTx("setSecurityCoefficient(1000)", () => bpv2.setSecurityCoefficient(1000));
    await sendTx("setFeeBasisPoints(250)", () => bpv2.setFeeBasisPoints(250));
    await sendTx("setDefaultMaxBaseBet(100)", () => bpv2.setDefaultMaxBaseBet(ethers.parseEther("100")));
    await sendTx("setDefaultMaxQ(2.0)", () => bpv2.setDefaultMaxQ(ethers.parseEther("2")));

    console.log("\n--- initializeRoles(owner, signer, payoutOperator, devWallet) ---");
    await sendTx("initializeRoles", () =>
      bpv2.initializeRoles(
        OWNER_ADDRESS,
        signerRole.wallet.address,
        payoutRole.wallet.address,
        ethers.getAddress(devRole.wallet.address)
      )
    );
  }

  if (DRY_RUN) {
    console.log("\n=== REPÉTITION TERMINÉE — aucune tx envoyée, aucun livrable écrit ===");
    return;
  }

  // --- 6) LIVRABLE (SANS CLÉ) ---
  const deployments = {
    network: LOCAL_REHEARSAL ? "hardhat (répétition locale)" : "pingala",
    chainId: LOCAL_REHEARSAL ? 31337 : 99999,
    chainName: LOCAL_REHEARSAL ? "Réseau de répétition local" : "Pingala Chain",
    rpcUrl: LOCAL_REHEARSAL ? "hardhat in-process" : (process.env.PINGALA_RPC_URL || ""),
    deployedAt: new Date().toISOString(),
    deployer: { address: deployer.address },
    roles: {
      owner: { address: OWNER_ADDRESS },
      signer: { address: signerRole.wallet.address, envVar: "PINGALA_SIGNER_PK", keyGenerated: signerRole.generated },
      payoutOperator: { address: payoutRole.wallet.address, envVar: "PINGALA_PAYOUT_PK", keyGenerated: payoutRole.generated },
      devWallet: { address: devRole.wallet.address, envVar: "PINGALA_DEV_PK", keyGenerated: devRole.generated },
    },
    contracts: {
      avaxe: { address: avaxeAddr, deployTxHash: avaxeTxHash },
      battlepoolV2: { address: bpv2Addr, token: avaxeAddr, deployTxHash: bpv2TxHash },
    },
    note: "Gas = TST natif. AVAXe mint/burn = owner (opérateur bridge custodial). BattlepoolV2 devise = AVAXe (ERC-20).",
  };
  fs.writeFileSync(deploymentsFile, JSON.stringify(deployments, null, 2));
  console.log("\n✅ Déployé. Adresses + tx hashes →", deploymentsFile, "(aucune clé privée dedans)");
  console.log("=== DÉPLOIEMENT AVAX.e TERMINÉ SANS ERREUR ===");
}

main().catch((error) => {
  console.error("\n❌ ÉCHEC DU DÉPLOIEMENT AVAX.e :", error && error.message ? error.message : error);
  process.exitCode = 1;
});
