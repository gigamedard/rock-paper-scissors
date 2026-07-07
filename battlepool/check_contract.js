const { ethers } = require("hardhat");

async function main() {
    const B = await ethers.getContractFactory("Battlepool");
    const b = B.attach("0x0165878A594ca255338adfa4d48449f69242Eb8F");
    
    console.log("maxBaseBet:", ethers.formatEther(await b.defaultMaxBaseBet()), "ETH");
    console.log("feeBP:", (await b.feeBasisPoints()).toString());
    console.log("secCoeff:", (await b.securityCoefficient()).toString());
}

main().catch(console.error);
