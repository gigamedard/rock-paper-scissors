// resources/js/core/auth.js
import { getProvider, getSigner, resetProvider, ensurePingalaNetwork } from '../web3/web3-core.js';
import { secureFetch } from './api.js';
import { t } from '../modules/i18n.js';
import { showToast } from './toast.js';

export function parseRpcError(error) {
    const msg = error?.message || error?.shortMessage || error?.toString() || "Unknown error";
    
    // Attempt translation via global t function, fallback to French for safety
    const gt = window.t || ((key) => key);
    
    if (msg.includes("user rejected transaction") || msg.includes("User rejected")) return gt('errors.user_rejected');
    // Fermeture de la modal WalletConnect/AppKit sans connexion : c'est une
    // annulation volontaire (pas un échec technique) → même clé i18n.
    if (error?.code === 'MODAL_CLOSED' || msg.includes("Modal fermée sans connexion")) {
        return gt('errors.user_rejected');
    }
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
            // Mobile sans EIP-1193 injecté : passer par la modal AppKit
            // (connectWallet('walletconnect')) qui propose deep links natifs
            // iOS/Android (Core Wallet, MetaMask…) + QR. L'ancien deep link
            // manuel `deeplink.walletconnect.org` est retiré : il référençait
            // une page morte et doublonnait la modal AppKit.
            console.log("[Auth] Mobile sans provider injecté — ouverture de la modal AppKit (WalletConnect).");
            try {
                showToast(t('errors.wallet_app_required'), 'info');
            } catch (e) { /* toast non bloquant */ }
            const ok = await connectWallet('walletconnect');
            return ok;
        }
        showToast(t('errors.wallet_required'), 'error');
        return false;
    }

    try {
        console.log(`[Auth] Connexion ${providerType} en cours...`);
        // Réinitialiser le cache du provider/signer AVANT de se connecter,
        // pour garantir que le signer correspond au compte MetaMask ACTUEL
        // (sinon un ancien signer #1 peut être réutilisé après un changement de compte).
        await resetProvider();
        const provider = await getProvider(providerType);
        const signer = await getSigner(providerType);
        const walletAddress = await signer.getAddress();

        // Garde-réseau (bug historique corrigé) : le chemin INJECTÉ n'ajoutait
        // JAMAIS le réseau de jeu (seul WalletConnect le faisait, avec un
        // chainId erroné). MetaMask/Core reçoit maintenant le switch vers
        // Pingala (99999) + wallet_addEthereumChain sur 4902/4900.
        // Refus (4001) ≠ bloquant : la connexion peut aboutir hors réseau,
        // `ensurePingalaNetwork()` est rappelé avant chaque flux de tx.
        try {
            await ensurePingalaNetwork();
        } catch (networkError) {
            if (networkError?.code === 4001) {
                showToast("Réseau Pingala non ajouté (refusé). Les transactions échoueront tant que le réseau n'est pas sélectionné.", 'warn');
            } else {
                console.warn('[Auth] Switch Pingala non abouti :', networkError);
            }
        }
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
    // Réinitialiser le cache du provider/signer pour permettre la reconnexion
    // avec un AUTRE compte MetaMask (sinon le signer #1 reste en cache).
    resetProvider();
    try {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'BATTLEPOOL_SESSION_CLEAR' }, '*');
        }
    } catch (e) { /* ignore */ }
    window.location.reload();
}

