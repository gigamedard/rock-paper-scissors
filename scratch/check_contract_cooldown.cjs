const { ethers } = require("ethers");
const fs = require('fs');

async function checkCooldown() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const appConfig = JSON.parse(fs.readFileSync('smart_contracts/config.json', 'utf8'));
    
    // ABI only needs the nextSessionAllowedTime function
    const abi = [
        "function nextSessionAllowedTime(address) view returns (uint256)"
    ];
    
    const contract = new ethers.Contract(appConfig.BATTLEPOOL_ADDRESS, abi, provider);
    const userWallet = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const time = await contract.nextSessionAllowedTime(userWallet);
    const timeNum = Number(time);
    
    console.log(`nextSessionAllowedTime: ${timeNum}`);
    console.log(`Current block timestamp: ${(await provider.getBlock('latest')).timestamp}`);
}

checkCooldown().catch(console.error);
