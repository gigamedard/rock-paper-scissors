const { ethers } = require("ethers");
async function checkCooldown() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const abi = ["function nextSessionAllowedTime(address) view returns (uint256)"];
    const contract = new ethers.Contract(address, abi, provider);
    
    const userWallet = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const time = await contract.nextSessionAllowedTime(userWallet);
    const currentBlock = await provider.getBlock('latest');
    
    console.log(`nextSessionAllowedTime: ${time.toString()} (diff: ${time - BigInt(currentBlock.timestamp)}s)`);
    console.log(`Current block timestamp: ${currentBlock.timestamp}`);
}
checkCooldown().catch(console.error);
