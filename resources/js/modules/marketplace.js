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
import { parseRpcError } from '../core/auth.js';
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

// ─── RÉSILIENCE NONCE ─────────────────────────────────────────────────────────
// Le worker/batch process tourne en continu avec le compte GAME_WALLET (même
// compte que celui parfois utilisé pour tester) → le nonce nœud avance sans
// cesse alors que MetaMask garde son propre compteur local périmé.
// On lit donc le nonce frais ('pending') à chaque envoi et on retente en cas
// de collision (NONCE_EXPIRED), au lieu de laisser MetaMask décider seul.
const _MAX_NONCE_RETRIES = 4;

async function _getFreshNonce() {
    const provider = await getProvider();
    const signer   = await getSigner();
    const addr     = await signer.getAddress();
    // 'pending' = nonce courant du nœud (inclut les tx déjà soumises)
    return provider.getTransactionCount(addr, 'pending');
}

/**
 * Envoie une transaction en forçant un nonce frais (résilience NONCE_EXPIRED).
 * @param {(overrides: object) => Promise<any>} sendFn
 *   Fonction appelée avec l'overrides { nonce } (puis { nonce, value } si besoin).
 * @returns {Promise<any>} le receipt de transaction.
 */
async function _sendWithFreshNonce(sendFn) {
    let lastError;
    for (let attempt = 1; attempt <= _MAX_NONCE_RETRIES; attempt++) {
        const nonce = await _getFreshNonce();
        try {
            const tx = await sendFn({ nonce });
            return await tx.wait();
        } catch (e) {
            lastError = e;
            const msg = (e && (e.shortMessage || e.message || e.reason)) || '';
            const isNonceCollision =
                e?.code === 'NONCE_EXPIRED' ||
                e?.info?.error?.code === -32000 ||
                /nonce/i.test(msg);
            if (isNonceCollision && attempt < _MAX_NONCE_RETRIES) {
                console.warn(`[Marketplace] Collision de nonce (${msg}), nouvelle tentative ${attempt}/${_MAX_NONCE_RETRIES}...`);
                continue;
            }
            throw e;
        }
    }
    throw lastError;
}

export async function initMarketplace() {
    console.log("[Marketplace] Initialisation du module Marketplace");
    console.log("[Marketplace] DOM elements:", {
        approveBtn: !!document.getElementById('marketplace-approve-btn'),
        createBtn: !!document.getElementById('marketplace-create-btn'),
        sntInput: !!document.getElementById('mp-snt-amount'),
        avaxInput: !!document.getElementById('mp-avax-amount'),
        banner: !!document.getElementById('mp-contract-error'),
    });

    await loadContractAddresses();
    console.log("[Marketplace] Après loadContractAddresses:", CONTRACT_ADDRESSES);
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

    // Gestion des onglets P2P / Cartes / Inventaire
    const tabP2p = document.getElementById('tab-p2p');
    const tabCards = document.getElementById('tab-cards');
    const tabInventory = document.getElementById('tab-inventory');
    if (tabP2p && tabCards && tabInventory) {
        tabP2p.onclick = () => switchMarketplaceTab('p2p');
        tabCards.onclick = () => switchMarketplaceTab('cards');
        tabInventory.onclick = () => switchMarketplaceTab('inventory');
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
    const tabInventory = document.getElementById('tab-inventory');
    const contentP2p = document.getElementById('mp-p2p-content');
    const contentCards = document.getElementById('mp-cards-content');
    const contentInventory = document.getElementById('mp-inventory-content');

    // Réinitialiser les styles de tous les onglets
    [tabP2p, tabCards, tabInventory].forEach(el => {
        if (el) {
            el.classList.remove('active');
            el.style.background = 'rgba(255,255,255,0.1)';
            el.style.border = '1px solid var(--primary)';
        }
    });

    // Cacher tous les contenus
    if (contentP2p) contentP2p.style.display = 'none';
    if (contentCards) contentCards.style.display = 'none';
    if (contentInventory) contentInventory.style.display = 'none';

    // Activer l'onglet et le contenu sélectionné
    if (tab === 'p2p') {
        if (tabP2p) {
            tabP2p.classList.add('active');
            tabP2p.style.background = 'var(--primary)';
            tabP2p.style.border = 'none';
        }
        if (contentP2p) contentP2p.style.display = 'block';
    } else if (tab === 'cards') {
        if (tabCards) {
            tabCards.classList.add('active');
            tabCards.style.background = 'var(--primary)';
            tabCards.style.border = 'none';
        }
        if (contentCards) contentCards.style.display = 'block';
        loadShopCards();
    } else if (tab === 'inventory') {
        if (tabInventory) {
            tabInventory.classList.add('active');
            tabInventory.style.background = 'var(--primary)';
            tabInventory.style.border = 'none';
        }
        if (contentInventory) contentInventory.style.display = 'block';
        loadUserInventory();
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
        container.innerHTML = `<p style="text-align:center; color: var(--text-dim); grid-column: 1 / -1;">${t('marketplace.no_cards')}</p>`;
        return;
    }

    container.innerHTML = '';
    cards.forEach(card => {
        let effectDisplay = '';
        if (card.effect_type === 'cooldown_reduction') {
            const val = card.effect_value < 1 ? (card.effect_value * 100) + '%' : card.effect_value + ' mins';
            effectDisplay = t('marketplace.cooldown_effect', { val });
        } else if (card.effect_type === 'ceiling_increase') {
            effectDisplay = t('marketplace.ceiling_effect', { val: card.effect_value });
        } else if (card.effect_type === 'base_bet_modifier') {
            effectDisplay = t('marketplace.base_bet_effect', { val: card.effect_value });
        } else {
            effectDisplay = `⚡ ${card.effect_type} (${card.effect_value})`;
        }

        const cardEl = document.createElement('div');
        cardEl.className = 'holo-card';
        cardEl.innerHTML = `
            <div class="holo-card-content">
                <div class="holo-card-title">${card.name}</div>
                <div class="holo-card-effect">${effectDisplay}</div>
                <p style="font-size: 0.85rem; color: #ccc; margin-bottom: 1rem; min-height: 40px;">${card.description || ''}</p>
                <div style="font-size: 0.8rem; color: #aaa; margin-bottom: 0.5rem;">${t('marketplace.duration_label', { value: card.duration_value, type: t(card.duration_type === 'sessions' ? 'marketplace.sessions' : 'marketplace.hours') })}</div>
                <div class="holo-card-price">${card.price} SNT</div>
                <div style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.5rem;">
                    <input type="number" id="card-qty-${card.id}" min="1" value="1" style="width: 60px; padding: 0.3rem; background: rgba(0,0,0,0.5); color: #fff; border: 1px solid var(--primary); border-radius: 4px; text-align: center;">
                    <button class="btn-buy-card" style="flex: 1;" onclick="window.marketplaceBuyCard(${card.id}, ${card.price}, parseInt(document.getElementById('card-qty-${card.id}').value || 1))">${t('marketplace.buy_btn')}</button>
                </div>
            </div>
        `;
        container.appendChild(cardEl);
    });
}

window.marketplaceBuyCard = async function(cardId, cardPrice, quantity = 1) {
    if (isNaN(quantity) || quantity < 1) {
        quantity = 1;
    }

    if (!window.userState || !window.userState.walletAddress) {
        _showMpNotification(t('marketplace.sign_buy_wallet'), 'error');
        return;
    }
    
    const OWNER_ADDRESS = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
    const totalSntPrice = cardPrice * quantity;
    
    try {
        if (!CONTRACT_ADDRESSES.sntToken) {
            throw new Error("Adresse du token SNT non chargée.");
        }

        const sntContract = await getContract(CONTRACT_ADDRESSES.sntToken, [
            ...SNT_ABI,
            "function balanceOf(address owner) view returns (uint256)"
        ]);
        
        const signer = await getSigner();
        const signerAddr = await signer.getAddress();
        const userBalance = await sntContract.balanceOf(signerAddr);
        const amountInWei = parseEther(totalSntPrice.toString());

        if (userBalance < amountInWei) {
            _showMpNotification(t('marketplace.insufficient_snt', { req: totalSntPrice, bal: formatEther(userBalance) }), 'error');
            return;
        }

        addToFeed(t('feed.verify_snt'), 'var(--primary)');
        _showMpNotification(t('marketplace.sign_tx_info'), 'info');
        
        const tx = await sntContract.transfer(OWNER_ADDRESS, amountInWei);
        addToFeed(t('feed.sending_snt', { amount: totalSntPrice, hash: tx.hash.substring(0,10) }), 'var(--primary)');
        _showMpNotification(t('marketplace.tx_pending_info'), 'info');
        
        await tx.wait();
        addToFeed(t('feed.snt_transferred'), 'var(--success)');
        _showMpNotification(t('marketplace.tx_confirmed_info'), 'info');
        
        addToFeed(t('feed.allocating_card'), 'var(--primary)');
        const res = await secureFetch('/shop/buy', {
            method: 'POST',
            body: JSON.stringify({ 
                card_id: cardId,
                tx_hash: tx.hash,
                quantity: quantity
            })
        });
        
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Erreur inconnue');
        
        _showMpNotification(t('marketplace.buy_success'), 'success');
        addToFeed(t('feed.card_allocated'), 'var(--success)');
        
        switchMarketplaceTab('inventory');
    } catch (e) {
        console.error('[Marketplace] Erreur achat carte:', e);
        _showMpNotification(t('marketplace.buy_error', { error: parseRpcError(e) }), 'error');
    }
};

export async function loadUserInventory() {
    const container = document.getElementById('marketplace-inventory-list');
    if (!container) return;

    try {
        container.innerHTML = `<p style="text-align:center; color: var(--text-dim); grid-column: 1 / -1;">${t('marketplace.loading_inventory')}</p>`;
        const res = await secureFetch('/shop/inventory');
        if (!res.ok) throw new Error("Impossible de charger l'inventaire");
        const userCards = await res.json();

        if (userCards.length === 0) {
            container.innerHTML = `<p style="text-align:center; color: var(--text-dim); grid-column: 1 / -1;">${t('marketplace.no_inventory')}</p>`;
            return;
        }

        container.innerHTML = '';
        userCards.forEach(uc => {
            const card = uc.card;
            if (!card) return;

            let effectDisplay = '';
            if (card.effect_type === 'cooldown_reduction') {
                const val = card.effect_value < 1 ? (card.effect_value * 100) + '%' : card.effect_value + ' mins';
                effectDisplay = t('marketplace.cooldown_effect', { val });
            } else if (card.effect_type === 'ceiling_increase') {
                effectDisplay = t('marketplace.ceiling_effect', { val: card.effect_value });
            } else if (card.effect_type === 'base_bet_modifier') {
                effectDisplay = t('marketplace.base_bet_effect', { val: card.effect_value });
            } else {
                effectDisplay = `⚡ ${card.effect_type} (${card.effect_value})`;
            }

            let isExpired = false;
            if (uc.status === 'consumed' || uc.status === 'expired' || uc.status === 'failed') {
                isExpired = true;
            }
            if (card.duration_type === 'time' && uc.expires_at) {
                const expiresDate = new Date(uc.expires_at);
                if (expiresDate < new Date()) {
                    isExpired = true;
                }
            } else if (card.duration_type === 'sessions' && uc.remaining_sessions !== null && uc.remaining_sessions <= 0) {
                isExpired = true;
            }

            let statusDisplay = '';
            let statusColor = '';
            if (uc.status === 'pending') {
                statusDisplay = t('marketplace.pending_validation');
                statusColor = '#f59e0b';
            } else if (isExpired) {
                statusDisplay = t('marketplace.expired');
                statusColor = '#ef4444';
            } else if (uc.status === 'available' || uc.status === 'active') {
                statusDisplay = t('marketplace.active');
                statusColor = '#10b981';
            } else {
                statusDisplay = `⚡ ${uc.status}`;
                statusColor = '#aaa';
            }

            let validityDisplay = '';
            if (card.duration_type === 'sessions') {
                const count = uc.remaining_sessions !== null ? uc.remaining_sessions : card.duration_value;
                validityDisplay = t('marketplace.remaining_sessions', { count });
            } else {
                const expDate = uc.expires_at ? new Date(uc.expires_at).toLocaleString() : 'N/A';
                validityDisplay = t('marketplace.expires_at', { date: expDate });
            }

            const cardEl = document.createElement('div');
            cardEl.className = 'holo-card';
            cardEl.innerHTML = `
                <div class="holo-card-content">
                    <div class="holo-card-title">${card.name}</div>
                    <div class="holo-card-effect" style="margin-bottom: 0.5rem;">${effectDisplay}</div>
                    <p style="font-size: 0.85rem; color: #ccc; margin-bottom: 1rem; min-height: 40px;">${card.description || ''}</p>
                    <div style="font-size: 0.9rem; font-weight: bold; color: ${statusColor}; margin-bottom: 0.5rem;">${statusDisplay}</div>
                    <div style="font-size: 0.8rem; color: #aaa; margin-bottom: 0.5rem;">${validityDisplay}</div>
                    <div style="font-size: 0.7rem; color: var(--text-dim); word-break: break-all;">Tx: <a href="#" onclick="event.preventDefault(); window.open('https://subnets-test.avax.network/fuji-wagmi/tx/${uc.tx_hash}', '_blank');" style="color: #6366f1;">${uc.tx_hash.substring(0, 14)}...</a></div>
                </div>
            `;
            container.appendChild(cardEl);
        });
    } catch (e) {
        console.error('[Marketplace] Erreur chargement inventaire:', e);
        container.innerHTML = `<p style="text-align:center; color: #ef4444; grid-column: 1 / -1;">${t('marketplace.error_loading')}</p>`;
    }
}


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
    
    // Auto-refresh inventory if the inventory tab is active
    const tabInventory = document.getElementById('tab-inventory');
    if (tabInventory && tabInventory.classList.contains('active')) {
        loadUserInventory();
    }
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
        _showMpNotification(t('marketplace.approve_valid_snt'), 'error');
        return;
    }

    if (!CONTRACT_ADDRESSES.sntToken || !CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification(t('marketplace.contract_unavailable'), 'error');
        return;
    }

    const btn = document.getElementById('marketplace-approve-btn');
    const createBtn = document.getElementById('marketplace-create-btn');
    btn.disabled = true;
    btn.textContent = t('marketplace.creating_btn_label');

    try {
        const sntAmountWei = _safeParseEther(sntAmount);
        const sntContract  = await getContract(CONTRACT_ADDRESSES.sntToken, SNT_ABI);

        const signer    = await getSigner();
        const signerAddr = await signer.getAddress();
        const allowance  = await sntContract.allowance(signerAddr, CONTRACT_ADDRESSES.marketplace);

        if (allowance >= sntAmountWei) {
            _showMpNotification(t('marketplace.approve_success_sufficient'), 'success');
            _approvalDone = true;
            createBtn.disabled = false;
            btn.textContent = t('marketplace.approved_btn_label');
            return;
        }

        btn.textContent = t('marketplace.approve_confirm_wallet');
        await _sendWithFreshNonce((ovr) => sntContract.approve(CONTRACT_ADDRESSES.marketplace, sntAmountWei, ovr));

        _approvalDone = true;
        createBtn.disabled = false;
        btn.textContent = t('marketplace.approved_btn_label');
        _showMpNotification(t('marketplace.approve_success'), 'success');

    } catch (e) {
        console.error('[Marketplace] Erreur approbation:', e);
        _showMpNotification(t('marketplace.approve_failed', { error: parseRpcError(e) }), 'error');
        btn.disabled = false;
        btn.textContent = t('marketplace.approve_btn_label');
    }
}

// ─── ÉTAPE 2 : CRÉATION DE L'OFFRE ────────────────────────────────────────────
async function handleCreateOffer() {
    if (!_assertWalletConnected()) return;
    if (!_approvalDone) {
        _showMpNotification(t('marketplace.create_offer_approve_first'), 'error');
        return;
    }

    const sntAmount     = document.getElementById('mp-snt-amount')?.value;
    const avaxAmount    = document.getElementById('mp-avax-amount')?.value;
    const durationHours = document.getElementById('mp-duration')?.value || 24;

    if (!sntAmount || !avaxAmount || parseFloat(sntAmount) <= 0 || parseFloat(avaxAmount) <= 0) {
        _showMpNotification(t('marketplace.create_offer_valid_amounts'), 'error');
        return;
    }

    const btn = document.getElementById('marketplace-create-btn');
    btn.disabled  = true;
    btn.textContent = t('marketplace.create_offer_pending');

    try {
        const sntAmountWei  = _safeParseEther(sntAmount);
        const avaxAmountWei = _safeParseEther(avaxAmount);
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);

        const createTx = await _sendWithFreshNonce((ovr) =>
            escrowContract.createOffer(sntAmountWei, avaxAmountWei, parseInt(durationHours), ovr)
        );
        btn.textContent = t('marketplace.create_offer_tx_pending');
        console.log('[Marketplace] Offre créée, tx:', createTx.hash);

        _approvalDone = false;
        _showMpNotification(t('marketplace.create_offer_success', { snt: sntAmount, avax: avaxAmount }), 'success');
        addToFeed(t('feed.offer_created', { snt: sntAmount, avax: avaxAmount }), 'var(--success)');

        document.getElementById('mp-snt-amount').value  = '';
        document.getElementById('mp-avax-amount').value = '';
        const approveBtn = document.getElementById('marketplace-approve-btn');
        if (approveBtn) { approveBtn.textContent = t('marketplace.approve_btn_label'); approveBtn.disabled = false; }

        setTimeout(() => loadMarketplaceData(), 4000);

    } catch (e) {
        console.error('[Marketplace] Erreur création offre:', e);
        _showMpNotification(t('marketplace.create_offer_failed', { error: parseRpcError(e) }), 'error');
    } finally {
        btn.disabled  = false;
        btn.textContent = t('marketplace.create_offer') || 'Créer l\'Offre';
    }
}

// ─── ACHAT D'UNE OFFRE ────────────────────────────────────────────────────────
window.marketplaceBuyOffer = async function(offerId, avaxAmount) {
    if (!_assertWalletConnected()) return;
    if (!CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification(t('marketplace.contract_unavailable'), 'error');
        return;
    }
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        const avaxWei = _safeParseEther(avaxAmount.toString());
        addToFeed(t('feed.buying_offer', { id: offerId }), 'var(--primary)');
        await _sendWithFreshNonce((ovr) =>
            escrowContract.fulfillOffer(offerId, { value: avaxWei, ...ovr })
        );
        _showMpNotification(t('marketplace.buy_offer_success'), 'success');
        setTimeout(() => loadMarketplaceData(), 4000);
    } catch (e) {
        console.error('[Marketplace] Erreur achat:', e);
        _showMpNotification(t('marketplace.buy_offer_failed', { error: parseRpcError(e) }), 'error');
    }
};

// ─── ANNULATION D'UNE OFFRE ───────────────────────────────────────────────────
window.marketplaceCancelOffer = async function(offerId) {
    if (!_assertWalletConnected()) return;
    if (!CONTRACT_ADDRESSES.marketplace) {
        _showMpNotification(t('marketplace.contract_unavailable'), 'error');
        return;
    }
    try {
        const escrowContract = await getContract(CONTRACT_ADDRESSES.marketplace, ESCROW_ABI);
        addToFeed(t('feed.canceling_offer', { id: offerId }), 'var(--text-dim)');
        await _sendWithFreshNonce((ovr) => escrowContract.cancelOffer(offerId, ovr));
        _showMpNotification(t('marketplace.cancel_offer_success', { id: offerId }), 'success');
        setTimeout(() => loadMarketplaceData(), 4000);
    } catch (e) {
        console.error('[Marketplace] Erreur annulation:', e);
        _showMpNotification(t('marketplace.cancel_offer_failed', { error: parseRpcError(e) }), 'error');
    }
};
