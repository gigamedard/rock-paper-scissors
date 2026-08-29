import { ethers, Contract } from "ethers";
import { contracts } from "../smart_contracts/config.js";
import fs from "fs";

const ADDR = "0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC";
const AMOUNT = "10.01";                                   // solde DB au moment de la signature
const DEADLINE = 1787909984n;
const SIGNATURE = "0x7bce2d98644142ff73c4ecafdcbfa877f583c537ea4b2534a51a790efacee50128ea41fb28b4964aca5116434575503f59e4ca6a0ac1574a8b497f4340bddd001b";

const p = new ethers.JsonRpcProvider("http://127.0.0.1:8546");
const game = new Contract(contracts.game.address, contracts.game.abi, p);

// Wallet du user (Hardhat #2)
const hd = ethers.HDNodeWallet.fromPhrase("test test test test test test test test test test test junk", undefined, "m/44'/60'/0'/0/2");
if (hd.address.toLowerCase() !== ADDR.toLowerCase()) throw new Error("Adresse dérivée " + hd.address + " != " + ADDR);
const user = new ethers.Wallet(hd.privateKey, p);
console.log("Claimant :", user.address);

// 1. Solde on-chain actuel
let ub;
try {
  ub = await game.userBalances(ADDR);
  console.log("userBalances on-chain :", ethers.formatEther(ub), "ETH");
} catch (e) {
  console.log("userBalances illisible via ABI (" + e.shortMessage + ") — raw eth_call…");
  const data = new ethers.Interface(["function userBalances(address) view returns (uint256)"]).encodeFunctionData("userBalances", [ADDR]);
  const res = await p.call({ to: contracts.game.address, data });
  ub = ethers.AbiCoder.defaultAbiCoder().decode(["uint256"], res)[0];
  console.log("userBalances (raw)    :", ethers.formatEther(ub), "ETH");
}

// 2. Sync updateUserBalance si désynchronisé (§16 — via PAYOUT_OPERATOR)
const target = ethers.parseEther(AMOUNT);
if (ub < target) {
  console.log("Sync on-chain < DB → updateUserBalance(" + AMOUNT + ") via payout operator…");
  const opPk = fs.readFileSync(".secrets/payout_operator_pk", "utf8").trim();
  const op = new ethers.Wallet(opPk.startsWith("0x") ? opPk : "0x" + opPk, p);
  const tx1 = await (new Contract(contracts.game.address, contracts.game.abi, op)).updateUserBalance(ADDR, target);
  await tx1.wait();
  console.log("✅ updateUserBalance tx:", tx1.hash);
} else {
  console.log("Sync on-chain OK (>= amount), pas de updateUserBalance nécessaire.");
}

// 3. claimAndExit par le user
const tx = await (game.connect(user)).claimAndExit(target, DEADLINE, SIGNATURE, { gasLimit: 500000 });
console.log("claimAndExit envoyé :", tx.hash);
const rc = await tx.wait();
console.log("✅ Claim confirmé, block", rc.blockNumber, "- status:", rc.status);
console.log("Solde wallet après claim:", ethers.formatEther(await p.getBalance(ADDR)), "ETH");