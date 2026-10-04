/**
 * verify_pingala.js — Vérification post-déploiement on-chain Pingala (99999).
 *
 * Usage : npx hardhat run scripts/verify_pingala.js --network pingala
 * Lecture seule : aucune tx envoyée. La clé du deployer est requise par la
 * config hardhat (signer unique), mais aucune tx n'est signée/envoyée ici.
 *
 * Sources d'adresses : battlepool/pingala_deployments.json (écrit au deploy).
 */
const { ethers, network } = require("hardhat");
const fs = require("fs");
const path = require("path");

const DEPLOYMENTS_FILE = path.join(__dirname, "..", "pingala_deployments.json");
// Ownership PranaToken : le DEPLOYER = owner (mintage 1M PRANA au deployer).
// (Correctif audit : l'ancienne constante placeholder 0x8C3229EC…43D est abrogée ;
// vérifie maintenant un ownership contrôlable, aligné sur les tests unitaires.)
const PRANA_INITIAL_OWNER = "0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee";

async function main() {
  console.log(`=== VÉRIFICATION ON-CHAIN — ${network.name} ===`);
  if (network.name !== "pingala") {
    throw new Error(`Script réservé au réseau pingala (actuel : ${network.name})`);
  }
  const dep = JSON.parse(fs.readFileSync(DEPLOYMENTS_FILE, "utf8"));
  const C = dep.contracts;
  const provider = ethers.provider;

  const chainId = (await provider.getNetwork()).chainId;
  const block = await provider.getBlockNumber();
  console.log("chainId:", chainId.toString(), "(attendu 99999)", "| bloc:", block);
  console.log("deployer:", dep.deployer.address, "| balance:", ethers.formatEther(await provider.getBalance(dep.deployer.address)), "TST");

  // --- Battlepool ---
  console.log("--- Battlepool ---");
  const bp = await ethers.getContractAt("Battlepool", C.battlepool.address);
  const [
    bpBalance, fee, sc, maxBet, maxQ, minCd,
    owner, signer, payout, devWallet, deployBlock, stagnant, poolMaxSize,
  ] = await Promise.all([
    bp.getContractBalance(), bp.feeBasisPoints(), bp.securityCoefficient(),
    bp.defaultMaxBaseBet(), bp.defaultMaxQ(), bp.defaultMinCooldown(),
    bp.owner(), bp.signer(), bp.payoutOperator(), bp.devWallet(),
    bp.deployBlock(), bp.stagnantBlockLimit(), bp.defaultPoolMaxSize(),
  ]);
  const rolesOk =
    owner.toLowerCase() === dep.deployer.address.toLowerCase() &&
    signer.toLowerCase() === dep.roles.signer.address.toLowerCase() &&
    payout.toLowerCase() === dep.roles.payoutOperator.address.toLowerCase() &&
    devWallet.toLowerCase() === dep.roles.devWallet.address.toLowerCase();
  console.log(rolesOk ? "✓ rôles on-chain == pingala_deployments.json" : "❌ MISMATCH DE RÔLES");
  console.log("owner           :", owner, "(deployer attendu)");
  console.log("signer          :", signer);
  console.log("payoutOperator  :", payout);
  console.log("devWallet       :", devWallet);
  console.log("contractBalance :", ethers.formatEther(bpBalance), "TST (attendu 0)");
  console.log("feeBasisPoints  :", fee.toString(), "(attendu 250)");
  console.log("securityCoefficient:", sc.toString(), "(attendu 1000)");
  console.log("defaultMaxBaseBet:", ethers.formatEther(maxBet), "TST (attendu 100)");
  console.log("defaultMaxQ     :", ethers.formatEther(maxQ), "(attendu 2.0)");
  console.log("defaultMinCooldown:", minCd.toString(), "s (attendu 86400 = défaut constructeur)");
  console.log("defaultPoolMaxSize:", poolMaxSize.toString());
  console.log("stagnantBlockLimit:", stagnant.toString());
  console.log("deployBlock     :", deployBlock.toString());

  // --- PranaToken ---
  console.log("--- PranaToken (alias config `snt`) ---");
  const prana = await ethers.getContractAt("PranaToken", C.pranaToken.address);
  const [name, symbol, decimals, totalSupply, pranaOwner, initialOwnerBal] = await Promise.all([
    prana.name(), prana.symbol(), prana.decimals(), prana.totalSupply(), prana.owner(),
    prana.balanceOf(PRANA_INITIAL_OWNER),
  ]);
  console.log("name/symbol     :", name + "/" + symbol, "(attendu Prana/PRANA)");
  console.log("decimals        :", decimals.toString());
  console.log("totalSupply     :", ethers.formatEther(totalSupply), "PRANA (attendu 1000000)");
  console.log("owner           :", pranaOwner, "(deployer — correctif audit : owns la supply initiale)");
  console.log("balanceOf(initial owner):", ethers.formatEther(initialOwnerBal), "PRANA");

  // --- MarketplaceEscrow ---
  console.log("--- MarketplaceEscrow ---");
  const mp = await ethers.getContractAt("MarketplaceEscrow", C.marketplaceEscrow.address);
  const [mpOwner, mpToken] = await Promise.all([mp.owner(), mp.sntToken()]);
  console.log("owner:", mpOwner, "| token:", mpToken,
    mpToken.toLowerCase() === C.pranaToken.address.toLowerCase() ? "✓ lié à PranaToken" : "❌ LIEN INCORRECT");
  console.log("balance:", ethers.formatEther(await provider.getBalance(C.marketplaceEscrow.address)), "TST (attendu 0)");

  // --- WAVAX (invariant 1:1) ---
  console.log("--- WAVAX ---");
  const wavax = await ethers.getContractAt("WAVAX", C.wavax.address);
  const [wavaxName, wavaxSymbol, wavaxDecimals, wavaxSupply, wavaxContractAvax] = await Promise.all([
    wavax.name(), wavax.symbol(), wavax.decimals(), wavax.totalSupply(),
    provider.getBalance(C.wavax.address),
  ]);
  console.log("name/symbol     :", wavaxName + "/" + wavaxSymbol, "(attendu Wrapped AVAX/WAVAX)");
  console.log("totalSupply     :", ethers.formatEther(wavaxSupply), "WAVAX");
  console.log("balance native  :", ethers.formatEther(wavaxContractAvax), "TST",
    wavaxSupply === wavaxContractAvax ? "✓ invariant 1:1 OK" : "❌ INVARIANT VIOLÉ");

  console.log("\n=== FIN VÉRIFICATION (lecture seule, aucune tx envoyée) ===");
}

main().catch((error) => {
  console.error("❌ VÉRIFICATION ÉCHOUÉE :", error && error.message ? error.message : error);
  process.exitCode = 1;
});