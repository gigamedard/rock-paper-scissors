const { ethers } = require("hardhat");
async function main() {
    const CONTRACT_ADDRESS = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const ABI = [
        "function getPoolInfo(uint256 baseBet) view returns (uint256 poolId, uint256 maxSize, uint256 userCount, bool isLocked)",
        "function getPoolUsers(uint256 baseBet) view returns (address[])"
    ];
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const contract = new ethers.Contract(CONTRACT_ADDRESS, ABI, provider);
    const baseBet = ethers.parseUnits("0.01", 18);
    const info = await contract.getPoolInfo(baseBet);
    console.log("Pool Info:", {
        poolId: info.poolId.toString(),
        maxSize: info.maxSize.toString(),
        userCount: info.userCount.toString(),
        isLocked: info.isLocked
    });
    const users = await contract.getPoolUsers(baseBet);
    console.log("Users:", users);
}
main().catch(console.error);
