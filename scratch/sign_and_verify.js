import { ethers } from "ethers";

async function main() {
  const user = "0x70997970c51812dc3a010c7d01b50e0d17dc79c8";
  const amount = "10800000000000000000"; // 10.8 ETH in Wei
  const nonce = 0;
  const contractAddress = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
  const pk = "***REMOVED***";

  const wallet = new ethers.Wallet(pk);
  
  const messageHash = ethers.solidityPackedKeccak256(
    ["address", "uint256", "uint256", "address"],
    [user, amount, nonce, contractAddress]
  );
  
  console.log("Solidity messageHash:", messageHash);

  // Sign message
  const signature = await wallet.signMessage(ethers.getBytes(messageHash));
  console.log("Ethers.js Signature:", signature);

  const phpSignature = "0xfadb697e44d1b64b72c8e7df2c5c65531a3c7d3986aa075d7a2ae0e8694616ec110de78e9320275f313bbf0be0d052644c806dd3aa248939d8b46f7465480bc21b";
  console.log("PHP-generated Signature:", phpSignature);

  if (signature === phpSignature) {
    console.log("Match!");
  } else {
    console.log("No Match!");
  }
}

main().catch((error) => {
  console.error("Error:", error);
});
