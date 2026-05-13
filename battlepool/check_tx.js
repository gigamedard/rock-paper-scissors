const { ethers } = require("hardhat");
async function main() {
    const txHash = "0xdb4fff59db1df0bc46be927593583a06468718661c726f2761f7f26c09804819";
    const receipt = await ethers.provider.getTransactionReceipt(txHash);
    console.log(JSON.stringify(receipt, null, 2));
}
main().catch(console.error);
