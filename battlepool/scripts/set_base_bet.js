import { ethers } from "hardhat";

async function main() {
    const battlepoolAddress = "0xe7f1725E7734CE288F8367e1Bb143E90bb3F0512"; // Adjust if different

    const Battlepool = await ethers.getContractFactory("Battlepool");
    const battlepool = Battlepool.attach(battlepoolAddress);

    console.log("Setting default max base bet to 0.05 ether...");
    const tx = await battlepool.setDefaultMaxBaseBet(ethers.parseEther("0.05"));
    await tx.wait();
    console.log("Tx hash:", tx.hash);

    const newMax = await battlepool.defaultMaxBaseBet();
    console.log("New default max base bet:", ethers.formatEther(newMax));
}

main().catch(console.error);
