const hre = require('hardhat');
const ethers = hre.ethers;

async function main() {
  const [deployer] = await ethers.getSigners();
  console.log('Deployer (Hardhat #0):', deployer.address);

  // Read secrets
  const fs = require('fs');
  function readSecret(name) {
    try { return fs.readFileSync('/run/secrets/' + name, 'utf8').trim(); } catch (e) { return undefined; }
  }
  const gameWalletPk = readSecret('game_wallet_pk');
  const signerPk = readSecret('signer_wallet_pk');
  const payoutPk = readSecret('payout_operator_pk');

  // --- Deploy Battlepool ---
  const Battlepool = await ethers.getContractFactory('Battlepool');
  const battlepool = await Battlepool.deploy();
  await battlepool.waitForDeployment();
  const battlepoolAddr = await battlepool.getAddress();
  console.log('Battlepool deployed to:', battlepoolAddr);

  // --- Deploy SNTToken ---
  const SNTToken = await ethers.getContractFactory('SNTToken');
  const sntToken = await SNTToken.deploy();
  await sntToken.waitForDeployment();
  const sntTokenAddr = await sntToken.getAddress();
  console.log('SNTToken deployed to:', sntTokenAddr);

  // --- Deploy MarketplaceEscrow ---
  const MarketplaceEscrow = await ethers.getContractFactory('MarketplaceEscrow');
  const marketplace = await MarketplaceEscrow.deploy(sntTokenAddr, deployer.address);
  await marketplace.waitForDeployment();
  const marketplaceAddr = await marketplace.getAddress();
  console.log('MarketplaceEscrow deployed to:', marketplaceAddr);

  // --- Initialize Battlepool roles ---
  if (gameWalletPk) {
    const ownerWallet = new ethers.Wallet(gameWalletPk, ethers.provider);
    const contract = battlepool.connect(ownerWallet);
    const signerAddr = signerPk ? new ethers.Wallet(signerPk).address : ethers.ZeroAddress;
    const payoutAddr = payoutPk ? new ethers.Wallet(payoutPk).address : ethers.ZeroAddress;
    try {
      const tx = await contract.initializeRoles(signerAddr, payoutAddr);
      await tx.wait();
      console.log('Roles initialized: signer=' + signerAddr + ' payoutOperator=' + payoutAddr);
    } catch (e) {
      console.error('initializeRoles failed:', e.message);
    }

    // Set security coefficient to 1000
    try {
      const tx2 = await contract.setDefaultPoolMaxSize(2);
      await tx2.wait();
      console.log('Default pool max size set to 2');
    } catch (e) {
      console.error('setDefaultPoolMaxSize failed:', e.message);
    }
  }

  // --- Fund reserved wallets (#0 already has ETH, fund #1, #2, #99) ---
  // Hardhat #0 = deployer (10000 ETH)
  // #1, #2, #99 need to be funded from their PKs (they are default Hardhat accounts with 10000 ETH each)
  // Actually all Hardhat accounts 0-99 are pre-funded with 10000 ETH by default.
  // The reserved wallets (owner, signer, payout) need funding too.
  const walletsToFund = [];
  if (payoutPk) walletsToFund.push({ name: 'PAYOUT_OPERATOR', pk: payoutPk });
  if (signerPk) walletsToFund.push({ name: 'SIGNER', pk: signerPk });
  if (gameWalletPk) walletsToFund.push({ name: 'OWNER', pk: gameWalletPk });

  for (const { name, pk } of walletsToFund) {
    const w = new ethers.Wallet(pk, ethers.provider);
    const bal = await ethers.provider.getBalance(w.address);
    console.log(name + ' (' + w.address + ') balance: ' + ethers.formatEther(bal) + ' ETH');
    if (bal === 0n) {
      const tx = await deployer.sendTransaction({ to: w.address, value: ethers.parseEther('100') });
      await tx.wait();
      console.log('  -> Funded ' + name + ' with 100 ETH');
    }
  }

  // --- Write addresses to a temp file for later use ---
  const addresses = {
    battlepool: battlepoolAddr,
    sntToken: sntTokenAddr,
    marketplaceEscrow: marketplaceAddr,
  };
  fs.writeFileSync('/tmp/deployed_addresses.json', JSON.stringify(addresses, null, 2));
  console.log('\nAll contracts deployed. Addresses written to /tmp/deployed_addresses.json');
  console.log(JSON.stringify(addresses, null, 2));
}

main().catch(e => { console.error(e); process.exit(1); });