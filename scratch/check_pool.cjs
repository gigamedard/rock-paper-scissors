const { ethers } = require("ethers");
async function checkPools() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const abi = ["function isUserInPoolByBaseBet(uint256, address) view returns (bool)", "function getPoolQueueLength(uint256) view returns (uint256)"];
    const contract = new ethers.Contract(address, abi, provider);
    
    const userWallet = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const baseBet = ethers.parseEther("0.01");
    
    const inPool = await contract.isUserInPoolByBaseBet(baseBet, userWallet);
    const qLen = await contract.getPoolQueueLength(baseBet);
    
    console.log(`isUserInPoolByBaseBet (0.01): ${inPool}`);
    console.log(`getPoolQueueLength (0.01): ${qLen}`);
}
checkPools().catch(console.error);
