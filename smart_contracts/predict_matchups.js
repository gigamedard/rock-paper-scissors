import { ethers } from "ethers";

const args = process.argv.slice(2);
const humanAddress = args[0] || "0x70997970C51812dc3A010C7d01b50e0d17dc79C8";

const bots = [];
for (let i = 0; i < 4; i++) {
    const w = ethers.HDNodeWallet.fromPhrase(
        "test test test test test test test test test test test junk",
        undefined,
        `m/44'/60'/0'/0/${i + 2}`
    );
    bots.push({ name: `Bot ${i + 1}`, address: w.address });
}

// Order of joining: Bot 1, Bot 2, Bot 3, Bot 4, Human
const usersInOrder = [
    ...bots.map(b => b.address),
    humanAddress
];

console.log("=== USERS IN POOL ===");
usersInOrder.forEach((addr, i) => {
    let name = addr === humanAddress ? "Toi (Joueur Humain)" : bots.find(b => b.address === addr).name;
    console.log(`${i+1}. ${name} -> ${addr}`);
});

let packed = "";
for (let addr of usersInOrder) {
    packed += addr.slice(2).toLowerCase(); // remove 0x
}
const saltHash = ethers.keccak256("0x" + packed); 
const saltStr = saltHash.slice(2); 
console.log(`\n[Pool Salt Généré]: ${saltStr}`);

const addressHashes = {};
for (let addr of usersInOrder) {
    const noPrefix = addr.slice(2);
    const combinedHex = noPrefix.toLowerCase() + saltStr;
    const hash = ethers.keccak256("0x" + combinedHex);
    addressHashes[addr] = hash;
}

const sortedAddresses = Object.keys(addressHashes).sort((a, b) => {
    return addressHashes[a].localeCompare(addressHashes[b]);
});

console.log("\n=== ⚔️ MATCHUPS PREDITS ⚔️ ===");
for (let i = 0; i < sortedAddresses.length; i += 2) {
    if (i + 1 < sortedAddresses.length) {
        let p1 = sortedAddresses[i];
        let p2 = sortedAddresses[i+1];
        let n1 = p1.toLowerCase() === humanAddress.toLowerCase() ? "Toi (Joueur Humain)" : bots.find(b => b.address.toLowerCase() === p1.toLowerCase()).name;
        let n2 = p2.toLowerCase() === humanAddress.toLowerCase() ? "Toi (Joueur Humain)" : bots.find(b => b.address.toLowerCase() === p2.toLowerCase()).name;
        console.log(`Fight: ${n1} vs ${n2}`);
    } else {
        let p = sortedAddresses[i];
        let n = p.toLowerCase() === humanAddress.toLowerCase() ? "Toi (Joueur Humain)" : bots.find(b => b.address.toLowerCase() === p.toLowerCase()).name;
        console.log(`⏳ En Attente (Impair) : ${n}`);
    }
}
