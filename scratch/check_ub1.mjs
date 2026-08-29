import { ethers, Contract } from "ethers";
import { contracts } from "../smart_contracts/config.js";

const ADDR = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
const p = new ethers.JsonRpcProvider("http://127.0.0.1:8546");
const game = new Contract(contracts.game.address, contracts.game.abi, p);

try {
  const ub = await game.userBalances(ADDR);
  console.log("userBalances on-chain 0x7099 :", ethers.formatEther(ub), "ETH");
} catch (e) {
  const data = new ethers.Interface(["function userBalances(address) view returns (uint256)"]).encodeFunctionData("userBalances", [ADDR]);
  const res = await p.call({ to: contracts.game.address, data });
  const ub = ethers.AbiCoder.defaultAbiCoder().decode(["uint256"], res)[0];
  console.log("userBalances (raw)           :", ethers.formatEther(ub), "ETH");
}
console.log("Solde DB user 22             : 10.01 ETH");
console.log("Deadline signature           :", 1787912060, "=", new Date(1787912060 * 1000).toISOString());