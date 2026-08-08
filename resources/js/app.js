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
        window.userState = {
            id: null, walletAddress: null, userObject: null,
            balance: 0, bet_amount: 0.01, status: 'disconnected'
        };
        if (window.echoInstance) window.echoInstance.disconnect();
        showToast(window.t ? window.t('connect.session_expired') : "Session expirée. Veuillez vous reconnecter.", 'warn');
        window.location.reload();
    });
});

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
