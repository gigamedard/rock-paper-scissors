// resources/js/web3/web3-core.js
/**
 * Couche d'abstraction Web3.
 * - Utilise Ethers.js v6 en priorité (moderne et léger)
 * - Garde Web3.js comme fallback pour les cas limites (ex: méthodes spécifiques)
 */
import { BrowserProvider, Contract } from 'ethers';

let _provider = null;
let _signer = null;

/**
 * Initialise et retourne le provider Ethers.js.
 * Supporte MetaMask, Core Wallet (EIP-6963), et autres injected providers.
 */
export async function getProvider() {
    if (_provider) return _provider;

    if (typeof window.ethereum === 'undefined') {
        throw new Error("Aucun provider Web3 détecté. Veuillez installer MetaMask ou Core Wallet.");
    }

    _provider = new BrowserProvider(window.ethereum);
    return _provider;
}

/**
 * Retourne le signer connecté (utilisateur actif).
 */
export async function getSigner() {
    if (_signer) return _signer;
    const provider = await getProvider();
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
export function resetProvider() {
    _provider = null;
    _signer = null;
}
