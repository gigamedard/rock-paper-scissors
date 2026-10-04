/**
 * SANDBOX TEST — Valide syncBridgeConfig sur une COPIE temporaire de config.js.
 * (n'écrit jamais smart_contracts/config.js réel)
 *
 * Exécution : node -e "require('./full_deploy_pingala.js')" est impossible
 * (main() s'exécuterait) ⇒ on extrait la fonction par lecture du fichier :
 * astuce : on temporairement monkey-patch... plus simple : on copie le corps
 * via require d'un module dédié. Solution retenue : évaluer le fichier avec
 * hardhat stubbé.
 */
const fs = require("fs");
const path = require("path");
const Module = require("module");

// Stubs hardhat : ethers minimal pour que require("hardhat") résolve sans
// exécuter main() — on intercepte la fin du script.
const sandboxDir = path.join(__dirname, ".sandbox");
fs.mkdirSync(sandboxDir, { recursive: true });
const srcPath = path.join(__dirname, "..", "full_deploy_pingala.js");
const src = fs.readFileSync(srcPath, "utf8");

// 1) Neutralise main() (renomme l'appel final) et exporte syncBridgeConfig.
const patched = src
  .replace(/main\(\)\.catch[\s\S]*$/, "module.exports = { syncBridgeConfig };")
  .replace(/async function main\(\)/, "async function mainDISABLED()");

const sandboxFile = path.join(sandboxDir, "sandbox_sync.js");
fs.writeFileSync(sandboxFile, patched);

// 2) require("hardhat") du sandbox → redirigé vers un stub.
const stubHardhat = path.join(sandboxDir, "hardhat-stub.js");
fs.writeFileSync(stubHardhat, `
module.exports = {
  ethers: require("ethers"),
  network: { name: "hardhat" },
};
`);
const origResolve = Module._resolveFilename;
Module._resolveFilename = function (request, parent, ...args) {
  if (request === "hardhat" && parent && parent.filename && parent.filename.includes(".sandbox")) {
    return stubHardhat;
  }
  return origResolve.call(this, request, parent, ...args);
};

const { syncBridgeConfig } = require(sandboxFile);

// 3) Prépare une copie du config.js réel avec les adresses de rehearsal.
const REHEARSAL = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "pingala_deployments.rehearsal.json"), "utf8"));
const args = {
  gameAddr: REHEARSAL.contracts.battlepool.address,
  pranaAddr: REHEARSAL.contracts.pranaToken.address,
  marketplaceAddr: REHEARSAL.contracts.marketplaceEscrow.address,
  wavaxAddr: REHEARSAL.contracts.wavax.address,
};
const origConfigPath = path.join(__dirname, "..", "..", "smart_contracts", "config.js");
const backup = fs.readFileSync(origConfigPath, "utf8");
const tmpCopy = path.join(sandboxDir, "config.copy.js");
fs.writeFileSync(tmpCopy, backup);

// 4) Exécute syncBridgeConfig sur la copie : monkey-patch CONFIG_PATH…
// CONFIG_PATH est une const interne — on réécrit le fichier sandbox pour
// pointer la copie. Plus fiable : patcher la source sandbox directement.
let sandboxSrc = fs.readFileSync(sandboxFile, "utf8");
sandboxSrc = sandboxSrc.replace(
  /const CONFIG_PATH = path\.join\(__dirname, "\.\.", "smart_contracts", "config\.js"\);/,
  `const CONFIG_PATH = ${JSON.stringify(tmpCopy).replace(/\\\\/g, "/")};`
);
// Rebase aussi le répertoire des artifacts (__dirname du sandbox → battlepool/).
sandboxSrc = sandboxSrc.replace(
  /const artifactsDir = path\.join\(__dirname, "artifacts", "contracts"\);/,
  `const artifactsDir = ${JSON.stringify(path.join(__dirname, "..", "artifacts", "contracts")).replace(/\\\\/g, "/")};`
);
fs.writeFileSync(sandboxFile, sandboxSrc);
delete require.cache[require.resolve(sandboxFile)];
const { syncBridgeConfig: syncOnCopy } = require(sandboxFile);
syncOnCopy(args);

// 5) Validation de la copie : import ESM réel (le seul test qui compte pour le bridge).
const { execFileSync } = require("child_process");
const { pathToFileURL } = require("url");
const evalCode =
  `import { contracts } from ${JSON.stringify(pathToFileURL(tmpCopy).href)};\n` +
  `console.log(JSON.stringify({\n` +
  `  game: contracts.game.address,\n  snt: contracts.snt.address,\n` +
  `  marketplace: contracts.marketplace.address,\n` +
  `  wavax: contracts.wavax.address,\n` +
  `  abis: { game: contracts.game.abi.length, snt: contracts.snt.abi.length,\n` +
  `         marketplace: contracts.marketplace.abi.length, wavax: contracts.wavax.abi.length }\n` +
  `}));`;
const out = execFileSync("node", ["--input-type=module", "--eval", evalCode],
  { encoding: "utf8", cwd: path.join(__dirname, "..", "..", "smart_contracts") });
console.log("IMPORT ESM OK :", out.trim());

// 6) Assertions : adresses remplacées + ABI WAVAX présente + ABI lengths.
const after = fs.readFileSync(tmpCopy, "utf8");
const checks = {
  "game address replaced": after.includes(args.gameAddr),
  "snt (Prana) address replaced": after.includes(args.pranaAddr),
  "marketplace address replaced": after.includes(args.marketplaceAddr),
  "wavax address present": after.includes(args.wavaxAddr),
  "no legacy battlepool address remains in contracts section": !/game:\s*\{\s*\n\s*address:\s*"0x5FbDB2315678afecb367f032d93F642f64180aa3"/.test(after),
  "no legacy SNT address remains in snt section": !/snt:\s*\{\s*\n\s*address:\s*"0x0165878A594ca255338adfa4d48449f69242Eb8F"/.test(after),
  "no legacy marketplace address remains": !/marketplace:\s*\{\s*\n\s*address:\s*"0xa513E6E4b8f2a923D98304ec87F64353C4D5C853"/.test(after),
};
let failed = 0;
for (const [k, v] of Object.entries(checks)) {
  console.log(`${v ? "✓" : "❌"} ${k}`);
  if (!v) failed++;
}
const parsed = JSON.parse(out.slice(out.indexOf("{")));
const expectedAbiLen = JSON.parse(fs.readFileSync(path.join(__dirname, "..", "artifacts", "contracts", "WAVAX.sol", "WAVAX.json"), "utf8")).abi.length;
if (parsed.abis.wavax !== expectedAbiLen) { console.log(`❌ ABI wavax count mismatch: ${parsed.abis.wavax} ≠ ${expectedAbiLen}`); failed++; }
else console.log(`✓ ABI wavax length = ${parsed.abis.wavax}`);

fs.rmSync(sandboxDir, { recursive: true, force: true });
console.log(failed === 0 ? "\n=== SANDBOX SYNC : TOUS LES CHECKS PASSENT ===" : `\n=== SANDBOX SYNC : ${failed} CHECK(S) EN ÉCHEC ===`);
process.exit(failed === 0 ? 0 : 1);