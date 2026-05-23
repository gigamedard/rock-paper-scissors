import fs from 'fs';
import path from 'path';

const artifactPath = '../battlepool/artifacts/contracts/Battlepool.sol/Battlepool.json';
const configPath = 'config.js';

try {
    const artifact = JSON.parse(fs.readFileSync(artifactPath, 'utf8'));
    const newAbiStr = JSON.stringify(artifact.abi, null, 2);

    let configContent = fs.readFileSync(configPath, 'utf8');

    const regex = /(game:\s*\{\s*address:\s*"[^"]+",\s*abi:\s*)\[[^]*?\](\s*\},)/;

    if (regex.test(configContent)) {
        configContent = configContent.replace(regex, `$1${newAbiStr}$2`);
        fs.writeFileSync(configPath, configContent, 'utf8');
        console.log("✅ Successfully updated Battlepool ABI in config.js");
    } else {
        console.error("❌ Could not match the game.abi pattern in config.js");
    }
} catch (e) {
    console.error("❌ Error during ABI update:", e.message);
}
