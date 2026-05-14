import '../css/app.css';

import { initRouter, navigateTo } from './core/router.js';
import { getAuthToken } from './core/api.js';

// Define global app state to emulate old userState
window.userState = {
    id: null,
    walletAddress: null,
    userObject: null
};

document.addEventListener('DOMContentLoaded', () => {
    console.log("🚀 Lancement de l'application Battlepool (Vanilla JS SPA + Vite)");
    
    // Initialisation globale
    checkAuthSession();
    
    // Démarrage du routeur
    initRouter();
    
    // Écouteur de déconnexion globale
    window.addEventListener('auth:expired', () => {
        window.userState = { id: null, walletAddress: null, userObject: null };
        navigateTo(''); // Redirect to language/login
        alert("Session expirée. Veuillez vous reconnecter.");
    });
});

function checkAuthSession() {
    const token = getAuthToken();
    const storedUser = localStorage.getItem('user');
    
    if (token && storedUser) {
        try {
            const user = JSON.parse(storedUser);
            window.userState.id = user.id;
            window.userState.walletAddress = user.wallet_address;
            window.userState.userObject = user;
            console.log("✅ Session active restaurée pour :", user.wallet_address);
        } catch(e) {
            console.error("Erreur parsing user de session:", e);
        }
    } else {
        console.warn("⚠️ Aucune session active trouvée.");
    }
}
