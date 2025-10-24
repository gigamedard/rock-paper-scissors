import { JsonRpcProvider, Wallet, Contract, formatEther, parseUnits } from "ethers";
import { marketplaceAddress, Avax_fujiRpcUrl, marketplaceAbi } from "./config.js";

const provider = new JsonRpcProvider(Avax_fujiRpcUrl);
const wallet = new Wallet(process.env.AVAX_WALLET_PRIVATE_KEY, provider);
const marketplaceContract = new Contract(marketplaceAddress, marketplaceAbi, wallet);

async function main() {
    console.log("🔊 Marketplace Listener: Listening for events...");

    marketplaceContract.on("OfferCreated", (offerId, seller, sntAmount, avaxAmount, event) => {
        console.log(`
🔔 OfferCreated Event Detected:
  Offer ID: ${offerId}
  Seller: ${seller}
  SNT Amount: ${formatEther(sntAmount)} SNT
  AVAX Amount: ${formatEther(avaxAmount)} AVAX
  Transaction Hash: ${event.log.transactionHash}
        `);
        // Ici, vous pouvez ajouter la logique pour enregistrer l'offre dans votre base de données backend
        // ou déclencher d'autres actions.
    });

    marketplaceContract.on("OfferFulfilled", (offerId, buyer, event) => {
        console.log(`
🔔 OfferFulfilled Event Detected:
  Offer ID: ${offerId}
  Buyer: ${buyer}
  Transaction Hash: ${event.log.transactionHash}
        `);
        // Logique pour marquer l'offre comme fulfill dans votre base de données backend
    });

    marketplaceContract.on("OfferCancelled", (offerId, event) => {
        console.log(`
🔔 OfferCancelled Event Detected:
  Offer ID: ${offerId}
  Transaction Hash: ${event.log.transactionHash}
        `);
        // Logique pour marquer l'offre comme annulée dans votre base de données backend
    });
}

main().catch((error) => {
    console.error("🚨 Unexpected Marketplace Listener script error:", error.message);
    process.exit(1);
});

