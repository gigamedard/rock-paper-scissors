import '../css/app.css';

import { initRouter, navigateTo } from './core/router.js';
import { getAuthToken } from './core/api.js';
import { connectWallet, logout } from './core/auth.js';
import { initEcho } from './core/echo.js';
import { initGame } from './modules/game.js';
import { initI18n } from './modules/i18n.js';
import { initMarketplace } from './modules/marketplace.js';
import { initReferral } from './modules/referral.js';
import { initInfluencer } from './modules/influencer.js';
import { initStats } from './modules/stats.js';
import { initPWA } from './core/pwa.js';
import { showToast } from './core/toast.js';
import { addPingalaNetwork, getActiveEip1193Provider, closeWalletModal, ensureWalletVisibleForTx, warmupWalletLibs } from './web3/web3-core.js';
import { initDebugLog, debugLog } from './modules/debug-log.js';
import { initOnboardingCore } from './modules/onboarding-core.js';

// ===== ÉTAT GLOBAL =====
window.userState = {
    id: null,
    walletAddress: null,
    userObject: null,
    balance: 0,
    bet_amount: 0.01,
    status: 'disconnected',
    autoplay_active: false,
    session_started: false,
    cooldown_until: null
};

document.addEventListener('DOMContentLoaded', async () => {
    console.log("🚀 Battlepool SPA — Démarrage");

    // Capture du lien de parrainage (?ref=CODE)
    const urlParams = new URLSearchParams(window.location.search);
    const refCode = urlParams.get('ref');
    if (refCode) {
        localStorage.setItem('pending_referral', refCode);
        console.log("🔗 Code de parrainage capturé :", refCode);
    }

    // 0. Initialisation de la PWA
    initPWA();

    // 0 bis. Journal visuel mobile (bouton 🐞 → pop-up + copie presse-papiers) :
    // l'utilisateur transmet les logs depuis l'iPhone pour le debug à distance.
    // PROD HARDENING : désactivé hors DEV. Activable ponctuellement via `?debug=1`
    // (ou en localhost) — le bouton n'est plus exposé en prod par défaut.
    const _isDevHost = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(location.hostname);
    const _debugFlag = new URLSearchParams(location.search).has('debug');
    if (_isDevHost || _debugFlag) {
        initDebugLog();
        debugLog('APP', 'DOMContentLoaded Battlepool SPA');
    }
    // 0 ter. Onboarding Core Wallet (ajout manuel de Pingala) : handlers
    // copier + QR + auto-add. Initialisé tôt pour les nouveaux utilisateurs.
    initOnboardingCore();

    // 1. Internationalisation (doit être en premier pour traduire le DOM)
    await initI18n();

    // 2. Restauration de session
    checkAuthSession();

    // 3. Initialisation des modules métier
    initGame();
    initMarketplace();
    initReferral();
    initInfluencer();

    // 4. Démarrage du routeur (après les modules)
    initRouter();

    // 4bis. Stats publiques (polling léger + ticker) — si le DOM les porte
    initStats();

    // 5. Bouton de connexion
    const connectBtn = document.getElementById('connect-btn');
    if (connectBtn) {
        connectBtn.addEventListener('click', () => connectWallet('injected'));
    }

    const enterArenaBtn = document.getElementById('enter-arena-btn');
    if (enterArenaBtn) {
        enterArenaBtn.addEventListener('click', () => connectWallet('injected'));
    }

    const connectWcBtn = document.getElementById('connect-wc-btn');
    if (connectWcBtn) {
        connectWcBtn.addEventListener('click', () => connectWallet('walletconnect'));
    }

    // ===== P0 — Option B : CTA de connexion dupliqués dans la landing =====
    // Ids DISTINCTS (#connect-btn-landing / #connect-wc-btn-landing) avec les
    // MÊMES handlers que #connect-btn / #connect-wc-btn : getElementById ne
    // branche qu'un seul match, d'où des ids séparés et un wiring explicite.
    // La visibilité suit l'état global : #view-disconnected (qui les porte)
    // est masqué/affiché par game.js updateUI() via auth:success/auth:expired.
    const connectBtnLanding = document.getElementById('connect-btn-landing');
    if (connectBtnLanding) {
        connectBtnLanding.addEventListener('click', () => connectWallet('injected'));
    }

    const connectWcBtnLanding = document.getElementById('connect-wc-btn-landing');
    if (connectWcBtnLanding) {
        connectWcBtnLanding.addEventListener('click', () => connectWallet('walletconnect'));
    }

    // ===== P2 — Modale « Créer une offre » (#mp-offer-modal) =====
    // Ouverture/fermeture purement par classList : aucun listener marketplace
    // n'est créé ni purgé ici. Les boutons de transaction
    // (#marketplace-approve-btn / #marketplace-create-btn / saisies) restent
    // branchés par marketplace.js (getElementById), séquence approve → create
    // en 2 étapes inchangée (disabled pendant signature + toasts).
    const mpOfferModal = document.getElementById('mp-offer-modal');
    const mpCreateToggleBtn = document.getElementById('mp-create-toggle-btn');
    const mpModalCloseBtn = document.getElementById('mp-modal-close');

    function openMpOfferModal() {
        if (!mpOfferModal) return;
        mpOfferModal.classList.add('mp-modal-open');
        document.body.style.overflow = 'hidden'; // bloquer le scroll de fond tant que la modale est ouverte
    }

    function closeMpOfferModal() {
        if (!mpOfferModal) return;
        mpOfferModal.classList.remove('mp-modal-open');
        document.body.style.overflow = '';
    }

    if (mpCreateToggleBtn && mpOfferModal) {
        mpCreateToggleBtn.addEventListener('click', openMpOfferModal);
    }
    if (mpModalCloseBtn && mpOfferModal) {
        mpModalCloseBtn.addEventListener('click', closeMpOfferModal);
    }
    if (mpOfferModal) {
        // Clic sur le fond (overlay) = fermer ; clic dans la feuille = rien.
        mpOfferModal.addEventListener('click', (e) => {
            if (e.target === mpOfferModal) closeMpOfferModal();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mpOfferModal.classList.contains('mp-modal-open')) closeMpOfferModal();
        });
    }

    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => {
            window.dispatchEvent(new CustomEvent('app:refresh'));
        });
    }

    const disconnectBtn = document.getElementById('disconnect-btn');
    if (disconnectBtn) {
        disconnectBtn.addEventListener('click', () => logout());
    }

    // Bouton « 🎁 Réclamer l'airdrop » (50 TST de bienvenue, une seule fois)
    const faucetBtn = document.getElementById('faucet-claim-btn');
    if (faucetBtn) {
        faucetBtn.addEventListener('click', async () => {
            faucetBtn.disabled = true;
            const orig = faucetBtn.textContent;
            try {
                const { secureFetch } = await import('./core/api.js');
                const res = await secureFetch('/user/faucet-claim', { method: 'POST' });
                const data = await res.json();
                if (!res.ok) {
                    if (res.status === 409) {
                        showToast(data.error || 'Airdrop déjà réclamé', 'info');
                        updateFaucetButton(true);
                        return;
                    }
                    showToast(data.error || 'Échec de la réclamation', 'error');
                    return;
                }
                showToast(data.message || 'Airdrop demandé — 50 TST arrivent !', 'success', 5000);
                updateFaucetButton(true);
                window.dispatchEvent(new CustomEvent('app:refresh'));
            } catch (e) {
                showToast('Erreur : ' + (e.message || e), 'error');
            } finally {
                faucetBtn.disabled = false;
                faucetBtn.textContent = orig;
            }
        });
    }

    function updateFaucetButton(claimed) {
        if (!faucetBtn) return;
        if (claimed) {
            window.__bpFaucetClaimed = true;
            faucetBtn.style.display = 'none';
        } else if (!window.__bpFaucetClaimed) {
            faucetBtn.style.display = 'block';
        }
    }

    window.addEventListener('auth:success', () => {
        const user = window.userState?.userObject;
        updateFaucetButton(!!(user && user.has_received_airdrop));
    });

    // Bouton « ➕ Ajouter PRANA au wallet » (EIP-747 wallet_watchAsset).
    // Sur mobile (Core/MetaMask), ajoute le token ERC-20 PRANA à la liste des
    // tokens visibles. Fallback : copie de l'adresse du contrat.
    const addPranaBtn = document.getElementById('add-prana-btn');
    if (addPranaBtn) {
        const PRANA_ADDR = '0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B';
        const tx = (k, d) => (window.t ? window.t(k) : d);
        addPranaBtn.addEventListener('click', async () => {
            try {
                const { getActiveEip1193Provider } = await import('./web3/web3-core.js');
                const provider = (typeof window.ethereum !== 'undefined' && window.ethereum)
                    ? window.ethereum
                    : getActiveEip1193Provider();
                if (!provider || typeof provider.request !== 'function') {
                    showToast(tx('marketplace.add_prana_no_wallet', 'Ouvrez votre wallet puis réessayez.'), 'warn');
                    return;
                }
                const ok = await provider.request({
                    method: 'wallet_watchAsset',
                    params: { type: 'ERC20', options: { address: PRANA_ADDR, symbol: 'PRANA', decimals: 18 } }
                });
                if (ok) {
                    showToast(tx('marketplace.add_prana_ok', 'PRANA ajouté au wallet ✅'), 'success');
                } else {
                    showToast(tx('marketplace.add_prana_cancel', 'Ajout annulé.'), 'info');
                }
            } catch (e) {
                try { await navigator.clipboard.writeText(PRANA_ADDR); } catch (_) { /* noop */ }
                showToast(tx('marketplace.add_prana_fallback',
                    'Ajout auto non supporté. Adresse PRANA copiée : 0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B'), 'info', 8000);
            }
        });
    }

    // ===== PONT EIP-1193 App1 → iframe App 2 (index.html, cross-origin) =====
    // Le pont postMessage (script inline de index.html) relaie les RPC de
    // l'iframe sur CE getter, qui renvoie le provider ACTIF (injecté OU
    // WalletConnect mobile). Exposé sur window car le pont est un script inline
    // non-bundlé (ne peut pas importer le module).
    window.__bpGetEip1193 = getActiveEip1193Provider;

    // Réveil du wallet AVANT une signature demandée par l'iframe App 2 : sur le
    // chemin WalletConnect mobile, Core est en arrière-plan et son socket WC gelé
    // → une demande personal_sign/eth_sendTransaction venue du pont n'est jamais
    // vue et expire (« Délai dépassé (wallet) », journal 2026-10-15). On ré-émet
    // le deeplink Core (pattern « redirect to wallet on request ») comme pour les
    // tx natives d'App 1. No-op sur le chemin injecté.
    window.__bpWakeWallet = ensureWalletVisibleForTx;

    // WARM-UP (idle) : précharge les chunks AppKit/WalletConnect pour que le clic
    // « Connect » soit instantané (parité App 2, dont la lib est bundlée).
    try {
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => warmupWalletLibs(), { timeout: 4000 });
        } else {
            setTimeout(() => warmupWalletLibs(), 2500);
        }
    } catch (e) { /* noop */ }

    // Parité App 2 : à la connexion réussie, fermer tout overlay wallet
    // (modal AppKit) + le loader, pour passer au jeu sans étape manuelle.
    window.addEventListener('auth:success', () => {
        try { closeWalletModal(); } catch (e) { /* noop */ }
        try { window.hideLoader && window.hideLoader(); } catch (e) { /* noop */ }
    });

    // Bouton « 🚪 Leave pool » (sortie volontaire, stake remboursé via bridge).
    // Le même handler sert aussi le bouton DANS l'overlay de combat, pour ne
    // jamais rester bloqué quand l'overlay couvre l'écran.
    async function doLeavePool(btn) {
        const orig = btn.textContent;
        btn.disabled = true;
        try {
            const { secureFetch } = await import('./core/api.js');
            const res = await secureFetch('/user/leave-pool', { method: 'POST' });
            const data = await res.json();
            if (!res.ok) {
                showToast(data.error || 'Échec de la sortie', 'error');
                return;
            }
            showToast(data.message || 'Pool quittée', 'success', 5000);
            const ov = document.getElementById('combat-overlay');
            if (ov) ov.style.display = 'none';
            window.dispatchEvent(new CustomEvent('app:refresh'));
        } catch (e) {
            showToast('Erreur : ' + (e.message || e), 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = orig;
        }
    }
    ['leave-pool-btn', 'combat-leave-btn'].forEach((id) => {
        const btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', () => doLeavePool(btn));
    });

    // Bouton « ⊕ Réseau » : ajoute/active Pingala Chain dans le wallet injecté
    // (wallet_switchEthereumChain, puis wallet_addEthereumChain sur 4902/4900).
    // Même pattern de binding que les boutons ci-dessus (pas d'onclick inline).
    const addNetworkBtn = document.getElementById('add-network-btn');
    if (addNetworkBtn) {
        addNetworkBtn.addEventListener('click', async () => {
            if (typeof window.ethereum === 'undefined') {
                showToast(window.t ? window.t('network.no_wallet') : 'Aucun wallet injecté détecté.', 'warn');
                return;
            }
            addNetworkBtn.disabled = true;
            try {
                await addPingalaNetwork(window.ethereum);
                showToast(window.t ? window.t('network.added') : 'Réseau Pingala ajouté.', 'success');
            } catch (e) {
                // 4001 = refus utilisateur : message dédié, UI réessayable
                if (e && e.code === 4001) {
                    showToast(window.t ? window.t('network.rejected') : 'Ajout du réseau refusé.', 'info');
                } else {
                    console.error('[App] Ajout du réseau Pingala échoué :', e);
                    showToast((window.t ? window.t('network.failed') : 'Impossible d\'ajouter le réseau Pingala.') + (e?.message ? ` (${e.message})` : ''), 'error');
                }
            } finally {
                addNetworkBtn.disabled = false;
            }
        });
    }

    // 6. Écouteurs globaux
    window.addEventListener('auth:success', () => {
        initEcho().catch(e => console.warn("[Echo] init:", e?.message || e));
        
        // Appliquer automatiquement le parrainage si un code a été capturé
        const pendingRef = localStorage.getItem('pending_referral');
        if (pendingRef) {
            import('./core/api.js').then(({ secureFetch }) => {
                secureFetch('/user/set-referral', {
                    method: 'POST',
                    body: JSON.stringify({ referral_code: pendingRef })
                }).then(res => {
                    if (res.ok) {
                        console.log("✅ Code de parrainage automatique appliqué avec succès !");
                        localStorage.removeItem('pending_referral');
                    } else {
                        console.warn("⚠️ Le code de parrainage n'a pas pu être appliqué (peut-être déjà parrainé ?)");
                    }
                }).catch(err => console.error("Erreur lors de l'application du parrainage:", err));
            });
        }
    });

    window.addEventListener('auth:expired', () => {
        // Éviter la boucle de rechargement : si on vient déjà de recharger
        // (flag dans sessionStorage), on vide tout et on affiche l'écran de connexion.
        if (sessionStorage.getItem('auth_expired_reloaded')) {
            sessionStorage.removeItem('auth_expired_reloaded');
            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
            window.userState = {
                id: null, walletAddress: null, userObject: null,
                balance: 0, bet_amount: 0.01, status: 'disconnected'
            };
            if (window.echoInstance) window.echoInstance.disconnect();
            showToast(window.t ? window.t('connect.session_expired') : "Session expirée. Veuillez vous reconnecter.", 'warn');
            // Rediriger vers l'écran de connexion au lieu de recharger
            const connectScreen = document.getElementById('screen-connect') || document.getElementById('view-disconnected');
            if (connectScreen) {
                document.querySelectorAll('.page.active, .screen.active').forEach(s => s.classList.remove('active'));
                connectScreen.classList.add('active');
            }
            return;
        }
        sessionStorage.setItem('auth_expired_reloaded', '1');
        window.userState = {
            id: null, walletAddress: null, userObject: null,
            balance: 0, bet_amount: 0.01, status: 'disconnected'
        };
        if (window.echoInstance) window.echoInstance.disconnect();
        showToast(window.t ? window.t('connect.session_expired') : "Session expirée. Veuillez vous reconnecter.", 'warn');
        window.location.reload();
    });

    // Détection automatique du changement de compte MetaMask
    if (typeof window.ethereum !== 'undefined') {
        window.ethereum.on('accountsChanged', (accounts) => {
            if (accounts.length === 0) {
                logout();
                return;
            }
            const newAddr = accounts[0].toLowerCase();
            const storedUser = localStorage.getItem('user');
            if (storedUser) {
                try {
                    const u = JSON.parse(storedUser);
                    const currentAddr = (u.wallet_address || u.wallet || '').toLowerCase();
                    if (currentAddr && newAddr !== currentAddr) {
                        console.log('[App] Changement de compte MetaMask détecté :', currentAddr, '→', newAddr);
                        logout();
                    }
                } catch (e) {}
            }
        });
    }

    setTimeout(validateSessionAgainstMetamask, 500);
});

async function validateSessionAgainstMetamask() {
    if (typeof window.ethereum === 'undefined') return;
    const storedUser = localStorage.getItem('user');
    if (!storedUser) return;
    try {
        const accounts = await window.ethereum.request({ method: 'eth_accounts' });
        if (accounts.length === 0) {
            console.log('[Auth] MetaMask sans compte connecté, déconnexion');
            logout();
            return;
        }
        const mmAddr = accounts[0].toLowerCase();
        const user = JSON.parse(storedUser);
        const storedAddr = (user.wallet_address || user.wallet || '').toLowerCase();
        if (storedAddr && mmAddr !== storedAddr) {
            console.log(`[Auth] Session stockée (${storedAddr}) ≠ MetaMask (${mmAddr}), déconnexion`);
            logout();
        }
    } catch (e) {}
}

function checkAuthSession() {
    const token = getAuthToken();
    const storedUser = localStorage.getItem('user');

    if (token && storedUser) {
        try {
            const user = JSON.parse(storedUser);
            window.userState.id = user.id;
            window.userState.walletAddress = user.wallet_address || user.wallet;
            window.userState.userObject = user;
            window.userState.status = 'dashboard';
            console.log("✅ Session restaurée:", window.userState.walletAddress);
            initEcho().catch(e => console.warn("[Echo] init:", e?.message || e));
            // Afficher le bouton airdrop si non encore réclamé
            const fb = document.getElementById('faucet-claim-btn');
            if (fb && !user.has_received_airdrop) fb.style.display = 'block';
        } catch(e) {
            console.error("Erreur parsing session:", e);
            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
        }
    } else {
        console.warn("⚠️ Aucune session trouvée.");
    }
}
