// resources/js/modules/onboarding-core.js
// ============================================================================
// BATTLEPOOL — ONBOARDING CORE WALLET (chain perso Pingala)
// ============================================================================
// Aide l'utilisateur à ajouter Pingala Chain (chainId 99999) à Core Wallet
// manuellement : QR + 4 champs copier/coller + instructions visuelles.
// Problème visé (journal 2026-10-06) : Core n'accepte pas une session WC
// pour eip155:99999 car la chaîne n'est pas connue de son registre interne —
// l'ajout réseau PRE-ALABLE dans Core (manual) débloque l'auth WC.
// ============================================================================

const QR_DATA = {
    chainId: '0x1869f',       // 99999 décimal
    chainIdDec: '99999',
    chainName: 'Pingala Chain',
    rpcUrl: 'https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc',
    rpcUrls: ['https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc'],
    symbol: 'TST',
    nativeCurrency: { name: 'TST', symbol: 'TST', decimals: 18 },
    blockExplorerUrls: null,  // Pingala n'a pas d'explorer public
};

/**
 * Construit la chaîne EIP-3085 pour ajouter la chaîne à Core Wallet.
 * Format reconnu par l'UI Add Network des wallets modernes (EIP-3085) :
 *   { chainId, chainName, rpcUrls, nativeCurrency, blockExplorerUrls }
 */
function buildAddNetworkParams() {
    return QR_DATA; // objet complet
}

function getQRDataUrl() {
    // Format EIP-3085 complet — sera scanné par Core pour pré-remplir.
    const payload = JSON.stringify(buildAddNetworkParams());
    return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=8&qzone=2&data=' + encodeURIComponent(payload);
}

/**
 * Copie un texte dans le presse-papiers (navigator.clipboard API, avec
 * fallback execCommand pour Safari iOS anciens).
 * @returns {Promise<boolean>} true si succès
 */
async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (e) {
        // fallback iOS : execCommand sur un <textarea> éphémère
        try {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
            document.body.appendChild(ta);
            ta.focus(); ta.select();
            const ok = document.execCommand('copy');
            ta.remove();
            return ok;
        } catch (_) { return false; }
    }
}

/**
 * Lance l'ajout via Core Wallet si le wallet injecté est présent (extension
 * Core sur desktop, ou Core in-app browser sur mobile).
 * @returns {Promise<boolean>} true si succès
 */
async function tryAutoAdd() {
    if (typeof window.ethereum === 'undefined') return false;
    try {
        await window.ethereum.request({
            method: 'wallet_addEthereumChain',
            params: [buildAddNetworkParams()],
        });
        return true;
    } catch (e) {
        return false;
    }
}

/**
 * Initialise les handlers de la page onboarding (#/en-rolement). À appeler
 * depuis app.js (init).
 */
export function initOnboardingCore() {
    const page = document.getElementById('onboarding-core-page');
    if (!page) return;

    // QR code : utilise API externe gratuite (api.qrserver.com) — pas d'install.
    const qrImg = page.querySelector('[data-onboarding-qr]');
    if (qrImg && !qrImg.getAttribute('src')) {
        qrImg.src = getQRDataUrl();
        qrImg.alt = 'WalletConnect pingala-chain QR code';
    }

    // Boutons copier pour chaque champ
    page.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const key = btn.getAttribute('data-copy');
            const text = QR_DATA[key];
            if (text == null) return;
            const ok = await copyText(text);
            const orig = btn.dataset.origLabel || btn.textContent;
            if (!btn.dataset.origLabel) btn.dataset.origLabel = orig;
            btn.textContent = ok ? '✅' : '❌';
            setTimeout(() => { btn.textContent = btn.dataset.origLabel; }, 1500);
        });
    });

    // Bouton "ajouter via Core" (auto via extension/in-app)
    const autoBtn = page.querySelector('[data-onboarding-auto]');
    if (autoBtn) {
        autoBtn.addEventListener('click', async () => {
            const ok = await tryAutoAdd();
            if (ok) {
                window._bpDebugLogHook?.('BOOT', 'Onboarding: auto-add OK');
                window.location.hash = '#/';
            } else {
                window._bpDebugLogHook?.('BOOT', 'Onboarding: auto-add échoué → manual');
            }
        });
    }

    // Bouton "j'ai fini → continu"
    const doneBtn = page.querySelector('[data-onboarding-done]');
    if (doneBtn) {
        doneBtn.addEventListener('click', () => {
            window.location.hash = '#/';
        });
    }
}

// Export des valeurs pour usage externe (ex: lien QR direct, autre module)
export { QR_DATA, getQRDataUrl, copyText, tryAutoAdd };