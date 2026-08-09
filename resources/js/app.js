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
import { initPWA } from './core/pwa.js';
import { showToast } from './core/toast.js';

// ===== ÉTAT GLOBAL =====
window.userState = {
    id: null,
    walletAddress: null,
    userObject: null,
    balance: 0,
    bet_amount: 0.01,
    status: 'disconnected'
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

    // 6. Écouteurs globaux
    window.addEventListener('auth:success', () => {
        initEcho();
        
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
            initEcho();
        } catch(e) {
            console.error("Erreur parsing session:", e);
            localStorage.removeItem('user');
            localStorage.removeItem('auth_token');
        }
    } else {
        console.warn("⚠️ Aucune session trouvée.");
    }
}
