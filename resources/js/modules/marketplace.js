// resources/js/modules/marketplace.js
/**
 * Module Marketplace P2P (SNT <-> AVAX)
 * FIXES:
 *  - Clés JSON correctes depuis /api/artefacts : 'snt' et 'marketplace'
 *  - Flux en 2 étapes séparées : Approuver d'abord, puis Créer
 *  - Guard d'authentification wallet avant toute action
 *  - Remplacement de tous les alert() par addToFeed() + banners
 *  - Vérification allowance avant de re-demander une approbation inutile
 *  - Protection parseEther sur format invalide
 *  - Polling protégé contre les cumuls via setAppTimer
 */
import { getContract, getSigner, getProvider } from '../web3/web3-core.js';
import { secureFetch } from '../core/api.js';
import { addToFeed } from './game.js';
import { t } from './i18n.js';
import { setAppTimer } from '../core/timers.js';
import { parseEther, formatEther } from 'ethers';

const SNT_ABI = [
    "function approve(address spender, uint256 amount) returns (bool)",
    "function allowance(address owner, address spender) view returns (uint256)",
    "function transfer(address to, uint256 amount) returns (bool)"
];

const ESCROW_ABI = [
    "function createOffer(uint256 sntAmount, uint256 avaxAmount, uint256 durationHours) external",
    "function fulfillOffer(uint256 offerId) external payable",
    "function cancelOffer(uint256 offerId) external"
];

// FIX #1 : les vraies clés retournées par /get-game-config sont 'snt' et 'marketplace'
let CONTRACT_ADDRESSES = {
    sntToken: null,
    marketplace: null
};

// État interne : approbation déjà faite pour ce montant ?
let _approvalDone = false;

export async function initMarketplace() {
    console.log("[Marketplace] Initialisation du module Marketplace");

    await loadContractAddresses();
    _updateContractStatusBanner();
    await loadMarketplaceData();

    // FIX #2 : Bouton Approuver séparé
    const approveBtn = document.getElementById('marketplace-approve-btn');
    if (approveBtn) {
        approveBtn.onclick = handleApprove;
    }

    const createOfferBtn = document.getElementById('marketplace-create-btn');
    if (createOfferBtn) {
        createOfferBtn.onclick = handleCreateOffer;
        createOfferBtn.disabled = true; // Désactivé jusqu'à approbation
    }

    // Réinitialiser l'état d'approbation quand le montant SNT change
    const sntInput = document.getElementById('mp-snt-amount');
    if (sntInput) {
        sntInput.addEventListener('input', () => {
            _approvalDone = false;
            const createBtn = document.getElementById('marketplace-create-btn');
            if (createBtn) createBtn.disabled = true;
            const approveBtn = document.getElementById('marketplace-approve-btn');
            if (approveBtn) approveBtn.disabled = false;
        });
    }

    // Polling (protégé : setAppTimer remplace l'ancien timer)
    const timerId = setInterval(loadMarketplaceData, 30000);
    setAppTimer('marketplace-poll', timerId);

    // Gestion des onglets P2P / Cartes
    const tabP2p = document.getElementById('tab-p2p');
    const tabCards = document.getElementById('tab-cards');
    if (tabP2p && tabCards) {
        tabP2p.onclick = () => switchMarketplaceTab('p2p');
        tabCards.onclick = () => switchMarketplaceTab('cards');
    }

    if (!window.marketplaceListenersInitialized) {
        window.marketplaceListenersInitialized = true;
        window.addEventListener('i18n:changed', renderOffers);
        window.addEventListener('auth:success', () => loadMarketplaceData());
    }
}

// ─── TAB SWITCHING ────────────────────────────────────────────────────────────
function switchMarketplaceTab(tab) {
    const tabP2p = document.getElementById('tab-p2p');
    const tabCards = document.getElementById('tab-cards');
    const contentP2p = document.getElementById('mp-p2p-content');
    const contentCards = document.getElementById('mp-cards-content');

    if (tab === 'p2p') {
        tabP2p.classList.add('active');
        tabP2p.style.background = 'var(--primary)';
        tabP2p.style.border = 'none';
        tabCards.classList.remove('active');
        tabCards.style.background = 'rgba(255,255,255,0.1)';
        tabCards.style.border = '1px solid var(--primary)';
        
        contentP2p.style.display = 'block';
        contentCards.style.display = 'none';
    } else if (tab === 'cards') {
        tabCards.classList.add('active');
        tabCards.style.background = 'var(--primary)';
        tabCards.style.border = 'none';
        tabP2p.classList.remove('active');
        tabP2p.style.background = 'rgba(255,255,255,0.1)';
        tabP2p.style.border = '1px solid var(--primary)';
        
        contentP2p.style.display = 'none';
        contentCards.style.display = 'block';
        loadShopCards();
    }
}

// ─── SHOP CARDS LOGIC ─────────────────────────────────────────────────────────
async function loadShopCards() {
    try {
        const res = await secureFetch('/shop/cards');
        if (!res.ok) return;
        const cards = await res.json();
        renderShopCards(cards);
    } catch (e) {
        console.error('[Marketplace] Erreur chargement cartes:', e);
    }
}

function renderShopCards(cards) {
    const container = document.getElementById('marketplace-cards-list');
    if (!container) return;
    
    if (cards.length === 0) {
        container.innerHTML = '<p style="text-align:center; color: var(--text-dim); grid-column: 1 / -1;">Aucune carte disponible pour le moment.</p>';
        return;
    }

    container.innerHTML = '';
    cards.forEach(card => {
        let effectDisplay = '';
        if (card.effect_type === 'cooldown_reduction') effectDisplay = `⏳ Cooldown -${card.effect_value < 1 ? (card.effect_value * 100) + '%' : card.effect_value + ' mins'}`;
        else if (card.effect_type === 'ceiling_increase') effectDisplay = `🚀 Plafond +${card.effect_value}x`;
        else if (card.effect_type === 'base_bet_modifier') effectDisplay = `💰 Base Bet +${card.effect_value}`;
        else effectDisplay = `⚡ ${card.effect_type} (${card.effect_value})`;

        const cardEl = document.createElement('div');
        cardEl.className = 'holo-card';
        cardEl.innerHTML = `
            <div class="holo-card-content">
                <div class="holo-card-title">${card.name}</div>
                <div class="holo-card-effect">${effectDisplay}</div>
                <p style="font-size: 0.85rem; color: #ccc; margin-bottom: 1rem; min-height: 40px;">${card.description || 'Une carte mystérieuse offrant des avantages uniques.'}</p>
                <div style="font-size: 0.8rem; color: #aaa; margin-bottom: 0.5rem;">Durée: ${card.duration_value} ${card.duration_type === 'sessions' ? 'Sessions' : 'Heures'}</div>
                <div class="holo-card-price">${card.price} SNT</div>
                <button class="btn-buy-card" onclick="window.marketplaceBuyCard(${card.id}, ${card.price})">Acheter</button>
            </div>
        `;
        container.appendChild(cardEl);
    });
}

window.marketplaceBuyCard = async function(cardId, cardPrice) {
    if (!window.userState || !window.userState.walletAddress) {
        _showMpNotification('⚠️ Connectez votre portefeuille pour acheter.', 'error');
        return;
    }
    
    // Définir l'adresse de réception (Owner du projet)
    const OWNER_ADDRESS = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
    
    try {
        if (!CONTRACT_ADDRESSES.sntToken) {
            throw new Error("Adresse du token SNT non chargée.");
        }

        addToFeed('⏳ Validation de la transaction Web3 (transfert SNT)...', 'var(--primary)');
        _showMpNotification('Veuillez signer la transaction dans votre portefeuille...', 'info');

        const sntContract = await getContract(CONTRACT_ADDRESSES.sntToken, SNT_ABI);
        
        // Convertir le prix en Wei (18 décimales standard)
        const amountInWei = parseEther(cardPrice.toString());
        
        // 1. Transaction On-Chain
        const tx = await sntContract.transfer(OWNER_ADDRESS, amountInWei);
        addToFeed(`⏳ Envoi de ${cardPrice} SNT en cours... (${tx.hash.substring(0,10)}...)`, 'var(--primary)');
        
        // Attendre la confirmation
        await tx.wait();
        addToFeed('✅ SNT transférés avec succès !', 'var(--success)');
        
        // 2. Notification au Backend
        addToFeed('⏳ Attribution de la carte...', 'var(--primary)');
        const res = await secureFetch('/shop/buy', {
            method: 'POST',
            body: JSON.stringify({ 
                card_id: cardId,
                tx_hash: tx.hash
            })
        });
        
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Erreur inconnue');
        
        _showMpNotification('🎉 Carte achetée avec succès !', 'success');
        addToFeed('✅ Carte ajoutée à votre inventaire.', 'var(--success)');
        
        // Mettre à jour l'affichage de la balance si nécessaire
        // (La DApp met déjà à jour la balance via les events ou un fetch séparé)
    } catch (e) {
        console.error('[Marketplace] Erreur achat carte:', e);
        _showMpNotification('❌ Achat échoué : ' + e.message, 'error');
    }
};

// FIX #1 : lire les bonnes clés 'snt' et 'marketplace' du JSON
async function loadContractAddresses() {
    try {
        const res = await fetch('/api/artefacts');
        if (res.ok) {
            const data = await res.json();
            // Le Node.js peut retourner une string ("0x...") ou un objet ({address: "0x...", abi: [...]})
            const sntData = data.snt || data.snt_address || data.token_address;
            const mpData  = data.marketplace || data.marketplace_address;

            CONTRACT_ADDRESSES.sntToken    = typeof sntData === 'object' ? sntData?.address : (sntData || null);
            CONTRACT_ADDRESSES.marketplace = typeof mpData === 'object'  ? mpData?.address  : (mpData || null);
            console.log('[Marketplace] Adresses chargées:', CONTRACT_ADDRESSES);
        } else {
            console.warn('[Marketplace] /api/artefacts a retourné', res.status);
        }
    } catch (e) {
        console.warn('[Marketplace] Impossible de charger les adresses de contrats:', e);
    }
}

// FIX #3 : Bannière d'état visible dans l'UI (plus de crash silencieux)
function _updateContractStatusBanner() {
    const banner = document.getElementById('mp-contract-error');
    const approveBtn = document.getElementById('marketplace-approve-btn');
    const createBtn  = document.getElementById('marketplace-create-btn');

    if (!CONTRACT_ADDRESSES.sntToken || !CONTRACT_ADDRESSES.marketplace) {
        if (banner) banner.style.display = 'block';
        if (approveBtn) approveBtn.disabled = true;
        if (createBtn)  createBtn.disabled  = true;
    } else {
        if (banner) banner.style.display = 'none';
        if (approveBtn) approveBtn.disabled = false;
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
        const elSnt   = document.getElementById('mp-stat-snt-volume');
        const elAvax  = document.getElementById('mp-stat-avax-volume');
        if (elTotal) elTotal.textContent = stats.total_trades || 0;
        if (elSnt)   elSnt.textContent   = parseFloat(stats.total_snt_volume  || 0).toFixed(2) + ' SNT';
        if (elAvax)  elAvax.textContent  = parseFloat(stats.total_avax_volume || 0).toFixed(4) + ' AVAX';
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

// FIX #3 : Guard wallet
function _assertWalletConnected() {
    const token = localStorage.getItem('auth_token') || localStorage.getItem('token');
    if (!token && !window.userState?.id) {
        _showMpNotification('⚠️ Connectez votre wallet avant de créer une offre.', 'error');
        return false;
    }
    return true;
}

// FIX #4 : Utilitaire de notification (remplace alert)
function _showMpNotification(message, type = 'info') {
    const banner = document.getElementById('mp-notification');
    if (banner) {
        banner.textContent = message;
        banner.className = `mp-notification mp-notification-${type}`;
        banner.style.display = 'block';
        setTimeout(() => { banner.style.display = 'none'; }, 6000);
    }
    // Aussi dans le feed global
    const color = type === 'error' ? 'var(--accent)' : type === 'success' ? 'var(--success)' : 'var(--primary)';
    addToFeed(message, color);
}

// FIX #5 : Protection parseEther (format invalide)
function _safeParseEther(value) {
    try {
        // Normaliser : remplacer virgule par point, supprimer espaces
        const normalized = String(value).replace(',', '.').trim();
        return parseEther(normalized);
    } catch (e) {
        throw new Error(`Montant invalide : "${value}". Utilisez un point comme séparateur décimal.`);
    }
}

// ─── ÉTAPE 1 : APPROBATION ERC20 ──────────────────────────────────────────────
async function handleApprove() {
    if (!_assertWalletConnected()) return;

    const sntAmount   = document.getElementById('mp-snt-amount')?.value;
    if (!sntAmount || parseFloat(sntAmount) <= 0) {
        _showMpNotification('Veuillez renseigner un montant SNT valide avant d\'approuver.', 'error');
        return;
    }

    if (!CONTRACT_ADDRESSES.sntToken || !CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification('⚠️ Service blockchain indisponible. Impossible d\'approuver.', 'error');
        return;
    }

    const btn = document.getElementById('marketplace-approve-btn');
    const createBtn = document.getElementById('marketplace-create-btn');
    btn.disabled = true;
    btn.textContent = '⏳ Approbation en cours...';

    try {
        const sntAmountWei = _safeParseEther(sntAmount);
        const sntContract  = await getContract(CONTRACT_ADDRESSES.sntToken, SNT_ABI);

        // FIX #6 : Vérifier l'allowance existante avant de re-approuver
        const signer    = await getSigner();
        const signerAddr = await signer.getAddress();
        const allowance  = await sntContract.allowance(signerAddr, CONTRACT_ADDRESSES.marketplace);

        if (allowance >= sntAmountWei) {
            _showMpNotification('✅ Approbation déjà suffisante. Vous pouvez créer l\'offre.', 'success');
            _approvalDone = true;
            createBtn.disabled = false;
            btn.textContent = '✅ Approuvé';
            return;
        }

        btn.textContent = '⏳ Confirmation dans MetaMask...';
        const approveTx = await sntContract.approve(CONTRACT_ADDRESSES.marketplace, sntAmountWei);
        await approveTx.wait();

        _approvalDone = true;
        createBtn.disabled = false;
        btn.textContent = '✅ Approuvé';
        _showMpNotification('✅ Approbation réussie ! Vous pouvez maintenant créer votre offre.', 'success');

    } catch (e) {
        console.error('[Marketplace] Erreur approbation:', e);
        _showMpNotification('❌ Approbation échouée : ' + (e.reason || e.message), 'error');
        btn.disabled = false;
        btn.textContent = '1. Approuver les SNT';
    }
}

// ─── ÉTAPE 2 : CRÉATION DE L'OFFRE ────────────────────────────────────────────
async function handleCreateOffer() {
    if (!_assertWalletConnected()) return;
    if (!_approvalDone) {
        _showMpNotification('⚠️ Veuillez d\'abord approuver vos SNT (Étape 1).', 'error');
        return;
    }

    const sntAmount     = document.getElementById('mp-snt-amount')?.value;
    const avaxAmount    = document.getElementById('mp-avax-amount')?.value;
    const durationHours = document.getElementById('mp-duration')?.value || 24;

    if (!sntAmount || !avaxAmount || parseFloat(sntAmount) <= 0 || parseFloat(avaxAmount) <= 0) {
        _showMpNotification('Veuillez renseigner des montants valides.', 'error');
        return;
    }

    const btn = document.getElementById('marketplace-create-btn');
    btn.disabled  = true;
    btn.textContent = '⏳ Création de l\'offre...';

    try {
        const sntAmountWei  = _safeParseEther(sntAmount);
        const avaxAmountWei = _safeParseEther(avaxAmount);
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);

        const createTx = await escrowContract.createOffer(sntAmountWei, avaxAmountWei, parseInt(durationHours));
        btn.textContent = '⏳ Transaction en cours...';
        await createTx.wait();

        _approvalDone = false;
        _showMpNotification(`✅ Offre créée : ${sntAmount} SNT → ${avaxAmount} AVAX`, 'success');
        addToFeed(`✅ Offre Marketplace créée : ${sntAmount} SNT → ${avaxAmount} AVAX`, 'var(--success)');

        // Reset formulaire
        document.getElementById('mp-snt-amount').value  = '';
        document.getElementById('mp-avax-amount').value = '';
        const approveBtn = document.getElementById('marketplace-approve-btn');
        if (approveBtn) { approveBtn.textContent = '1. Approuver les SNT'; approveBtn.disabled = false; }

        // Attendre 4s avant refresh (temps que le listener Node.js sync la DB)
        setTimeout(() => loadMarketplaceData(), 4000);

    } catch (e) {
        console.error('[Marketplace] Erreur création offre:', e);
        _showMpNotification('❌ Création échouée : ' + (e.reason || e.message), 'error');
    } finally {
        btn.disabled  = false;
        btn.textContent = t('marketplace.create_offer') || 'Créer l\'Offre';
    }
}

// ─── ACHAT D'UNE OFFRE ────────────────────────────────────────────────────────
window.marketplaceBuyOffer = async function(offerId, avaxAmount) {
    if (!_assertWalletConnected()) return;
    if (!CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification('⚠️ Service blockchain indisponible.', 'error');
        return;
    }
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        const avaxWei = _safeParseEther(avaxAmount.toString());
        addToFeed(`⏳ Achat en cours (offre #${offerId})...`, 'var(--primary)');
        const tx = await escrowContract.fulfillOffer(offerId, { value: avaxWei });
        await tx.wait();
        _showMpNotification(`✅ Achat réussi ! Vous avez reçu des SNT.`, 'success');
        setTimeout(() => loadMarketplaceData(), 4000);
    } catch (e) {
        console.error('[Marketplace] Erreur achat:', e);
        _showMpNotification('❌ Achat échoué : ' + (e.reason || e.message), 'error');
    }
};

// ─── ANNULATION D'UNE OFFRE ───────────────────────────────────────────────────
window.marketplaceCancelOffer = async function(offerId) {
    if (!_assertWalletConnected()) return;
    if (!CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification('⚠️ Service blockchain indisponible.', 'error');
        return;
    }
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        addToFeed(`⏳ Annulation de l'offre #${offerId}...`, 'var(--text-dim)');
        const tx = await escrowContract.cancelOffer(offerId);
        await tx.wait();
        _showMpNotification(`✅ Offre #${offerId} annulée. SNT restitués.`, 'success');
        setTimeout(() => loadMarketplaceData(), 4000);
    } catch (e) {
        console.error('[Marketplace] Erreur annulation:', e);
        _showMpNotification('❌ Annulation échouée : ' + (e.reason || e.message), 'error');
    }
};
