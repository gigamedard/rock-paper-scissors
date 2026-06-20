const { ethers } = require("ethers");
async function checkStatus() {
    const provider = new ethers.JsonRpcProvider("http://127.0.0.1:8545");
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const abi = ["function isUserInAnyPool(address) view returns (bool)"];
    const contract = new ethers.Contract(address, abi, provider);
    
    const userWallet = "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266";
    const inPool = await contract.isUserInAnyPool(userWallet);
    
    console.log(`isUserInAnyPool: ${inPool}`);
}
checkStatus().catch(console.error);
