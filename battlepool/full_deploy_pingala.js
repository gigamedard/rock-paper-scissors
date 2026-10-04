/**
 * full_deploy_pingala.js — DÉPLOIEMENT RÉEL sur Pingala Chain (chainId 99999).
 *
 * Phase B — bp-blockchain. Exécution STRICTEMENT séquentielle : une tx
 * à la fois, `await tx.wait()` après chaque envoi (chaîne réelle = nonces
 * séquentiels, aucun remplacement/envoi parallèle).
 *
 * Sécurité des clés :
 * - PINGALA_DEPLOYER_PK : lu depuis battlepool/.env (gitignored, chargé par
 *   hardhat.config.js) ou /run/secrets/pingala_deployer_pk. JAMAIS loggée,
 *   JAMAIS écrite dans un fichier versionné.
 * - PINGALA_SIGNER_PK / PINGALA_PAYOUT_PK / PINGALA_DEV_PK : si absentes,
 *   elles sont GÉNÉRÉES ALÉATOIREMENT (ethers.Wallet.createRandom) et
 *   PERSISTÉES dans battlepool/.env (gitignored). Seules les ADRESSES sont
 *   affichées/loggées.
 *
 * Ordre des txs (l'ordre des CONTRATS détermine les adresses ; règle d'or :
 * Battlepool → PranaToken → MarketplaceEscrow d'abord, WAVAX nouveau en
 * DERNIER pour ne pas décaler les 3 contrats consommés) :
 *   fund rôles (gas) → deploy Battlepool → 4 params owner → initializeRoles(4)
 *   → deploy PranaToken → deploy MarketplaceEscrow → deploy WAVAX.
 * Le funding précède le deploy : initializeRoles n'est valide que dans les
 * 100 blocs suivant le deploy de Battlepool ; funder avant libère cette
 * fenêtre sans retoucher à l'ordre des contrats.
 *
 * Mode répétition générale : PINGALA_DRY_RUN=1 → AUCUNE tx envoyée, AUCUN
 * livrable écrit (ni pingala_deployments.json réel, ni config.js). Les gas
 * des déploiements sont estimés sur le RPC réel ; les txs admin/fund/init
 * sont listées hors-mesure (impossibles à estimer sans contrat déployé).
 *
 * Post-deploy : écrit battlepool/pingala_deployments.json (adresses +
 * tx hashes, SANS clé) et resynchronise smart_contracts/config.js.
 */
const { ethers, network } = require("hardhat");
const fs = require("fs");
const path = require("path");

const DEPLOYMENTS_FILE = path.join(__dirname, "pingala_deployments.json");
const ENV_FILE = path.join(__dirname, ".env");
const CONFIG_PATH = path.join(__dirname, "..", "smart_contracts", "config.js");

// Répétition générale (aucune tx, aucun livrable réel).
const DRY_RUN = process.env.PINGALA_DRY_RUN === "1";
// Répétition LOCALE : exécute le flux COMPLET (vraies txs) sur le réseau
// Hardhat in-process, pour auditer le script sans toucher Pingala. Livrables
// redirigés vers pingala_deployments.rehearsal.json ; clés NON persistées.
const LOCAL_REHEARSAL = process.env.PINGALA_LOCAL_REHEARSAL === "1";
if (LOCAL_REHEARSAL && network.name !== "hardhat") {
  throw new Error("PINGALA_LOCAL_REHEARSAL=1 est réservé au réseau in-process hardhat (jamais sur une vraie chaîne).");
}
if (DRY_RUN && LOCAL_REHEARSAL) {
  throw new Error("PINGALA_DRY_RUN et PINGALA_LOCAL_REHEARSAL sont exclusifs.");
}
const deploymentsFile = LOCAL_REHEARSAL
  ? path.join(__dirname, "pingala_deployments.rehearsal.json")
  : DEPLOYMENTS_FILE;

const FUND_AMOUNTS = {
  // SIGNER ne fait qu'off-chain (EIP-191) : un peu de gas par précaution.
  PINGALA_SIGNER_PK: ethers.parseEther("0.2"),
  // PAYOUT_OPERATOR envoie les txs de payout — le plus consommateur.
  PINGALA_PAYOUT_PK: ethers.parseEther("0.5"),
  // DEV retire les frais quand le vault devBalance > 0.
  PINGALA_DEV_PK: ethers.parseEther("0.2"),
};
const MIN_DEPLOYER_BALANCE = ethers.parseEther("1.5"); // 4 deploys ≈ 0.21 + fund 0.9 + params ≈ 0.01, marge large

/** Lit une clé privée depuis les secrets Docker, sans jamais la logger. */
function readSecret(envVarName) {
  const secretPath = path.join("/run/secrets", envVarName.toLowerCase());
  if (fs.existsSync(secretPath)) {
    try { return fs.readFileSync(secretPath, "utf8").trim(); } catch (e) { /* fallback env */ }
  }
  return process.env[envVarName];
}

/**
 * Retourne le wallet du rôle demandé. Si la clé est absente, GÉNÈRE une clé
 * aléatoire et la persiste dans battlepool/.env (gitignored) — seules les
 * ADRESSES sont affichées. En DRY-RUN : wallet jetable, RIEN n'est persisté.
 */
function getOrCreateRoleWallet(roleName, envVarName) {
  const pk = readSecret(envVarName);
  if (pk) {
    const w = new ethers.Wallet(pk);
    console.log(`  ${roleName} — clé existante (valeur non affichée), adresse: ${w.address}`);
    return { wallet: w, generated: false, dryRun: false };
  }
  if (DRY_RUN || LOCAL_REHEARSAL) {
    // Dry-run ET répétition locale : wallet jetable, RIEN n'est persisté.
    const w = ethers.Wallet.createRandom();
    const mode = DRY_RUN ? "DRY-RUN" : "REHEARSAL";
    console.log(`  ${roleName} — [${mode}] wallet jetable, non persisté (adresse: ${w.address})`);
    return { wallet: w, generated: true, dryRun: true };
  }
  const w = ethers.Wallet.createRandom();
  let current = "";
  if (fs.existsSync(ENV_FILE)) {
    current = fs.readFileSync(ENV_FILE, "utf8");
    if (new RegExp(`^${envVarName}=`, "m").test(current)) {
      throw new Error(`${envVarName} déjà présent dans ${ENV_FILE} mais non chargé — vérifier le chargement dotenv`);
    }
  }
  fs.appendFileSync(ENV_FILE, `${envVarName}=${w.privateKey}\n`);
  process.env[envVarName] = w.privateKey; // dispo pour le process courant
  console.log(`  ${roleName} — clé GÉNÉRÉE à froid, persistée dans battlepool/.env (gitignored). Adresse : ${w.address}`);
  return { wallet: w, generated: true, dryRun: false };
}

/** Envoie une tx et attends le minage. Une seule tx en vol à la fois. */
async function sendTx(label, buildFn) {
  if (DRY_RUN) {
    console.log(`[DRY-RUN] ${label} — tx simulée, aucune signature réelle (gas non mesurable sans contrat déployé)`);
    return null;
  }
  const tx = await buildFn();
  const receipt = await tx.wait();
  if (receipt.status !== 1) throw new Error(`${label} : tx minée avec status=${receipt.status} (REVERT) — txHash=${tx.hash}`);
  console.log(`✓ ${label} — tx ${tx.hash} (bloc ${receipt.blockNumber}, gas ${receipt.gasUsed})`);
  return receipt;
}

async function main() {
  console.log(`\n=== DÉPLOIEMENT PINGALA CHAIN (network=${network.name}) ===\n`);
  if (!LOCAL_REHEARSAL && network.name !== "pingala") {
    throw new Error(`Ce script ne doit tourner que sur --network pingala (actuel : ${network.name}). Pour la répétition locale : PINGALA_LOCAL_REHEARSAL=1 npx hardhat run full_deploy_pingala.js`);
  }
  if (DRY_RUN) console.log("[DRY-RUN] répétition générale : AUCUNE tx envoyée, AUCUN livrable écrit.");
  if (LOCAL_REHEARSAL) console.log("[REHEARSAL] répétition locale sur hardhat in-process : txs réelles sur le réseau de test, livrables redirigés (" + path.basename(deploymentsFile) + ").");

  const [deployer] = await ethers.getSigners();
  console.log("Deployer :", deployer.address);

  // --- 0) PREFLIGHT ---
  const { chainId } = await ethers.provider.getNetwork();
  if (!LOCAL_REHEARSAL && BigInt(chainId) !== 99999n) {
    throw new Error(`chainId attendu 99999, reçu ${chainId}`);
  }
  const feeData = await ethers.provider.getFeeData();
  const bal = await ethers.provider.getBalance(deployer.address);
  const nonceLatest = await ethers.provider.getTransactionCount(deployer.address, "latest");
  const noncePending = await ethers.provider.getTransactionCount(deployer.address, "pending");
  console.log(
    `chainId=${chainId} | balance=${ethers.formatEther(bal)} TST | nonce latest=${nonceLatest} pending=${noncePending}`,
    `| gasPrice=${feeData.gasPrice ? BigInt(feeData.gasPrice) / 10n ** 9n : "?"} gwei`
  );
  if (noncePending !== nonceLatest) {
    throw new Error(`Nonce pending (${noncePending}) ≠ latest (${nonceLatest}) : tx bloquée en mempool. STOP.`);
  }
  if (!DRY_RUN && !LOCAL_REHEARSAL && bal < MIN_DEPLOYER_BALANCE) {
    throw new Error(`Balance deployer (${ethers.formatEther(bal)} TST) < minimum requis (~${ethers.formatEther(MIN_DEPLOYER_BALANCE)}). STOP — financer le deployer.`);
  }

  // Budget annoncé (déploiements estimés + funding planifié). L'estimation RPC
  // peut être refusée (compte sans fonds en dry-run) : fallback conservateur.
  const FALLBACK_DEPLOY_GAS = {
    battlepool: 3_500_000n, prana: 1_300_000n, marketplace: 2_500_000n, wavax: 900_000n,
  };
  const factories = {
    battlepool: await ethers.getContractFactory("Battlepool"),
    prana: await ethers.getContractFactory("PranaToken"),
    marketplace: await ethers.getContractFactory("MarketplaceEscrow"),
    wavax: await ethers.getContractFactory("WAVAX"),
  };
  const gasPriceBig = BigInt(feeData.gasPrice ?? 0) || 25n * 10n ** 9n;
  let estDeployCost = 0n;
  const estGasDeploys = {};
  for (const [name, f] of Object.entries(factories)) {
    let est;
    try {
      est = await deployer.estimateGas({ type: 2, data: f.bytecode });
    } catch (e) {
      const msg = e && e.message ? String(e.message).slice(0, 100) : "erreur inconnue";
      est = FALLBACK_DEPLOY_GAS[name];
      console.warn(`[BUDGET] estimation RPC impossible pour ${name} (${msg}) — gas conservateur ${est}`);
    }
    estGasDeploys[name] = est;
    estDeployCost += est * gasPriceBig;
  }
  const estFundCost = Object.values(FUND_AMOUNTS).reduce((a, b) => a + b, 0n);
  console.log("[BUDGET] gas deploys estimés :", Object.entries(estGasDeploys).map(([k, v]) => `${k}=${v}`).join(", "));
  console.log(`[BUDGET] déploiements ≈ ${ethers.formatEther(estDeployCost)} TST, + funding rôles ${ethers.formatEther(estFundCost)} TST, + ~10 txs admin/init (gas faible, non estimable ici)`);
  if (!DRY_RUN && !LOCAL_REHEARSAL && bal < estDeployCost + estFundCost) {
    throw new Error(`Budget insuffisant : besoin ≈ ${ethers.formatEther(estDeployCost + estFundCost)} TST, disponible ${ethers.formatEther(bal)}. STOP — financer.`);
  }

  // --- 1) WALLETS DE RÔLES (créés/chargés AVANT le deploy) ---
  console.log("\n--- Wallets de rôles (clés jamais affichées) ---");
  const signerRole = getOrCreateRoleWallet("SIGNER", "PINGALA_SIGNER_PK");
  const payoutRole = getOrCreateRoleWallet("PAYOUT_OPERATOR", "PINGALA_PAYOUT_PK");
  const devRole = getOrCreateRoleWallet("DEV", "PINGALA_DEV_PK");
  const OWNER_ADDRESS = deployer.address; // owner = adresse du deployer (décision brief)
  const DEV_ADDRESS = devRole.wallet.address;

  // --- 2) FUNDING DES WALLETS DE RÔLES (idempotent) ---
  console.log("\n--- Funding des wallets de rôles (beta) ---");
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

  // --- 3) BATTLEPOOL (premier contrat) ---
  console.log("\n--- Deploy Battlepool ---");
  // GasLimit = estimation bytecode + marge, PLAFONNÉ par le block gas limit
  // réel de la chaîne (Pingala subnet-evm : 8 000 000). Un gasLimit hardcodé
  // au-dessus du plafond fait rejeter la tx par le nœud (experienced Phase B).
  const blockGasLimit = (await ethers.provider.getBlock("latest")).gasLimit;
  const BP_GAS_MARGIN = 500_000n;
  const bpGasLimit = estGasDeploys.battlepool + BP_GAS_MARGIN;
  if (bpGasLimit >= blockGasLimit) {
    throw new Error(`Gas deploy Battlepool (${bpGasLimit}) >= block gas limit (${blockGasLimit}) — impossible de déployer sur cette chaîne. STOP.`);
  }
  console.log(`  gasLimit deploy = ${bpGasLimit} (estimation ${estGasDeploys.battlepool} + marge ${BP_GAS_MARGIN}, block limit ${blockGasLimit})`);
  let battlepool, gameAddr, bpDeployTxHash;
  if (DRY_RUN) {
    gameAddr = ethers.getCreateAddress({ from: deployer.address, nonce: nonceLatest + 3n }); // +3 fund txs
    console.log(`[DRY-RUN] Battlepool — gas estimé ${estGasDeploys.battlepool} → adresse simulée ${gameAddr}`);
  } else {
    battlepool = await factories.battlepool.deploy({ gasLimit: bpGasLimit });
    await battlepool.waitForDeployment();
    gameAddr = await battlepool.getAddress();
    bpDeployTxHash = battlepool.deploymentTransaction().hash;
    console.log(`✓ Battlepool déployé — tx ${bpDeployTxHash}`);
    console.log("Battlepool :", gameAddr);
  }

  // --- 4) PARAMÈTRES OWNER (avant initializeRoles : deployer est encore owner) ---
  console.log("\n--- Paramètres Battlepool (owner) ---");
  await sendTx("setSecurityCoefficient(1000)", () => battlepool.setSecurityCoefficient(1000));
  await sendTx("setFeeBasisPoints(250)", () => battlepool.setFeeBasisPoints(250));
  await sendTx("setDefaultMaxBaseBet(100 TST)", () => battlepool.setDefaultMaxBaseBet(ethers.parseEther("100")));
  await sendTx("setDefaultMaxQ(2.0)", () => battlepool.setDefaultMaxQ(ethers.parseEther("2")));
  // NB : defaultMinCooldown NON surchargé — le défaut constructeur (86400 s)
  // fait foi en production. full_deploy.js le force à 10 s pour les tests locaux.
  // NB2 : defaultPoolMaxSize reste au défaut du constructeur (5) — cohérent local.

  // --- 5) INITIALIZE ROLES (4 args, une seule fois, fenêtre 100 blocs) ---
  console.log("\n--- initializeRoles(owner, signer, payoutOperator, devWallet) ---");
  console.log("  owner          →", OWNER_ADDRESS, "(deployer)");
  console.log("  signer         →", signerRole.wallet.address);
  console.log("  payoutOperator →", payoutRole.wallet.address);
  console.log("  devWallet      →", DEV_ADDRESS);
  await sendTx("initializeRoles", () =>
    battlepool.initializeRoles(
      OWNER_ADDRESS,
      signerRole.wallet.address,
      payoutRole.wallet.address,
      ethers.getAddress(DEV_ADDRESS) // payable address
    )
  );
  if (!DRY_RUN && (await battlepool.owner()) !== OWNER_ADDRESS) {
    throw new Error("owner on-chain ≠ OWNER_ADDRESS après initializeRoles");
  }

  // --- 6) PRANATOKEN (ex-SNTToken renommé, alias config `snt` conservé) ---
  console.log("\n--- Deploy PranaToken ---");
  let pranaToken, pranaAddr;
  if (DRY_RUN) {
    pranaAddr = ethers.getCreateAddress({
      from: deployer.address,
      nonce: nonceLatest + 3n /* fund */ + 1n /* battlepool */ + 5n /* params+init */,
    });
    console.log(`[DRY-RUN] PranaToken — gas estimé ${estGasDeploys.prana} → adresse simulée ${pranaAddr}`);
  } else {
    pranaToken = await factories.prana.deploy({ gasLimit: estGasDeploys.prana + 300_000n });
    await pranaToken.waitForDeployment();
    pranaAddr = await pranaToken.getAddress();
    console.log(`✓ PranaToken déployé — tx ${pranaToken.deploymentTransaction().hash}`);
    const pranaInitialOwner = await pranaToken.owner();
    console.log("PranaToken :", pranaAddr);
    console.log("  owner initial (deployer = owner, correctif audit) :", pranaInitialOwner);
  }

  // --- 7) MARKETPLACEESCROW (token, initialOwner) ---
  console.log("\n--- Deploy MarketplaceEscrow ---");
  let marketplaceEscrow, marketplaceAddr;
  if (DRY_RUN) {
    marketplaceAddr = ethers.getCreateAddress({ from: deployer.address, nonce: 0n }); // placeholder, non utilisé
    console.log(`[DRY-RUN] MarketplaceEscrow — gas estimé ${estGasDeploys.marketplace} (adresse: remplie au vrai deploy)`);
  } else {
    marketplaceEscrow = await factories.marketplace.deploy(pranaAddr, deployer.address, { gasLimit: estGasDeploys.marketplace + 1_500_000n });
    await marketplaceEscrow.waitForDeployment();
    marketplaceAddr = await marketplaceEscrow.getAddress();
    console.log(`✓ MarketplaceEscrow déployé — tx ${marketplaceEscrow.deploymentTransaction().hash}`);
    console.log("MarketplaceEscrow :", marketplaceAddr);
  }

  // --- 8) WAVAX (DERNIER — règle d'or : nouveau contrat en fin d'ordre) ---
  console.log("\n--- Deploy WAVAX (en dernier) ---");
  let wavax, wavaxAddr;
  if (DRY_RUN) {
    wavaxAddr = ethers.getCreateAddress({ from: deployer.address, nonce: 0n }); // placeholder, non utilisé
    console.log(`[DRY-RUN] WAVAX — gas estimé ${estGasDeploys.wavax} (adresse: remplie au vrai deploy)`);
  } else {
    wavax = await factories.wavax.deploy({ gasLimit: estGasDeploys.wavax + 300_000n });
    await wavax.waitForDeployment();
    wavaxAddr = await wavax.getAddress();
    console.log(`✓ WAVAX déployé — tx ${wavax.deploymentTransaction().hash}`);
    console.log("WAVAX :", wavaxAddr);
  }

  if (DRY_RUN) {
    console.log("\n=== REPÉTITION GÉNÉRALE TERMINÉE — aucune tx envoyée, aucun livrable écrit ===");
    console.log("=== Lancez le vrai déploiement : npx hardhat run full_deploy_pingala.js --network pingala ===");
    return;
  }

  // --- 9) RÉSUMÉ + FICHIER DES DÉPLOIEMENTS (SANS CLÉ) ---
  const deployments = {
    network: LOCAL_REHEARSAL ? "hardhat (répétition locale PINGALA)" : "pingala",
    chainId: LOCAL_REHEARSAL ? 31337 : 99999,
    chainName: LOCAL_REHEARSAL ? "Réseau de répétition local (hardhat in-process)" : "Pingala Chain",
    rpcUrl: LOCAL_REHEARSAL ? "hardhat in-process" : (process.env.PINGALA_RPC_URL || ""),
    deployedAt: new Date().toISOString(),
    deployer: {
      address: deployer.address,
      // NOTE: aucune clé privée dans ce fichier — uniquement l'adresse publique.
    },
    roles: {
      owner: { address: OWNER_ADDRESS, note: "deployer (clé PINGALA_DEPLOYER_PK, hors bridge)" },
      signer: { address: signerRole.wallet.address, envVar: "PINGALA_SIGNER_PK", keyGenerated: signerRole.generated },
      payoutOperator: { address: payoutRole.wallet.address, envVar: "PINGALA_PAYOUT_PK", keyGenerated: payoutRole.generated },
      devWallet: { address: DEV_ADDRESS, envVar: "PINGALA_DEV_PK", keyGenerated: devRole.generated },
    },
    contracts: {
      battlepool: { address: gameAddr, deployTxHash: bpDeployTxHash },
      pranaToken: { address: pranaAddr, alias: "snt (config.js)", deployTxHash: pranaToken ? pranaToken.deploymentTransaction().hash : null },
      marketplaceEscrow: { address: marketplaceAddr, deployTxHash: marketplaceEscrow ? marketplaceEscrow.deploymentTransaction().hash : null },
      wavax: { address: wavaxAddr, deployTxHash: wavax ? wavax.deploymentTransaction().hash : null },
    },
    pranaInitialOwnerNote:
      "PranaToken minte 1M PRANA au DEPLOYER (owner Ownable via msg.sender, " +
      "constructeur correctif audit : ex-placeholder INITIAL_OWNER_ADDRESS abrogé). " +
      "Le deployer finance ensuite PAYOUT_OPERATOR et DEV directement depuis la supply.",
  };
  fs.writeFileSync(deploymentsFile, JSON.stringify(deployments, null, 2));
  console.log("\n✅ Déployé. Adresses + tx hashes →", deploymentsFile, "(aucune clé privée dedans)");

  // --- 10) SYNCHRONISATION smart_contracts/config.js ---
  if (LOCAL_REHEARSAL) {
    console.log("[REHEARSAL] sync smart_contracts/config.js SKIPPÉE (livrable réel protégé) — adresses simulées uniquement.");
  } else {
    syncBridgeConfig({ gameAddr, pranaAddr, marketplaceAddr, wavaxAddr });
  }

  console.log("\n=== DÉPLOIEMENT PINGALA TERMINÉ SANS ERREUR ===");
  console.log("Prochaines étapes : npx hardhat run scripts/verify_pingala.js --network pingala");
}

/**
 * Resynchronise smart_contracts/config.js :
 * - adresses game / snt (alias PranaToken) / marketplace ;
 * - ABIs game/snt/marketplace depuis les artifacts (source de vérité compilée) ;
 * - section `wavax` ajoutée (ou mise à jour si déjà présente).
 * Le fichier est ESM et monté dans le bridge : structure conservée, ABIs
 * réécrits via le pattern findArrayEnd/replaceAbi de full_deploy.js.
 */
function syncBridgeConfig({ gameAddr, pranaAddr, marketplaceAddr, wavaxAddr }) {
  if (!fs.existsSync(CONFIG_PATH)) {
    console.warn("⚠️ config.js introuvable à", CONFIG_PATH, "— pas de sync (à faire manuellement)");
    return;
  }
  const artifactsDir = path.join(__dirname, "artifacts", "contracts");
  const battlepoolArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "Battlepool.sol", "Battlepool.json"), "utf8"));
  const pranaArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "PranaToken.sol", "PranaToken.json"), "utf8"));
  const mpArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "MarketplaceEscrow.sol", "MarketplaceEscrow.json"), "utf8"));
  const wavaxArtifact = JSON.parse(fs.readFileSync(path.join(artifactsDir, "WAVAX.sol", "WAVAX.json"), "utf8"));

  let c = fs.readFileSync(CONFIG_PATH, "utf8");

  // --- Adresses (adresses uniquement — les clés `snt`/`game`/`marketplace`
  // restent les noms d'alias historiques utilisés par app.js/indexer/PHP) ---
  c = c.replace(/game:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `game: {\n${ind}address: "${gameAddr}"`);
  c = c.replace(/snt:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `snt: {\n${ind}address: "${pranaAddr}"`);
  c = c.replace(/marketplace:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `marketplace: {\n${ind}address: "${marketplaceAddr}"`);

  // --- ABIs (pattern findArrayEnd/replaceAbi de full_deploy.js) ---
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
  c = replaceAbi(c, "game", battlepoolArtifact.abi);
  c = replaceAbi(c, "snt", pranaArtifact.abi);
  c = replaceAbi(c, "marketplace", mpArtifact.abi);

  // --- Section wavax : ajoutée si absente, sinon MAJ adresse+ABI ---
  if (c.indexOf("wavax:") === -1) {
    const wavaxEntry =
      ",\n  // --- WAVAX : wrapper de l'AVAX natif en ERC-20 (pattern WETH9, 1:1, passif) ---\n" +
      "  wavax: {\n" +
      `    address: "${wavaxAddr}",\n` +
      "    abi: " + JSON.stringify(wavaxArtifact.abi, null, 2) + "\n" +
      "  }";
    // Ancrage ROBUSTE : la fermeture de l'objet `contracts` est le dernier
    // `\n  }\n};` avant `export const pinata`. Insertion APRÈS le `  }` qui
    // ferme la dernière section (snt) et AVANT `};`.
    const pinataIdx = c.indexOf("export const pinata");
    if (pinataIdx === -1) throw new Error("ancre `export const pinata` introuvable dans config.js");
    const closeMatchIdx = c.lastIndexOf("\n  }\n};", pinataIdx);
    if (closeMatchIdx === -1) throw new Error("fermeture de l'objet contracts introuvable dans config.js");
    const insertPos = closeMatchIdx + "\n  }".length;
    c = c.slice(0, insertPos) + wavaxEntry + c.slice(insertPos);
  } else {
    c = c.replace(/wavax:\s*\{\s*\n(\s*)address:\s*['"]0x[a-fA-F0-9]+['"]/g, (m, ind) => `wavax: {\n${ind}address: "${wavaxAddr}"`);
    c = replaceAbi(c, "wavax", wavaxArtifact.abi);
  }

  fs.writeFileSync(CONFIG_PATH, c);
  console.log(`✅ config.js synchronisé : game=${gameAddr} snt(alias Prana)=${pranaAddr} marketplace=${marketplaceAddr} wavax=${wavaxAddr}`);
  console.log(`   ABIs : game(${battlepoolArtifact.abi.length}), snt(${pranaArtifact.abi.length}), marketplace(${mpArtifact.abi.length}), wavax(${wavaxArtifact.abi.length})`);
}

main().catch((error) => {
  console.error("\n❌ ÉCHEC DU DÉPLOIEMENT PINGALA :", error && error.message ? error.message : error);
  // Pas de contournement destructif : on s'arrête et on rapporte l'erreur exacte.
  process.exitCode = 1;
});