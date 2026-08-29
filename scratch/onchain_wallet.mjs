import { ethers, Contract } from "ethers";
import { contracts } from "../smart_contracts/config.js";

const ADDR = "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC";
const L = ADDR.toLowerCase();
const p = new ethers.JsonRpcProvider("http://127.0.0.1:8546");

console.log("Solde ETH wallet       :", ethers.formatEther(await p.getBalance(ADDR)));
console.log("Nonce (nb txs envoyées):", await p.getTransactionCount(ADDR));

const game = new Contract(ADDR, contracts.game.abi, p);
try { console.log("userBalances (contrat) :", ethers.formatEther(await game.userBalances(ADDR)), "ETH"); }
catch (e) { console.log("userBalances           : n/a (" + e.shortMessage + ")"); }
try { console.log("isUserInAnyPool        :", await game.isUserInAnyPool(ADDR)); }
catch (e) { console.log("isUserInAnyPool        : n/a"); }

const iface = new ethers.Interface(contracts.game.abi);
const allLogs = await p.getLogs({ address: contracts.game.address, fromBlock: 0, toBlock: "latest" });
const touched = allLogs.filter(l => JSON.stringify(l.topics).toLowerCase().includes(L.slice(2)));

console.log("\nLogs contrat impliquant ce wallet :", touched.length);
const byEvent = {};
const details = [];
for (const log of touched) {
  let name = log.topics[0].slice(0, 10), args = null;
  try { const ev = iface.parseLog(log); name = ev.name; args = ev.args; } catch {}
  byEvent[name] = (byEvent[name] || 0) + 1;
  details.push({ name, block: log.blockNumber, tx: log.transactionHash, args });
}
console.log("Par type :", JSON.stringify(byEvent));

// Détail des derniers événements lisibles
console.log("\n--- 15 derniers événements ---");
for (const d of details.slice(-15)) {
  let extra = "";
  try {
    if (d.name === "SubmitPremoveCID") extra = "base_bet=" + ethers.formatEther(d.args[0]) + " ETH cid=" + String(d.args[1]).slice(0, 12) + "...";
    else if (/Payout|Withdraw|Claim/i.test(d.name)) extra = Object.values(d.args).map(a => typeof a === "bigint" ? ethers.formatEther(a) : String(a)).join(" | ");
    else if (d.args) extra = Object.values(d.args).map(a => typeof a === "bigint" ? a.toString() : String(a)).slice(0, 4).join(" | ");
  } catch {}
  console.log(`[${d.name}] block ${d.block} ${extra}`);
}