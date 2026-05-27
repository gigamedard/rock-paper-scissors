import '../css/app.css';

import { initRouter, navigateTo } from './core/router.js';
import { getAuthToken } from './core/api.js';
import { connectWallet } from './core/auth.js';
import { initEcho } from './core/echo.js';
import { initGame } from './modules/game.js';
import { initI18n } from './modules/i18n.js';
import { initMarketplace } from './modules/marketplace.js';
import { initReferral } from './modules/referral.js';
import { initInfluencer } from './modules/influencer.js';
import { initPWA } from './core/pwa.js';

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

    const connectWcBtn = document.getElementById('connect-wc-btn');
    if (connectWcBtn) {
        connectWcBtn.addEventListener('click', () => connectWallet('walletconnect'));
    }

    // 6. Écouteurs globaux
    window.addEventListener('auth:success', () => {
        initEcho();
    });

    window.addEventListener('auth:expired', () => {
        window.userState = {
            id: null, walletAddress: null, userObject: null,
            balance: 0, bet_amount: 0.01, status: 'disconnected'
        };
        if (window.echoInstance) window.echoInstance.disconnect();
        alert("Session expirée. Veuillez vous reconnecter.");
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
