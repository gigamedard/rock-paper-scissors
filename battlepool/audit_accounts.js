import pkg from 'hardhat';
const { ethers } = pkg;

async function main() {
    const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const Battlepool = await ethers.getContractAt("Battlepool", contractAddress);
    
    const signers = await ethers.getSigners();
    
    for (let i = 0; i < 10; i++) {
        const addr = signers[i].address;
        const inPool = await Battlepool.isUserInAnyPool(addr);
        const balance = await Battlepool.userBalances(addr);
        console.log(`Account #${i} (${addr}): inPool=${inPool}, Balance=${ethers.formatEther(balance)}`);
    }
}

main().catch(console.error);
