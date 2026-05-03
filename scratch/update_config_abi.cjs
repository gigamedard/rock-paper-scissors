const fs = require('fs');
const path = require('path');

const artifactPath = 'd:\\dev\\PHP\\rock-paper-scissors\\battlepool\\artifacts\\contracts\\Battlepool.sol\\Battlepool.json';
const deploymentPath = 'd:\\dev\\PHP\\rock-paper-scissors\\battlepool\\ignition\\deployments\\chain-1337\\deployed_addresses.json';
const configPath = 'd:\\dev\\PHP\\rock-paper-scissors\\smart_contracts\\config.js';

const artifact = JSON.parse(fs.readFileSync(artifactPath, 'utf8'));
const deployments = JSON.parse(fs.readFileSync(deploymentPath, 'utf8'));
const newAddress = deployments["BattlepoolModule#Battlepool"];
const newAbi = JSON.stringify(artifact.abi, null, 2);

let configContent = fs.readFileSync(configPath, 'utf8');

// Use regex to replace the game address and ABI.
const regex = /(game:\s*{\s*address:\s*")([^"]*)(" ,?\s*abi:\s*)\[[\s\S]*?\](\s*},)/;
const regexSimple = /(game:\s*{\s*address:\s*")([^"]*)(" ,?\s*abi:\s*)\[[\s\S]*?\](\s*},)/;

// Let's use a more flexible regex
const flexibleRegex = /game:\s*{\s*address:\s*"[^"]*",\s*abi:\s*\[[\s\S]*?\]\s*},/;

if (flexibleRegex.test(configContent)) {
    const replacement = `game: {
    address: "${newAddress}",
    abi: ${newAbi}
  },`;
    configContent = configContent.replace(flexibleRegex, replacement);
    fs.writeFileSync(configPath, configContent);
    console.log(`✅ config.js updated with Address: ${newAddress} and new ABI.`);
} else {
    console.error('❌ Could not find game object in config.js');
    process.exit(1);
}

