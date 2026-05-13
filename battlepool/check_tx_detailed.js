const { ethers } = require("hardhat");
async function main() {
    const txHash = "0xdb4fff59db1df0bc46be927593583a06468718661c726f2761f7f26c09804819";
    const receipt = await ethers.provider.getTransactionReceipt(txHash);
    if (!receipt) {
        console.log("Transaction not found or not mined yet.");
        return;
    }
    console.log("Status:", receipt.status);
    console.log("Block Number:", receipt.blockNumber);
    console.log("Number of logs:", receipt.logs.length);
    receipt.logs.forEach((log, index) => {
        console.log(`Log ${index}: address=${log.address} topics=${JSON.stringify(log.topics)}`);
    });
}
main().catch(console.error);
