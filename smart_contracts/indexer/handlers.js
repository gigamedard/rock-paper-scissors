// ============================================================
// Handlers métier APP1 : mapping événement on-chain → webhook Laravel.
// Reproduit FIDÈLEMENT la logique de l'ancien startBlockchainListeners()
// (app.js), mais pilotée par l'indexeur robuste (idempotent).
// ============================================================
import { formatEther } from 'ethers';

/**
 * Construit les "watchers" (un par contrat) avec leurs handlers.
 *
 * @param {object} ctx
 * @param {import('ethers').Contract} ctx.gameContract
 * @param {import('ethers').Contract} ctx.marketplaceContract
 * @param {import('ethers').Contract} ctx.sntContract
 * @param {string} ctx.marketplaceAddress - adresse du marketplace (pour dédup SNT)
 * @param {(endpoint: string, body: object) => Promise<void>} ctx.postToLaravel
 * @returns {Array<{key: string, contractAddress: string, contract: object, handlers: object}>}
 */
export function createWatchers({ gameContract, marketplaceContract, sntContract, marketplaceAddress, postToLaravel }) {
  const marketplaceLower = marketplaceAddress.toLowerCase();

  return [
    {
      key: 'game',
      contractAddress: gameContract.target,
      contract: gameContract,
      handlers: {
        async PoolEmitted(args) {
          const [poolId, baseBet, users, premoveCIDs, poolSalt, balances] = args;
          await postToLaravel('/internal/handle-pool-emited', {
            pool_id: poolId.toString(),
            base_bet: baseBet.toString(),
            users,
            premove_cids: premoveCIDs,
            pool_salt: poolSalt,
            balances: balances.map((b) => b.toString()),
          });
        },
        async DepositReceived(args) {
          const [user, amount] = args;
          await postToLaravel('/internal/update-balance', {
            wallet_address: user,
            balance: amount.toString(),
          });
        },
        async SecurityCoefficientUpdated(args) {
          const [newCoefficient] = args;
          await postToLaravel('/internal/update-setting', {
            key: 'security_coefficient',
            value: newCoefficient.toString(),
            type: 'integer',
          });
        },
        async FeeBasisPointsUpdated(args) {
          const [newFee] = args;
          const percentage = parseFloat(newFee) / 100; // 250 -> 2.5
          await postToLaravel('/internal/update-setting', {
            key: 'smart_contract_fee_percentage',
            value: percentage.toString(),
            type: 'float',
          });
        },
        async PayoutProcessed(args) {
          const [wallet] = args;
          await postToLaravel('/internal/handle-claim', { wallet_address: wallet });
        },
        async PlayerClaimed(args) {
          const [wallet] = args;
          await postToLaravel('/internal/handle-claim', { wallet_address: wallet });
        },
      },
    },
    {
      key: 'marketplace',
      contractAddress: marketplaceContract.target,
      contract: marketplaceContract,
      handlers: {
        async OfferCreated(args) {
          const [offerId, seller, sntAmount, avaxAmount] = args;
          try {
            const offerDetails = await marketplaceContract.offers(offerId);
            const expiresAt = offerDetails.expiresAt;
            await postToLaravel('/internal/trades/create', {
              offerId: offerId.toString(),
              seller,
              sntAmount: formatEther(sntAmount),
              avaxAmount: formatEther(avaxAmount),
              expiresAt: expiresAt.toString(),
            });
          } catch (err) {
            console.error(`❌ [Indexer] Failed to fetch offer details for ${offerId}:`, err.message);
          }
        },
        async OfferFulfilled(args) {
          const [offerId, buyer] = args;
          await postToLaravel('/internal/trades/update-status', {
            offerId: offerId.toString(),
            newStatus: 'fulfilled',
            buyerAddress: buyer,
          });
        },
        async OfferCancelled(args) {
          const [offerId] = args;
          await postToLaravel('/internal/trades/update-status', {
            offerId: offerId.toString(),
            newStatus: 'cancelled',
          });
        },
      },
    },
    {
      key: 'snt',
      contractAddress: sntContract.target,
      contract: sntContract,
      handlers: {
        async Transfer(args) {
          const [from, to, value] = args;
          // DEDUPLICATION : ignorer les transferts impliquant le marketplace
          // (déjà gérés par OfferFulfilled/OfferCreated).
          if (from.toLowerCase() === marketplaceLower || to.toLowerCase() === marketplaceLower) {
            return;
          }
          await postToLaravel('/internal/trades/sync-transfer', {
            from,
            to,
            amount: formatEther(value),
          });
        },
      },
    },
  ];
}
