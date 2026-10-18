// resources/js/core/auth.js
import { getProvider, getSigner, resetProvider, ensurePingalaNetwork, isWalletConnectActive, getWcSessionAddress, reopenWalletForSigning } from '../web3/web3-core.js';
import { getAddress, hexlify, toUtf8Bytes } from 'ethers';
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
    // SESSION WC DÉJÀ ACTIVE (preuve journal 2026-10-06) : après approbation
    // dans Core, l'utilisateur re-clique Connect Wallet (croyant ne pas être
    // connecté) → resetProvider() tuait la session fraîchement approuvée →
    // boucle infinie. Si une session WC porte une adresse, on la réutilise
    // directement pour finir l'auth (challenge/signature/verify).
    if (providerType === 'injected' && typeof window.ethereum === 'undefined' && isWalletConnectActive()) {
        try {
            const wcAddr = getWcSessionAddress();
            if (wcAddr) {
                console.info('[Auth] session WC déjà active → réutilisation directe pour', wcAddr);
                return _authenticateWithAddress(wcAddr, 'walletconnect');
            }
        } catch (e) {
            console.warn('[Auth] réutilisation session WC échouée → flux complet :', e?.message);
        }
    }
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
        return await _authenticateWithAddress(walletAddress, providerType);
    } catch (error) {
        console.error("[Auth] Échec :", error);
        showToast(t('errors.auth_failed') + ' ' + parseRpcError(error), 'error');
        return false;
    }
}

/**
 * Corps d'authentification partagé : challenge → signature → verify → session.
 * Appelé depuis connectWallet (flux complet) et depuis la réutilisation de
 * session WC existante (sans reconnecter le wallet).
 * @param {string} walletAddress
 * @param {string} providerType 'injected' | 'walletconnect'
 * @returns {Promise<boolean>} true si la session backend est posée
 */
async function _authenticateWithAddress(walletAddress, providerType) {
    try {
        const isInjected = !(providerType === 'walletconnect' || isWalletConnectActive());

        // SYNCHRONISATION DU COMPTE ACTIF (fix « The account shown for this
        // request is not part of the active wallet ») : sur le chemin injecté,
        // on relit les comptes AVANT de demander le challenge. Si l'utilisateur
        // a plusieurs wallets (MetaMask + Core) ou a changé de compte entre
        // l'appel et la signature, l'adresse passée est obsolète → le wallet
        // refuse personal_sign. On repart de l'adresse réellement active.
        let effectiveAddress = walletAddress;
        let injectedProvider = null;
        if (isInjected) {
            try {
                injectedProvider = await getProvider('injected');
                console.info('[Auth] provider injecté', {
                    isMetaMask: !!window.ethereum?.isMetaMask,
                    isCore: !!window.ethereum?.isCore,
                    isBrave: !!window.ethereum?.isBraveWallet,
                    requested: walletAddress
                });
                let accounts = [];
                try {
                    accounts = await injectedProvider.send('eth_requestAccounts', []);
                } catch (reqErr) {
                    console.warn('[Auth] eth_requestAccounts échoué → fallback eth_accounts :', reqErr?.message || reqErr);
                    accounts = await injectedProvider.send('eth_accounts', []);
                }
                console.info('[Auth] comptes du wallet :', accounts);
                const active = Array.isArray(accounts) && accounts[0] ? accounts[0] : null;
                if (active) {
                    effectiveAddress = active;
                    if (String(active).toLowerCase() !== String(walletAddress).toLowerCase()) {
                        console.warn('[Auth] compte actif ≠ adresse demandée → recalcul', {
                            requested: walletAddress, active
                        });
                    }
                } else {
                    console.warn('[Auth] aucun compte actif retourné par le wallet');
                }
            } catch (e) {
                console.warn('[Auth] synchronisation du compte actif échouée :', e?.message || e);
            }
        }

        // Helper : demande un challenge pour une adresse donnée.
        const fetchChallenge = async (addr) => {
            const challengeRes = await fetch('/api/wallet/generate-message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ wallet_address: addr })
            });
            const challengeData = await challengeRes.json();
            if (!challengeRes.ok) {
                throw new Error("Erreur de connexion au serveur. Veuillez réessayer.");
            }
            return challengeData.message;
        };

        // Signature injectée — MÉTHODE APPRISE D'APP2 (preuve terrain) :
        // `personal_sign` DIRECT sur window.ethereum avec le compte ACTIF
        // re-interrogé et checksummé. Évite l'erreur MetaMask « The account
        // shown for this request is not part of the active wallet » causée par
        // l'adresse périmée/non-checksummée passée via le signer ethers.
        const signInjected = async (msg) => {
            // Provider EIP-1193 BRUT (window.ethereum) : c'est lui qui expose
            // .request ; le BrowserProvider ethers n'a pas .request.
            const eth = (typeof window !== 'undefined' && window.ethereum && typeof window.ethereum.request === 'function')
                ? window.ethereum
                : null;
            if (!eth) throw new Error('Aucun provider wallet.');
            let accts = [];
            try { accts = await eth.request({ method: 'eth_requestAccounts' }); } catch (e) { accts = []; }
            if (!accts || !accts.length) throw new Error('Aucun compte wallet.');
            let active = accts[0];
            try { active = getAddress(active); } catch (e) { /* garde tel quel */ }
            if (String(active).toLowerCase() !== String(effectiveAddress).toLowerCase()) {
                // Compte changé depuis le challenge → nouveau challenge lié à l'adresse active.
                effectiveAddress = active;
                msg = await fetchChallenge(effectiveAddress);
            } else {
                effectiveAddress = active;
            }
            const msgHex = hexlify(toUtf8Bytes(msg));
            return await eth.request({ method: 'personal_sign', params: [msgHex, active] });
        };

        // Helper : signe le challenge avec l'adresse active.
        const signChallenge = async (addr, msg) => {
            if (isInjected) return signInjected(msg);
            const signer = await getSigner('walletconnect');
            return signer.signMessage(msg);
        };

        // 1. Get Challenge (pour l'adresse RÉELLEMENT active)
        let message = await fetchChallenge(effectiveAddress);

        // 2. Sign Challenge
        // MOBILE WC (preuve journal 2026-10-06) : après approbation de session,
        // l'utilisateur revient dans Safari — le personal_sign arrive dans Core
        // et n'est jamais vu → il croit n'est pas connecté et re-clique (chaque
        // re-clic tuant la session → boucle). On ré-ouvre le wallet juste
        // avant la signature (pattern WC officiel « redirect on request ») —
        // uniquement pour le chemin WC (le chemin injecté affiche nativement).
        if (!isInjected) {
            // Toast explicite : le bandeau iOS dira « Ouvrir dans Core ? » sans
            // préciser pourquoi — on annonce la signature (retour utilisateur :
            // « le message devait plutôt dire signer le message dans Core »).
            showToast(t('sign_in_wallet'), 'info', 3500);
            console.info('[Auth] WC actif → ré-ouverture de Core pour la signature…');
            // PRÉ-SWITCH (capture 11:24 du journal) : le `wallet_switchEthereumChain`
            // envoyé dans `connectWalletMobile` part pendant que Core est en
            // arrière-plan (Safari) → Core ne le traite pas et applique sa
            // propre politique « Switch Network » sur le personal_sign qui
            // suit. On le RENVOIE ici (Core est au 1er plan via le
            // reopenWalletForSigning) AVANT la signature : Core le traitera
            // en amont du personal_sign et ne demandera plus de switch.
            try {
                await ensurePingalaNetwork();
            } catch (e) {
                console.warn('[Auth] Pré-switch Pingala échoué (Core refus ?):', e?.message || e);
            }
            reopenWalletForSigning('core');
        }

        // Signature. Si le wallet répond « not part of the active wallet »
        // (MetaMask avec plusieurs comptes / compte sélectionné différent de
        // celui demandé), on RÉ-INITIE la permission (wallet_requestPermissions
        // → sélecteur de compte), on reprend le compte actif et on re-signe
        // avec un NOUVEAU challenge (l'ancien nonce est lié à l'adresse).
        let signature;
        try {
            signature = await signChallenge(effectiveAddress, message);
        } catch (signErr) {
            const sm = String(signErr?.message || '');
            if (!(isInjected && injectedProvider && /not part of the active wallet|re-initiate/i.test(sm))) {
                throw signErr;
            }
            console.warn('[Auth] compte non actif côté wallet → wallet_requestPermissions + retry');
            try {
                await injectedProvider.send('wallet_requestPermissions', [{ eth_accounts: {} }]);
            } catch (permErr) {
                console.warn('[Auth] wallet_requestPermissions échoué :', permErr?.message || permErr);
            }
            let accounts2 = [];
            try {
                accounts2 = await injectedProvider.send('eth_accounts', []);
            } catch (e2) { /* noop */ }
            console.info('[Auth] comptes après re-permission :', accounts2);
            const active2 = Array.isArray(accounts2) && accounts2[0] ? accounts2[0] : null;
            if (!active2) throw signErr;
            effectiveAddress = active2;
            message = await fetchChallenge(effectiveAddress);
            signature = await signChallenge(effectiveAddress, message);
        }

        // 3. Verify Signature
        const verifyRes = await fetch('/api/wallet/verify-signature', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                wallet_address: effectiveAddress,
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
            walletAddress: data.user.wallet_address || effectiveAddress,
            userObject: data.user,
            status: 'dashboard'
        };

        console.log("✅ Authentifié avec succès :", window.userState.walletAddress);

        // Dispatch custom event for successful login (Echo initialization etc.)
        window.dispatchEvent(new CustomEvent('auth:success', { detail: data.user }));

        return true;
    } catch (error) {
        console.error("[Auth] Échec (auth par adresse) :", error);
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

