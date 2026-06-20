import { ethers } from "ethers";

const rawTx = "0x02f9011a82053980843b9aca00844c9fda788307a120945fbdb2315678afecb367f032d93f642f64180aa3880e398811bec68000b8a4fc231918000000000000000000000000000000000000000000000000002386f26fc100000000000000000000000000000000000000000000000000000000000000000040000000000000000000000000000000000000000000000000000000000000003d626167616169657261336b33686d7064647a6376696e6333746d6261663432716b65777a7035733764376c6f737a697a66727a716a7534723468673671000000c001a0b14a0362a94fce0d89fbe71df9e56df26f5d2e881f76e7573f470faf622e2c24a0685a9a2cb6f9616c464543d7a890cd3888e2bfd374e0cc9b61e0d6055dfcc677";

function main() {
    const tx = ethers.Transaction.from(rawTx);
    console.log("========================================");
    console.log("Decoded Transaction:");
    console.log("To:      ", tx.to);
    console.log("Value:   ", ethers.formatEther(tx.value), "ETH");
    console.log("GasLimit:", tx.gasLimit.toString());
    console.log("Data:    ", tx.data);
    console.log("========================================");
}

main();
