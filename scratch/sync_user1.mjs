import { ethers, Contract } from "ethers";
import { contracts } from "../smart_contracts/config.js";
import fs from "fs";

const ADDR = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
const AMOUNT = "10.01";
const p = new ethers.JsonRpcProvider("http://127.0.0.1:8546");
const game = new Contract(contracts.game.address, contracts.game.abi, p);

const opPk = fs.readFileSync(".secrets/payout_operator_pk", "utf8").trim();
const op = new ethers.Wallet(opPk.startsWith("0x") ? opPk : "0x" + opPk, p);
const nonceBefore = await game.nonces(ADDR);
console.log("Nonce contrat avant sync :", nonceBefore.toString());

const tx = await game.connect(op).updateUserBalance(ADDR, ethers.parseEther(AMOUNT));
await tx.wait();
console.log("✅ updateUserBalance(" + AMOUNT + ") tx:", tx.hash);
console.log("userBalances on-chain    :", ethers.formatEther(await game.userBalances(ADDR)), "ETH");
console.log("Nonce contrat après sync :", (await game.nonces(ADDR)).toString(), "(inchangé → signature toujours valide)");