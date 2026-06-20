const { ethers } = require("ethers");
async function checkPools() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const abi = ["function getPoolInfo(uint256) view returns (uint256 poolId, uint256 maxSize, uint256 userCount, bool isLocked)", "function getPoolUsers(uint256) view returns (address[])"];
    const contract = new ethers.Contract(address, abi, provider);
    
    const baseBet = ethers.parseEther("0.01");
    
    const info = await contract.getPoolInfo(baseBet);
    const users = await contract.getPoolUsers(baseBet);
    
    console.log(`Pool info: ID=${info.poolId}, maxSize=${info.maxSize}, userCount=${info.userCount}, isLocked=${info.isLocked}`);
    console.log(`Pool users: ${users.join(", ")}`);
}
checkPools().catch(console.error);
