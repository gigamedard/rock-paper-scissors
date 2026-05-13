import { JsonRpcProvider } from "ethers";
const provider = new JsonRpcProvider("http://127.0.0.1:8545");
provider.getBlockNumber().then(n => console.log("Current Block:", n)).catch(console.error);
