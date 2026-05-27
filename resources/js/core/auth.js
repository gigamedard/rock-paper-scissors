// resources/js/core/auth.js
import { getProvider, getSigner } from '../web3/web3-core.js';
import { secureFetch } from './api.js';

export function parseRpcError(error) {
    const msg = error.message || error.toString();
    if (msg.includes("user rejected transaction") || msg.includes("User rejected")) return "Transaction refusée par l'utilisateur.";
    if (msg.includes("insufficient funds")) return "Fonds insuffisants pour couvrir la transaction + gaz.";
    if (msg.includes("nonce too low")) return "Erreur de synchronisation réseau (Nonce). Réessayez.";
    if (msg.includes("execution reverted")) {
        const match = msg.match(/reason="([^"]+)"/);
        if (match && match[1]) return `Action refusée par le contrat : ${match[1]}`;
        return "Transaction rejetée par le Smart Contract (conditions non remplies).";
    }
    if (msg.includes("User not found") || msg.includes("Signature invalid")) return "Erreur d'authentification. Veuillez vous reconnecter.";
    return "Erreur technique : " + (msg.length > 100 ? msg.substring(0, 100) + "..." : msg);
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
        alert("MetaMask is required!");
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
        const { message } = await challengeRes.json();

        // 2. Sign Challenge
        const signature = await signer.signMessage(message);

        // 3. Verify Signature
        const verifyRes = await fetch('/api/wallet/verify-signature', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                wallet_address: walletAddress,
                signature: signature
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
        alert("Authentication failed: " + parseRpcError(error));
        return false;
    }
}
