const { Web3 } = require('web3');
const web3 = new Web3();

const privateKey = '0x***REMOVED***';
const account = web3.eth.accounts.privateKeyToAccount(privateKey);

console.log('Address:', account.address);
