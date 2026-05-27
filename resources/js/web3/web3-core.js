// resources/js/web3/web3-core.js
/**
 * Couche d'abstraction Web3.
 * - Utilise Ethers.js v6 en priorité (moderne et léger)
 * - Garde Web3.js comme fallback pour les cas limites (ex: méthodes spécifiques)
 */
import { BrowserProvider, Contract } from 'ethers';
import { EthereumProvider } from '@walletconnect/ethereum-provider';

let _provider = null;
let _signer = null;
let _walletConnectProvider = null;

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
            const currentHost = window.location.hostname;
            const rpcPort = '8545';
            const rpcUrl = `http://${currentHost}:${rpcPort}`;

            console.log(`[Web3] Initialisation de WalletConnect avec RPC: ${rpcUrl}`);
            
            _walletConnectProvider = await EthereumProvider.init({
                projectId: projectId,
                chains: [1], // Mainnet standard requis pour que MetaMask accepte la connexion initiale
                optionalChains: [1337], // Hardhat facultatif (on switchera après)
                showQrModal: true,
                qrModalOptions: {
                    themeMode: 'dark'
                },
                rpcMap: {
                    1: 'https://cloudflare-eth.com',
                    1337: rpcUrl
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

        // Switch programmatiquement vers Hardhat (1337)
        try {
            console.log("[Web3] Demande de switch vers la chaîne Hardhat (1337)...");
            await _walletConnectProvider.request({
                method: 'wallet_switchEthereumChain',
                params: [{ chainId: '0x539' }] // 1337 en hexadécimal
            });
        } catch (switchError) {
            // Code 4902 indique que la chaîne n'a pas encore été ajoutée au portefeuille
            if (switchError.code === 4902 || (switchError.message && switchError.message.includes("Unrecognized chain ID"))) {
                try {
                    console.log("[Web3] Chaîne non reconnue. Tentative d'ajout du réseau Hardhat...");
                    await _walletConnectProvider.request({
                        method: 'wallet_addEthereumChain',
                        params: [{
                            chainId: '0x539',
                            chainName: 'Hardhat Tailscale',
                            nativeCurrency: { name: 'ETH', symbol: 'ETH', decimals: 18 },
                            rpcUrls: [rpcUrl]
                        }]
                    });
                } catch (addError) {
                    console.error("[Web3] Échec de l'ajout de la chaîne Hardhat :", addError);
                }
            } else {
                console.error("[Web3] Échec du switch vers Hardhat :", switchError);
            }
        }

        _provider = new BrowserProvider(_walletConnectProvider);
        return _provider;
    }

    // Comportement standard injecté (MetaMask)
    if (typeof window.ethereum === 'undefined') {
        throw new Error("Aucun provider Web3 détecté. Veuillez installer MetaMask ou utiliser WalletConnect.");
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

