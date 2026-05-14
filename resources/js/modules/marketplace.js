// resources/js/modules/marketplace.js
/**
 * Module Marketplace P2P (SNT <-> AVAX)
 * - Lit les offres via l'API Laravel (MarketplaceController)
 * - Crée / Achète / Annule des offres via le Smart Contract MarketplaceEscrow
 */
import { getContract, getSigner } from '../web3/web3-core.js';
import { secureFetch } from '../core/api.js';
import { addToFeed } from './game.js';
import { t } from './i18n.js';
import { setAppTimer } from '../core/timers.js';
import { parseEther, formatEther } from 'ethers';

// ABIs minimaux (on n'importe pas tout le fichier ABI complet pour rester léger)
const SNT_ABI = [
    "function approve(address spender, uint256 amount) returns (bool)",
    "function allowance(address owner, address spender) view returns (uint256)"
];

const ESCROW_ABI = [
    "function createOffer(uint256 sntAmount, uint256 avaxAmount, uint256 durationHours) external",
    "function fulfillOffer(uint256 offerId) external payable",
    "function cancelOffer(uint256 offerId) external"
];

// Ces adresses seront chargées depuis la config ou l'API en production
let CONTRACT_ADDRESSES = {
    sntToken: null,
    marketplace: null
};

export async function initMarketplace() {
    console.log("[Marketplace] Initialisation du module Marketplace");

    // Charger les adresses de contrats depuis l'API
    await loadContractAddresses();

    // Charger les offres et stats
    await loadMarketplaceData();

    // Bind les boutons du formulaire de création d'offre
    const createOfferBtn = document.getElementById('marketplace-create-btn');
    if (createOfferBtn) {
        createOfferBtn.addEventListener('click', handleCreateOffer);
    }

    // Polling toutes les 30 secondes pour actualiser les offres
    const timerId = setInterval(loadMarketplaceData, 30000);
    setAppTimer('marketplace-poll', timerId);

    // Écouter les changements de langue
    window.addEventListener('i18n:changed', renderOffers);
}

async function loadContractAddresses() {
    try {
        const res = await fetch('/api/artefacts');
        if (res.ok) {
            const data = await res.json();
            CONTRACT_ADDRESSES.sntToken = data.snt_address || data.token_address;
            CONTRACT_ADDRESSES.marketplace = data.marketplace_address;
        }
    } catch (e) {
        console.warn('[Marketplace] Impossible de charger les adresses de contrats:', e);
    }
}

async function loadMarketplaceData() {
    await Promise.all([loadStats(), loadOffers()]);
}

async function loadStats() {
    try {
        const res = await secureFetch('/marketplace/stats');
        if (!res.ok) return;
        const stats = await res.json();

        const elTotal = document.getElementById('mp-stat-total-trades');
        const elSnt = document.getElementById('mp-stat-snt-volume');
        const elAvax = document.getElementById('mp-stat-avax-volume');

        if (elTotal) elTotal.textContent = stats.total_trades || 0;
        if (elSnt) elSnt.textContent = parseFloat(stats.total_snt_volume || 0).toFixed(2) + ' SNT';
        if (elAvax) elAvax.textContent = parseFloat(stats.total_avax_volume || 0).toFixed(4) + ' AVAX';
    } catch (e) {
        console.error('[Marketplace] Erreur chargement stats:', e);
    }
}

let currentOffers = [];

async function loadOffers() {
    try {
        const res = await secureFetch('/marketplace/trades');
        if (!res.ok) return;
        const data = await res.json();
        currentOffers = data.trades || [];
        renderOffers();
    } catch (e) {
        console.error('[Marketplace] Erreur chargement offres:', e);
    }
}

function renderOffers() {
    const container = document.getElementById('marketplace-offers-list');
    if (!container) return;

    if (currentOffers.length === 0) {
        container.innerHTML = `<p class="mp-empty-state">${t('marketplace.no_offers')}</p>`;
        return;
    }

    container.innerHTML = currentOffers.map(offer => `
        <div class="mp-offer-card ${offer.is_own_trade ? 'own-trade' : ''}" data-offer-id="${offer.blockchain_id}">
            <div class="mp-offer-amounts">
                <span class="mp-snt">${parseFloat(offer.snt_amount).toFixed(2)} <em>SNT</em></span>
                <span class="mp-arrow">→</span>
                <span class="mp-avax">${parseFloat(offer.avax_amount).toFixed(4)} <em>AVAX</em></span>
            </div>
            <div class="mp-offer-meta">
                <span class="mp-seller">👤 ${offer.seller_address}</span>
                <span class="mp-price">Prix: ${(offer.price_per_snt || 0).toFixed(6)} AVAX/SNT</span>
            </div>
            <div class="mp-offer-actions">
                ${offer.is_own_trade
                    ? `<button class="btn-mp-cancel" onclick="window.marketplaceCancelOffer(${offer.blockchain_id})">${t('marketplace.cancel')}</button>`
                    : `<button class="btn-mp-buy" onclick="window.marketplaceBuyOffer(${offer.blockchain_id}, '${offer.avax_amount}')">${t('marketplace.buy')}</button>`
                }
            </div>
        </div>
    `).join('');
}

async function handleCreateOffer() {
    const sntAmount = document.getElementById('mp-snt-amount')?.value;
    const avaxAmount = document.getElementById('mp-avax-amount')?.value;
    const durationHours = document.getElementById('mp-duration')?.value || 24;

    if (!sntAmount || !avaxAmount || parseFloat(sntAmount) <= 0 || parseFloat(avaxAmount) <= 0) {
        alert("Veuillez renseigner des montants valides.");
        return;
    }

    const btn = document.getElementById('marketplace-create-btn');
    btn.disabled = true;
    btn.textContent = "⏳ Approbation en cours...";

    try {
        // Étape 1 : Approbation ERC20 (SNT → Escrow)
        if (!CONTRACT_ADDRESSES.sntToken || !CONTRACT_ADDRESSES.marketplace) {
            throw new Error("Adresses de contrats non chargées. Assurez-vous que le backend est démarré.");
        }

        const sntContract = await getContract(CONTRACT_ADDRESSES.sntToken, SNT_ABI);
        const sntAmountWei = parseEther(sntAmount.toString());

        const approveTx = await sntContract.approve(CONTRACT_ADDRESSES.marketplace, sntAmountWei);
        btn.textContent = "⏳ Confirmation approbation...";
        await approveTx.wait();

        // Étape 2 : Création de l'offre on-chain
        btn.textContent = "⏳ Création de l'offre...";
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        const avaxAmountWei = parseEther(avaxAmount.toString());

        const createTx = await escrowContract.createOffer(sntAmountWei, avaxAmountWei, parseInt(durationHours));
        await createTx.wait();

        addToFeed(`✅ Offre créée : ${sntAmount} SNT → ${avaxAmount} AVAX`, "var(--success)");
        await loadMarketplaceData(); // Actualiser
    } catch (e) {
        console.error('[Marketplace] Erreur création offre:', e);
        alert("Erreur: " + (e.reason || e.message));
    } finally {
        btn.disabled = false;
        btn.textContent = t('marketplace.create_offer');
    }
}

// Achat d'une offre
window.marketplaceBuyOffer = async function(offerId, avaxAmount) {
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        const avaxWei = parseEther(avaxAmount.toString());

        const tx = await escrowContract.fulfillOffer(offerId, { value: avaxWei });
        addToFeed(`⏳ Achat en cours (offre #${offerId})...`, "var(--primary)");
        await tx.wait();
        addToFeed(`✅ Achat réussi ! Vous avez reçu des SNT.`, "var(--success)");
        await loadMarketplaceData();
    } catch (e) {
        console.error('[Marketplace] Erreur achat:', e);
        alert("Erreur: " + (e.reason || e.message));
    }
};

// Annulation d'une offre
window.marketplaceCancelOffer = async function(offerId) {
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        const tx = await escrowContract.cancelOffer(offerId);
        addToFeed(`⏳ Annulation de l'offre #${offerId}...`, "var(--text-dim)");
        await tx.wait();
        addToFeed(`✅ Offre #${offerId} annulée. SNT restitués.`, "var(--success)");
        await loadMarketplaceData();
    } catch (e) {
        console.error('[Marketplace] Erreur annulation:', e);
        alert("Erreur: " + (e.reason || e.message));
    }
};
