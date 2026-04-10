import { JsonRpcProvider } from "ethers";
import fs from "fs";

async function main() {
    const provider = new JsonRpcProvider("http://127.0.0.1:8545");
    const address = "0x5FbDB2315678afecb367f032d93F642f64180aa3";
    const bytecode = await provider.getCode(address);
    console.log("Deployed Bytecode length:", bytecode.length);
    
    // Read artifact bytecode (for comparison, removing '0x' but keeping it for console)
    const artifactPath = "battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json";
    const artifact = JSON.parse(fs.readFileSync(artifactPath, "utf8"));
    const artifactBytecode = artifact.deployedBytecode;
    console.log("Artifact Bytecode length:", artifactBytecode.length);

    if (bytecode === artifactBytecode) {
        console.log("✅ Bytecode matches perfectly!");
    } else {
        console.log("❌ Bytecode MISMATCH!");
    }
}
main().catch(console.error);
