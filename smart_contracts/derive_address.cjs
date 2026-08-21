const { Web3 } = require('web3');
const web3 = new Web3();

// SECURITY: private key must be provided via env var, never hardcoded.
const privateKey = process.env.MAIN_WALLET_PK;
if (!privateKey) {
    console.error('❌ MAIN_WALLET_PK env var is required');
    process.exit(1);
}
const account = web3.eth.accounts.privateKeyToAccount(privateKey);

console.log('Address:', account.address);
