import fetch from "node-fetch";

async function main() {
    const res = await fetch("http://127.0.0.1:8001/api/artefacts");
    const json = await res.json();
    console.log("========================================");
    console.log("Artefacts Response:");
    console.log(JSON.stringify(json, null, 2));
    console.log("========================================");
}

main().catch(console.error);
