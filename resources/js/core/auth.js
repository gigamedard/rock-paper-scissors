// resources/js/core/auth.js
import { getProvider, getSigner } from '../web3/web3-core.js';
import { secureFetch } from './api.js';
import { t } from '../modules/i18n.js';
import { showToast } from './toast.js';

export function parseRpcError(error) {
    const msg = error?.message || error?.shortMessage || error?.toString() || "Unknown error";
    
    // Attempt translation via global t function, fallback to French for safety
    const gt = window.t || ((key) => key);
    
    if (msg.includes("user rejected transaction") || msg.includes("User rejected")) return gt('errors.user_rejected');
    if (msg.includes("insufficient funds")) return gt('errors.insufficient_funds');
    if (msg.includes("nonce too low")) return gt('errors.nonce_too_low');
    
    // Extraction de la raison du revert : "reverted with reason string 'XYZ'" ou reason="XYZ"
    const reasonMatch = msg.match(/reverted with reason string '([^']+)'/i) 
        || msg.match(/reason="([^"]+)"/);
    if (reasonMatch && reasonMatch[1]) {
        return `Action refusée : ${reasonMatch[1]}`;
    }
    
    // Extraction d'un custom error : "reverted with custom error 'XYZ(...)'"
    const customErrorMatch = msg.match(/reverted with custom error '([^']+)'/i);
    if (customErrorMatch && customErrorMatch[1]) {
        return `Erreur du contrat : ${customErrorMatch[1]}`;
    }
    
    if (msg.includes("unknown custom error") || msg.includes("execution reverted")) return gt('errors.contract_reverted');
    if (msg.includes("User not found") || msg.includes("Signature invalid")) return gt('errors.auth_failed');
    if (msg.includes("Failed to fetch") || msg.includes("NetworkError")) return gt('errors.network_issue');
    
    // Fallback : message générique SANS exposer le détail technique au client
    return gt('errors.generic_error');
}

export async function connectWallet(providerType = 'injected') {
    if (providerType === 'injected' && typeof window.ethereum === 'undefined') {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isMobile) {
            // Construit l'URL sans le protocole (ex: battlepool.com/dashboard)
            const cleanedUrl = window.location.href.replace(/^https?:\/\//, '');
            const metamaskDeepLink = `https://metamask.app.link/dapp/${cleanedUrl}`;
            console.log("[Auth] Appareil mobile détecté. Redirection vers MetaMask Mobile via Deep Link:", metamaskDeepLink);
            window.open(metamaskDeepLink, '_blank');
            return false;
        }
        showToast(t('errors.metamask_required'), 'error');
        return false;
    }

    try {
        console.log(`[Auth] Connexion ${providerType} en cours...`);
        const provider = await getProvider(providerType);
        const signer = await getSigner(providerType);
        const walletAddress = await signer.getAddress();

        
        // 1. Get Challenge
        const challengeRes = await fetch('/api/wallet/generate-message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ wallet_address: walletAddress })
        });
        
        const challengeData = await challengeRes.json();
        if (!challengeRes.ok) {
            throw new Error("Erreur de connexion au serveur. Veuillez réessayer.");
        }
        
        const message = challengeData.message;

        // 2. Sign Challenge
        const signature = await signer.signMessage(message);

        // 3. Verify Signature
        const verifyRes = await fetch('/api/wallet/verify-signature', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                wallet_address: walletAddress,
                signature: signature,
                locale: localStorage.getItem('user_locale') || 'en'
            })
        });
        
        const data = await verifyRes.json();
        
        if (!verifyRes.ok) throw new Error(data.message || "Erreur serveur.");

        // Persist session
        localStorage.setItem('user', JSON.stringify(data.user));
        localStorage.setItem('auth_token', data.token);
        
        window.userState = {
            id: data.user.id,
            walletAddress: data.user.wallet_address || walletAddress,
            userObject: data.user,
            status: 'dashboard'
        };

        console.log("✅ Authentifié avec succès :", window.userState.walletAddress);
        
        // Dispatch custom event for successful login (Echo initialization etc.)
        window.dispatchEvent(new CustomEvent('auth:success', { detail: data.user }));
        
        return true;
    } catch (error) {
        console.error("[Auth] Échec :", error);
        showToast(t('errors.auth_failed') + ' ' + parseRpcError(error), 'error');
        return false;
    }
}

export function logout() {
    console.log("[Auth] Déconnexion demandée.");
    localStorage.removeItem('user');
    localStorage.removeItem('auth_token');
    localStorage.removeItem('token');
    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'BATTLEPOOL_SESSION_CLEAR' }, '*');
        }
    } catch (e) { /* ignore */ }
    window.location.reload();
}

