// resources/js/core/auth.js
import { BrowserProvider } from 'ethers';
import { secureFetch } from './api.js';

export function parseRpcError(error) {
    const msg = error.message || error.toString();
    if (msg.includes("user rejected transaction")) return "Transaction refusée par l'utilisateur.";
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

export async function connectWallet() {
    if (typeof window.ethereum === 'undefined') {
        alert("MetaMask is required!");
        return false;
    }

    try {
        console.log("[Auth] Connexion MetaMask en cours...");
        const provider = new BrowserProvider(window.ethereum);
        const signer = await provider.getSigner();
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
            userObject: data.user
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
