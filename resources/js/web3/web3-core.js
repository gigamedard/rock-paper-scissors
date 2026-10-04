// resources/js/web3/web3-core.js
/**
 * Couche d'abstraction Web3.
 * - Utilise Ethers.js v6 en priorité (moderne et léger)
 * - Garde Web3.js comme fallback pour les cas limites (ex: méthodes spécifiques)
 *
 * MIGRATION PINGALA CHAIN (Phase E) :
 * - chainId 99999 (0x1869f), symbole natif TST, RPC public (voir PRANA_NETWORK).
 * - Le chemin INJECTÉ (MetaMask/Core) implémente désormais switch + add réseau
 *   (bug historique : seul WalletConnect le faisait, et avec un chainId erroné).
 * - La config réseau vit ICI et NULLE PART AILLEURS : les modules importent
 *   PRANA_NETWORK / ensurePingalaNetwork depuis ce module.
 *
 * NOTE WalletConnect SDK : `chains: [1]` est une contrainte du SDK
 * (@walletconnect/ethereum-provider exige une chaîne EIP-155 "connue" pour
 * l'appairage initial). La chaîne de jeu (99999) est en `optionalChains`
 * et le switch vers Pingala est fait juste après la connexion.
 */
import { BrowserProvider, Contract } from 'ethers';
import { EthereumProvider } from '@walletconnect/ethereum-provider';

// ═══ CONFIG RÉSEAU CENTRALISÉE — PINGALA CHAIN ═══════════════════════════════
// UN SEUL endroit : ne pas dupliquer ces valeurs ailleurs dans le front.
export const PRANA_NETWORK = {
    chainId: 99999,
    chainIdHex: '0x1869f',
    chainName: 'Pingala Chain',
    nativeCurrency: { name: 'TST', symbol: 'TST', decimals: 18 },
    rpcUrl: 'https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc',
    // Pas d'explorateur de blocs sur Pingala pour l'instant → les liens
    // d'explorateur de l'UI doivent être conditionnels (voir marketplace.js).
    blockExplorerUrls: []
};

// Symbole du token ERC20 de jeu (PranaToken exposé via /api/artefacts, clé 'snt')
export const TOKEN_SYMBOL = 'PRANA';
// Symbole du gas natif (testnet Pingala)
export const GAS_SYMBOL = 'TST';

let _provider = null;
let _signer = null;
let _walletConnectProvider = null;

/**
 * Résout le provider brut EIP-1193 le plus adapté :
 * - wallet injecté (MetaMask, Core, EIP-6963) si présent ;
 * - sinon provider WalletConnect (si une session existe).
 */
function _getRawProvider() {
    if (typeof window !== 'undefined' && typeof window.ethereum !== 'undefined') {
        return window.ethereum;
    }
    return _walletConnectProvider;
}

/**
 * BOUTON « AJOUTER TOUS MES RÉSEAUX » (demande initiale du projet).
 * Basculer le wallet vers Pingala Chain ; si la chaîne est inconnue du wallet
 * (erreurs 4902 / 4900), la proposer via wallet_addEthereumChain.
 * - Utilisable pour les wallets INJECTÉS (MetaMask, Core) ET WalletConnect.
 * - Le refus utilisateur (4001) est propagé tel quel : l'UI reste réessayable.
 * @param {object} provider - provider EIP-1193 brut (window.ethereum, WC, etc.)
 * @returns {Promise<unknown>} résultat de la requête (null si déjà sur la chaîne)
 */
export async function addPingalaNetwork(provider) {
    if (!provider || typeof provider.request !== 'function') {
        throw new Error('Provider Web3 invalide ou absent.');
    }

    try {
        console.log(`[Web3] Switch vers Pingala Chain (${PRANA_NETWORK.chainIdHex})...`);
        return await provider.request({
            method: 'wallet_switchEthereumChain',
            params: [{ chainId: PRANA_NETWORK.chainIdHex }]
        });
    } catch (switchError) {
        // 4001 = refus utilisateur → on laisse remonter, aucun état bloqué
        if (switchError.code === 4001) {
            throw switchError;
        }

        // 4902 : chaîne inconnue ; 4900 : chaîne non configurée.
        // Certains wallets emballent l'erreur (switchError.data.originalError).
        const isUnrecognizedChain =
            switchError.code === 4902 ||
            switchError.code === 4900 ||
            switchError?.data?.originalError?.code === 4902 ||
            (switchError.message && switchError.message.includes('Unrecognized chain ID'));

        if (isUnrecognizedChain) {
            try {
                console.log('[Web3] Chaîne inconnue du wallet. Ajout de Pingala Chain...');
                return await provider.request({
                    method: 'wallet_addEthereumChain',
                    params: [{
                        chainId: PRANA_NETWORK.chainIdHex,
                        chainName: PRANA_NETWORK.chainName,
                        nativeCurrency: { ...PRANA_NETWORK.nativeCurrency },
                        rpcUrls: [PRANA_NETWORK.rpcUrl],
                        blockExplorerUrls: [...PRANA_NETWORK.blockExplorerUrls]
                    }]
                });
            } catch (addError) {
                if (addError.code === 4001) {
                    throw addError;
                }
                console.error("[Web3] Échec de l'ajout de Pingala Chain :", addError);
                throw addError;
            }
        }

        console.error('[Web3] Échec du switch vers Pingala Chain :', switchError);
        throw switchError;
    }
}

/**
 * Garde-réseau transactionnel : à appeler AVANT tout flux de transaction
 * (startSession, claim, achats marketplace, approvals).
 * Silencieux si le wallet est déjà sur Pingala (simple eth_chainId),
 * déclenche le switch/add seulement si la chaîne n'est pas la bonne.
 * @returns {Promise<boolean>} true si le wallet est sur Pingala Chain.
 */
export async function ensurePingalaNetwork() {
    const provider = _getRawProvider();
    if (!provider || typeof provider.request !== 'function') {
        throw new Error('Aucun provider Web3 détecté pour vérifier le réseau.');
    }

    // Déjà sur la bonne chaîne ? (aucune popup dans ce cas)
    try {
        const currentChainId = await provider.request({ method: 'eth_chainId' });
        if (currentChainId === PRANA_NETWORK.chainIdHex) {
            return true;
        }
        console.warn(`[Web3] Chaîne actuelle (${currentChainId}) ≠ Pingala (${PRANA_NETWORK.chainIdHex}) → switch requis.`);
    } catch (e) {
        // eth_chainId non supporté (rare) : on tente quand même le switch
        console.warn('[Web3] eth_chainId indisponible, tentative de switch direct :', e);
    }

    await addPingalaNetwork(provider);
    return true;
}

/**
 * Initialise et retourne le provider Ethers.js.
 * Supporte MetaMask, Core Wallet (EIP-6963), et WalletConnect.
 * @param {string} type - 'injected' (MetaMask/default) ou 'walletconnect'
 */
export async function getProvider(type = 'injected') {
    if (_provider) return _provider;

    if (type === 'walletconnect') {
        if (!_walletConnectProvider) {
            const projectId = import.meta.env.VITE_WALLETCONNECT_PROJECT_ID || 'c074cb1e22709ff3c6902251ad45adcc'; // Fallback demo ID if none set

            console.log(`[Web3] Initialisation de WalletConnect avec RPC Pingala: ${PRANA_NETWORK.rpcUrl}`);

            _walletConnectProvider = await EthereumProvider.init({
                projectId: projectId,
                chains: [1], // Contrainte SDK : chaîne EIP-155 standard pour l'appairage initial (documenté)
                optionalChains: [PRANA_NETWORK.chainId], // Pingala (99999) facultatif — switch après connexion
                showQrModal: true,
                qrModalOptions: {
                    themeMode: 'dark'
                },
                rpcMap: {
                    1: 'https://cloudflare-eth.com',
                    [PRANA_NETWORK.chainId]: PRANA_NETWORK.rpcUrl
                },
                metadata: {
                    name: 'Battlepool',
                    description: 'Battlepool Game dApp',
                    url: window.location.origin,
                    icons: [window.location.origin + '/logo.png']
                }
            });
        }

        // Active la session de connexion
        await _walletConnectProvider.connect();

        // Switch/ajout programmatique vers Pingala Chain (99999)
        try {
            await addPingalaNetwork(_walletConnectProvider);
        } catch (e) {
            // Refus utilisateur ou échec : non bloquant, ensurePingalaNetwork
            // sera rappelé avant chaque flux de transaction.
            console.warn('[Web3] Réseau Pingala non activé côté WalletConnect :', e);
        }

        _provider = new BrowserProvider(_walletConnectProvider);
        return _provider;
    }

    // Comportement standard injecté (MetaMask / Core Wallet)
    if (typeof window.ethereum === 'undefined') {
        throw new Error("Aucun provider Web3 détecté. Veuillez installer MetaMask ou utiliser WalletConnect.");
    }

    // Événements wallet : réinitialiser le cache sans recharger la page
    // (règles fiche frontend_wallet_agent n°3). Bind unique.
    if (!window._bpWalletEventsBound && typeof window.ethereum.on === 'function') {
        window._bpWalletEventsBound = true;
        window.ethereum.on('accountsChanged', () => {
            console.log('[Web3] accountsChanged → reset du provider/signer');
            _provider = null;
            _signer = null;
        });
        window.ethereum.on('chainChanged', () => {
            console.log('[Web3] chainChanged → reset du provider/signer');
            _provider = null;
            _signer = null;
        });
    }

    _provider = new BrowserProvider(window.ethereum);
    return _provider;
}

/**
 * Retourne le signer connecté (utilisateur actif).
 * @param {string} type - 'injected' ou 'walletconnect'
 */
export async function getSigner(type = 'injected') {
    if (_signer) return _signer;
    const provider = await getProvider(type);
    _signer = await provider.getSigner();
    return _signer;
}

/**
 * Instancie un contrat Ethers.js avec signer (lecture + écriture).
 */
export async function getContract(address, abi) {
    const signer = await getSigner();
    return new Contract(address, abi, signer);
}

/**
 * Instancie un contrat Ethers.js sans signer (lecture seule).
 */
export async function getReadOnlyContract(address, abi) {
    const provider = await getProvider();
    return new Contract(address, abi, provider);
}

/**
 * Vide le cache du provider (utile après déconnexion).
 */
export async function resetProvider() {
    if (_walletConnectProvider) {
        try {
            await _walletConnectProvider.disconnect();
        } catch (e) {
            console.warn("[Web3] Erreur de déconnexion WalletConnect:", e);
        }
    }
    _provider = null;
    _signer = null;
    _walletConnectProvider = null;
}

// Exposition minimale pour les handlers inline HTML (futur bouton
// « AJOUTER TOUS MES RÉSEAUX » dans les templates sans refactor import).
if (typeof window !== 'undefined') {
    window.ensurePingalaNetwork = ensurePingalaNetwork;
    window.addPingalaNetwork = addPingalaNetwork;
    window.PRANA_NETWORK = PRANA_NETWORK;
}
