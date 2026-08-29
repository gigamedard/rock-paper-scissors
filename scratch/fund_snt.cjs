// fund_snt.cjs — §9bis TEST_RUNBOOK : distribuer les SNT aux comptes réservés
// Le SNTToken mint 1M SNT au compte #100 (INITIAL_OWNER_ADDRESS codé en dur), PAS au déployeur #0.
const { JsonRpcProvider, Contract, formatEther, parseEther, HDNodeWallet } = require('ethers');

async function main() {
  const provider = new JsonRpcProvider('http://localhost:8545');
  const SNT_ADDRESS = '0x0165878A594ca255338adfa4d48449f69242Eb8F'; // = contracts.snt.address dans config.js
  const abi = ['function balanceOf(address) view returns (uint256)', 'function transfer(address to, uint256 amount) returns (bool)'];

  // Owner du SNT = compte Hardhat #100 (INITIAL_OWNER_ADDRESS codé en dur dans SNTToken.sol)
  const hd = HDNodeWallet.fromPhrase(
    "test test test test test test test test test test test junk",
    undefined,
    "m/44'/60'/0'/0/100"
  );
  const ownerWallet = hd.connect(provider);
  const snt = new Contract(SNT_ADDRESS, abi, ownerWallet);

  const targets = {
    '#0': '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266',
    '#1': '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
    '#2': '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
    '#99': '0x98d08079928fccb30598c6c6382abfd7dbfaa1cd',
  };

  for (const [label, addr] of Object.entries(targets)) {
    const bal = await snt.balanceOf(addr);
    if (bal > 0n) { console.log(`${label}: déjà ${formatEther(bal)} SNT`); continue; }
    const tx = await snt.transfer(addr, parseEther('100'));
    await tx.wait();
    console.log(`${label}: ${formatEther(await snt.balanceOf(addr))} SNT`);
  }
}
main().catch(e => console.error('ERR:', e.message));