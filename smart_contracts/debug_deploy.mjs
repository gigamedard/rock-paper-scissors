import hre from "hardhat";
console.log("HRE keys:", Object.keys(hre));
const { ethers } = hre;
console.log("Ethers in HRE:", !!ethers);

async function main() {
    if (!ethers) {
        throw new Error("Ethers is not loaded in HRE! Check your hardhat.config.js");
    }
    const [deployer] = await ethers.getSigners();
    console.log("Deployer:", deployer.address);
}
main().catch(console.error);
