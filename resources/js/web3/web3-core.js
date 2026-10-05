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
 * CORRECTIF MOBILE (modal WalletConnect) :
 * - Sur mobile, la modal WalletConnect legacy (`showQrModal`) liste les wallets
 *   du registre Reown Explorer filtrés par chaînes supportées. Pingala (99999)
 *   n'y est déclaré par AUCUN wallet → liste vide → spinner infini.
 * - On migre le flux WC vers AppKit (Reown) v1.8.17 (`@reown/appkit`, déjà
 *   installé). La modal AppKit affiche les wallets via deep links natifs
 *   iOS/Android (mobile_link du registre) + nos `customWallets` (voir plus bas),
 *   ce qui débloque mobile sans dépendre du registre pour le chainId 99999.
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

// ═══ APPKIT (REOWN) — CONFIG ═════════════════════════════════════════════════
// projectId depuis l'environnement Vite (aucune valeur en dur — l'ancien
// fallback "demo ID" est volontairement supprimé : sans projectId valable,
// WalletConnect ne peut pas fonctionner, autant échouer avec un message clair).
// ⚠️ DÉFENSE ANTI-CRLF : sur le VPS, `.env.staging` est en fins de ligne
// Windows (CRLF) — le parser d'env du build Vite garde un `\r` collé à la
// VALEUR (piège documenté : `projectId=…95\r` → 33 chars → toutes les API
// Reown renvoient 403 « projectId must be 32 characters » → la modal charge
// indéfiniment avec une liste vide). On strippe donc tout whitespace/CR.
const WC_PROJECT_ID = (import.meta.env.VITE_WALLETCONNECT_PROJECT_ID || '').trim();

/**
 * Réseau Pingala au format CAIP attendu par AppKit v1.8 :
 * `networks: [AppKitNetwork]` où AppKitNetwork = (choix entre)
 * - BaseNetwork  (viem `Chain` : id numérique + rpcUrls.default.http)
 * - CaipNetwork  (id numérique/string + chainNamespace + caipNetworkId)
 * CONTRAINTE VÉRIFIÉE DANS LES TYPES installés (appkit-common TypeUtil.d.ts) :
 * une chaîne custom (id NON répertoriée dans le registre Reown) est acceptée :
 * `CaipNetworksUtil.extendCaipNetwork` (appkit-utils) dérive
 * `caipNetworkId = 'eip155:99999'` automatiquement pour un objet BaseNetwork
 * simple et conserve notre rpcUrls.default.http (aucun RPC proxy Reown n'est
 * injecté : `eip155:99999` n'est pas dans WC_HTTP_RPC_SUPPORTED_CHAINS).
 * LIMITE CONNUE (lue dans ApiController.js) : la liste "recommandés/featured"
 * interroge l'Explorer API avec `chains=eip155:99999` → réponse vide puisque
 * le registre ignore cette chaîne. Les vues "recents/recommandés" peuvent donc
 * être vides ; les `customWallets` ci-dessous + les wallets injectés restent
 * TOUJOURS visibles et fonctionnels (chemin garanti depuis notre config).
 */
const APPKIT_PINGALA_NETWORK = {
    // Shape viem `Chain` (BaseNetwork) : `id` numérique + rpcUrls.default.http.
    // (AppKit dérive seul chainNamespace='eip155' et caipNetworkId='eip155:99999'
    // via CaipNetworksUtil.extendCaipNetwork pour un objet non-CAIP.)
    id: PRANA_NETWORK.chainId,
    name: PRANA_NETWORK.chainName,
    nativeCurrency: { ...PRANA_NETWORK.nativeCurrency },
    rpcUrls: { default: { http: [PRANA_NETWORK.rpcUrl] } }
    // Pas de blockExplorers (aucun explorer sur Pingala pour l'instant).
};

/**
 * Wallets déclarés LOCALEMENT (option `customWallets` d'AppKit) : le registre
 * Reown ne connaît pas Pingala (99999), donc la liste fetched serait vide sur
 * mobile. Un CustomWallet est rendu dans la vue Connect (subtype 'custom') et
 * son clic pousse la vue `ConnectingWalletConnect` → pairing WalletConnect
 * construit depuis NOS caipNetworks (Pingala 99999 + RPC). Le champ
 * `mobile_link` est formé par AppKit en deep link natif iOS/Android
 * (ConnectionControllerUtil.onConnectMobile → formatNativeUrl) : c'est LE fix
 * mobile (Core Wallet / MetaMask s'ouvrent directement depuis Safari iOS).
 *   - Core Wallet mobile : deeplink officiel `core.app.link` (avantgarde labs).
 *   - MetaMask mobile   : deeplink officiel `metamask.app.link` (référence
 *     PresetsUtil.ConnectorExplorerIds du SDK).
 * Si un user utilise un autre wallet WC, il reste la vue "All Wallets"/QR.
 */
const APPKIT_CUSTOM_WALLETS = [
    // `mobile_link` = universal link HTTPS (format lu dans CoreHelperUtil.
    // formatNativeUrl : http* → formatUniversalUrl → `https://…/wc?uri=…`,
    // le seul chemin qui déclenche l'app via universal link iOS (Safari).
    { id: 'bp-custom-corewallet', name: 'Core Wallet', homepage: 'https://core.app', image_url: window.location.origin + '/pwa_icon_192.png', mobile_link: 'https://core.app.link', desktop_link: undefined, webapp_link: undefined },
    { id: 'bp-custom-metamask', name: 'MetaMask', homepage: 'https://metamask.io', image_url: window.location.origin + '/pwa_icon_192.png', mobile_link: 'https://metamask.app.link', desktop_link: undefined, webapp_link: undefined }
];

/**
 * Métadonnées dApp affichées par le wallet lors de la demande de session.
 * (Piège conservé : /logo.png n'existe pas — utiliser l'icône PWA livrée.)
 */
const APPKIT_METADATA = {
    name: 'Battlepool',
    description: 'Battlepool Game dApp',
    url: window.location.origin,
    icons: [window.location.origin + '/pwa_icon_192.png']
};

let _provider = null;
let _signer = null;
let _walletConnectProvider = null; // provider EIP-1193 actif du chemin WC (AppKit universal-provider OU legacy)
let _activeWcSource = null;        // 'appkit' | 'legacy' — pour un reset propre
let _appKit = null;
let _appKitInitPromise = null;
let _universalProvider = null;     // UniversalProvider initié PAR NOUS et passé à AppKit (référence EIP-1193 directe)
let _lastWcUri = null;             // URI de pairing WC — émise par le hook standard `display_uri`

// ═══ DEEP LINKS NATIFS MOBILE (iPhone Safari / Android) ══════════════════════
// POURQUOI (mesuré en E2E sur le domaine de production, 2026-10-05) :
// au clic mobile, AppKit route la vue « All Wallets » (registre Explorer) et
// les `customWallets` ne sont JAMAIS rendus dans cette vue. Le registre est
// filtré par `supports_wc` (Core Wallet : champ vide) ET par les chaînes
// (`chains=eip155:99999` — inconnue de tous) → Core n'apparaîtra jamais dans
// la modal, quelle que soit notre config. La seule voie fiable = ouvrir le
// wallet DIRECTEMENT via son universal link, avec l'URI de pairing.
// Le hook standard : `provider.on('display_uri', uri => …)` — le SDK émet
// l'URI exactly pour ce cas (cf. universal-provider : this.events.emit
// ("display_uri", uri) après le pairing).
// Core Wallet    : universal `https://core.app` (+ /wc?uri=<uri>) ; natif `core://`
// MetaMask mobile: universal `https://metamask.app.link` ; natif `metamask://`
const WALLET_DEEPLINKS = {
    core: {
        name: 'Core Wallet',
        universal: 'https://core.app',
        native: 'core://',
    },
    metamask: {
        name: 'MetaMask',
        universal: 'https://metamask.app.link',
        native: 'metamask://',
    },
};

/** Détection mobile SANS dépendance aux helpers AppKit (UA + touch). */
function _isMobileDevice() {
    const ua = navigator.userAgent || '';
    const touch = (navigator.maxTouchPoints || 0) > 1;
    return /Android|iPhone|iPad|iPod|IEMobile|Opera Mini/i.test(ua) || touch;
}

/** URL de deep link pour un wallet donné.
 * FORMAT MESURÉ (rétroaction iPhone 2026-10-05) : `https://core.app/wc?uri=…`
 * sert une PAGE WEB (200) « page non trouvée + bouton Ouvrir » = une étape
 * supplémentaire avant l'app — pas acceptable. Le format réel du SDK (cf.
 * `CoreHelperUtil.formatNativeUrl`) forme `core://wc?uri=<encoded>` : Safari
 * iOS affiche son bandeau « Ouvrir dans Core ? » DIRECTEMENT = zéro étape web.
 * On construit donc le **scheme natif**, pas un chemin web deviné. */
function _buildDeeplink(walletKey, uri) {
    const d = WALLET_DEEPLINKS[walletKey];
    return `${d.native}wc?uri=${encodeURIComponent(uri)}`;
}

/**
 * Erreur sémantique : l'utilisateur a fermé la modal sans se connecter.
 * (Ne DOIT PAS déclencher le fallback legacy — l'UI est déjà affichée.)
 */
class WalletConnectionClosedError extends Error {
    constructor() {
        super('Modal fermée sans connexion (annulé par l\'utilisateur)');
        this.name = 'WalletConnectionClosedError';
        this.code = 'MODAL_CLOSED';
    }
}

/**
 * Lazy-init : crée l'UniversalProvider (@walletconnect/universal-provider)
 * NOUS-MÊMES puis l'injecte via createAppKit({ universalProvider }).
 * Option documentée dans les types installés (TypesUtil.d.ts) :
 *   "universalProvider?: UniversalProvider — AppKit will generate its own
 *    instance by default if none provided".
 * Pourquoi ne pas laisser AppKit le créer en interne ? Parce que la session
 * WC est posée sur provider.session AVANT le close() de la modal
 * (appkit-base-client.js : onConnect → finalizeWcConnection), alors que
 * syncWalletConnectAccount (qui alimente ProviderController) tourne APRES le
 * close() — un getProvider('eip155') pris entre les deux retournerait
 * undefined. En gardant la référence, l'état de connexion est lu sur
 * provider.session de façon déterministe, sans course.
 * IMPORT DYNAMIQUE des deux modules → chunks séparés à la demande.
 * @returns {Promise<object>} instance AppKit prête à open()
 */
async function _getAppKitWithProvider() {
    if (_appKit && _universalProvider) return { appKit: _appKit, provider: _universalProvider };
    if (!_appKitInitPromise) {
        _appKitInitPromise = (async () => {
            if (!WC_PROJECT_ID) {
                throw new Error('WalletConnect ProjectId manquant : définissez VITE_WALLETCONNECT_PROJECT_ID au build.');
            }
            const [{ createAppKit }, { UniversalProvider }] = await Promise.all([
                import('@reown/appkit'),
                import('@walletconnect/universal-provider')
            ]);
            // adapters non fourni → AppKit instancie son UniversalAdapter
            // (noAdapters=true) ; avec la référence au provider, le pairing WC
            // et l'état EIP-1193 restent sous notre contrôle.
            _universalProvider = (await UniversalProvider.init({
                projectId: WC_PROJECT_ID,
                metadata: APPKIT_METADATA
            }));
            const appKit = createAppKit({
                projectId: WC_PROJECT_ID,
                networks: [APPKIT_PINGALA_NETWORK],
                metadata: APPKIT_METADATA,
                themeMode: 'dark',
                // customWallets : seuls affichés GARANTIS sur mobile pour une
                // chaîne absente du registre (cf. commentaire APPKIT_NETWORK).
                customWallets: APPKIT_CUSTOM_WALLETS,
                features: { analytics: false },
                // Référence imposée à AppKit (voir doc du bloc ci-dessus).
                universalProvider: _universalProvider
            });
            _appKit = appKit;
            return { appKit, provider: _universalProvider };
        })();
    }
    try {
        return await _appKitInitPromise;
    } catch (e) {
        _appKitInitPromise = null; // permettre un retry au clic suivant
        _appKit = null;
        _universalProvider = null;
        throw e;
    }
}

/**
 * Extrait l'adresse EVM connectée depuis la session WalletConnect
 * (provider.session.namespaces.eip155.accounts = ['eip155:<chainId>:<addr>']).
 * Déterministe : la session est posée AVANT le close() de la modal AppKit.
 * @param {object|null} universalProvider
 * @returns {string|null} adresse 0x… ou null si non connecté
 */
function _getSessionAddress(universalProvider) {
    try {
        const accounts = universalProvider?.session?.namespaces?.eip155?.accounts || [];
        const addr = accounts.map(a => a.split(':')[2]).find(Boolean);
        return addr || null;
    } catch (e) {
        return null;
    }
}

/**
 * Attend que la session WalletConnect soit validée.
 * @param {object|null} appKit instance AppKit (null pour le flux mobile sans
 *        modal : on poll uniquement la session du provider)
 * @param {object} universalProvider notre référence EIP-1193
 * @param {number} timeoutMs garde-fou (défaut 10 min)
 * @returns {Promise<string>} l'adresse connectée
 */
async function _waitForAppKitConnection(appKit, universalProvider, timeoutMs = 10 * 60 * 1000) {
    // Session déjà rétablie (reconnect AppKit au chargement, enableReconnect) :
    // ne pas rouvrir la modal, rendre le provider.
    const existing = _getSessionAddress(universalProvider);
    if (existing) {
        return existing;
    }

    // Desktop modal : open() est async (injection dynamique de la modal UI,
    // plus longue sur mobile) — l'attendre AVANT le premier poll, sinon
    // isOpen() peut encore valoir false et provoquer un faux "MODAL_CLOSED".
    if (appKit) {
        await appKit.open();
    }
    const start = Date.now();
    return new Promise((resolve, reject) => {
        const poll = () => {
            const now = Date.now();
            if (now - start > timeoutMs) {
                if (appKit) { try { appKit.close(); } catch (e) { /* noop */ } }
                reject(new Error('Délai de connexion WalletConnect dépassé.'));
                return;
            }
            // La session WC est posée sur provider.session dès l'approbation
            // du wallet (avant close() de la modal) → lecture déterministe.
            const addr = _getSessionAddress(universalProvider);
            if (addr) {
                resolve(addr);
                return;
            }
            // Modal fermée par l'utilisateur sans connexion → annulation.
            // (flux desktop uniquement — le flux mobile n'a pas de modal)
            if (appKit) {
                let open = false;
                try { open = Boolean(appKit.isOpen()); } catch (e) { /* noop */ }
                if (!open) {
                    reject(new WalletConnectionClosedError());
                    return;
                }
            }
            setTimeout(poll, 400);
        };
        setTimeout(poll, 400);
    });
}

/**
 * Namespaces de session WalletConnect — la shape EXACTE que les wallets
 * exigent (Core Wallet rejetait la session « network not specified » avec un
 * connect({}) vide : sans `chains`/`methods`, la proposition ne décrit aucun
 * réseau). Shape copiée de AppKit (WcHelpersUtil.createNamespaces +
 * createDefaultNamespace + DEFAULT_METHODS.eip155) :
 *   { eip155: { chains: ['eip155:99999'], methods: […], events: […],
 *               rpcMap: { 99999: <rpc> } } }
 */
const WC_EIP155_METHODS = [
    'eth_accounts',
    'eth_requestAccounts',
    'eth_sendRawTransaction',
    'eth_sign',
    'eth_signTransaction',
    'eth_signTypedData',
    'eth_signTypedData_v3',
    'eth_signTypedData_v4',
    'eth_sendTransaction',
    'personal_sign',
    'wallet_switchEthereumChain',
    'wallet_addEthereumChain',
    'wallet_getPermissions',
    'wallet_requestPermissions',
    'wallet_registerOnboarding',
    'wallet_watchAsset',
    'wallet_scanQRCode',
];
const WC_EIP155_EVENTS = ['accountsChanged', 'chainChanged'];
const WC_NAMESPACES = {
    eip155: {
        chains: [`eip155:${PRANA_NETWORK.chainId}`],
        methods: WC_EIP155_METHODS,
        events: WC_EIP155_EVENTS,
        rpcMap: { [PRANA_NETWORK.chainId]: PRANA_NETWORK.rpcUrl },
    },
};

/**
 * FLUX MOBILE (iPhone Safari / Android Chrome) — le fix qui marche :
 * 1. init AppKit + UniversalProvider (SANS ouvrir la modal),
 * 2. `provider.connect({…})` = démarre le pairing WC ;
 *    le SDK émet `display_uri` avec l'URI de pairing,
 * 3. on redirige IMMÉDIATEMENT vers Core Wallet via son universal link
 *    (`https://core.app/wc?uri=…`) → l'app Core s'ouvre, l'utilisateur
 *    approuve, la session WC est établie côté page,
 * 4. le poll lit provider.session (déterministe).
 *
 * Pourquoi pas la modal AppKit sur mobile ? (mesuré : la modal route la vue
 * « All Wallets » = registre Explorer filtré par supports_wc/chaînes — Core
 * n'y apparaît JAMAIS car le registre ignore Pingala 99999 et le champ
 * supports_wc de Core est vide. Voir le commentaire WALLET_DEEPLINKS.)
 *
 * @param {string} [walletKey='core'] 'core' | 'metamask' (deep link cible)
 * @returns {Promise<BrowserProvider>}
 */
export async function connectWalletMobile(walletKey = 'core') {
    const { provider } = await _getAppKitWithProvider();
    if (!provider || typeof provider.connect !== 'function') {
        throw new Error('Provider WalletConnect indisponible (init échouée).');
    }

    // Hook standard du SDK : l'URI de pairing arrive via display_uri.
    const uriPromise = new Promise((resolve) => {
        const handler = (uri) => {
            if (uri) {
                _lastWcUri = uri;
                try { provider.events?.once?.('disconnect', () => {}); } catch (e) { /* noop */ }
                resolve(uri);
            }
        };
        try { provider.on('display_uri', handler); } catch (e) {
            // certains builds : l'event est déjà consommé par AppKit → fallback poll
        }
        // Filet : certains builds émettent AVANT qu'on s'abonne — poll l'URI.
        const t0 = Date.now();
        const pollUri = () => {
            if (_lastWcUri) { resolve(_lastWcUri); return; }
            if (Date.now() - t0 > 15000) { resolve(null); return; }
            setTimeout(pollUri, 250);
        };
        setTimeout(pollUri, 300);
    });

    // Démarre le pairing AVEC les namespaces déclarés — sinon le wallet
    // reçoit une proposition sans réseau et lève « network not specified »
    // (toast « Connection failed » dans Core, mesuré sur iPhone 2026-10-05).
    const connectPromise = provider.connect({ optionalNamespaces: WC_NAMESPACES }).catch((e) => {
        console.warn('[Web3] provider.connect (mobile) erreur:', e?.message || e);
    });

    const uri = await uriPromise;
    const target = WALLET_DEEPLINKS[walletKey] || WALLET_DEEPLINKS.core;

    if (uri) {
        // Scheme NATIF (core://wc?uri=…) : Safari iOS affiche « Ouvrir dans
        // Core ? » IMMÉDIATEMENT (zéro page intermédiaire). L'universal link
        // https://core.app/wc?uri= sert lui une page web 404+bouton (mesuré).
        let link = _buildDeeplink(walletKey, uri);
        console.log(`[Web3] Deep link ${target.name} (${link.slice(0, 40)}…)`);
        // Fallback : certains navigateurs bloquent schemes custom en direct —
        // on retente via l'universal link si rien ne se passe (détecté par
        // visibilitychange : aucune perte de focus = scheme ignoré).
        let opened = false;
        const onHide = () => { opened = true; };
        document.addEventListener('visibilitychange', onHide, { once: true });
        window.location.href = link;
        setTimeout(() => {
            document.removeEventListener('visibilitychange', onHide);
            if (!opened) {
                const uni = `${WALLET_DEEPLINKS[walletKey].universal}/wc?uri=${encodeURIComponent(uri)}`;
                console.warn('[Web3] scheme natif ignoré — fallback universal link:', uni.slice(0, 50));
                window.location.href = uni;
            }
        }, 1800);
    } else {
        // Pas d'URI (pairing lento/échoué) : on tombe sur la modal standard.
        console.warn('[Web3] URI WC non reçue — fallback modal AppKit.');
    }

    // La modal reste disponible en secours si le retour au navigateur ne
    // déclenche pas la session : on garde le poll de session en arrière-plan.
    await _waitForAppKitConnection(null /** pas de modal — poll session seulement*/, provider);

    _activeWcSource = 'appkit';
    _walletConnectProvider = provider;
    try { await addPingalaNetwork(provider); } catch (e) {
        if (e && e.code !== 4001) console.warn('[Web3] Réseau Pingala non activé :', e);
    }
    _provider = new BrowserProvider(provider);
    return _provider;
}

/**
 * Flux WalletConnect via AppKit (DESKTOP : modal + QR ; MOBILE : deeplink direct).
 * Routage par appareil :
 *   - mobile  → connectWalletMobile() (l'app Core/MetaMask s'ouvre, la modal
 *               All Wallets du registre — qui n'a JAMAIS Core — est évitée),
 *   - desktop → modal AppKit + QR (Core Wallet extension via le chemin injecté).
 * Provider rendu = NOTRE UniversalProvider (EIP-1193) wrappé par ethers v6.
 * @returns {Promise<BrowserProvider>}
 */
export async function connectWalletViaAppKit() {
    if (_isMobileDevice()) {
        return connectWalletMobile('core');
    }
    return _connectWalletViaAppKitDesktop();
}

/** Chemin desktop (modal + QR), inchangé du flux AppKit. */
async function _connectWalletViaAppKitDesktop() {
    const { appKit, provider } = await _getAppKitWithProvider();
    await _waitForAppKitConnection(appKit, provider);

    if (!provider || typeof provider.request !== 'function') {
        throw new Error('AppKit connecté mais provider EIP-1193 indisponible.');
    }

    _activeWcSource = 'appkit';
    _walletConnectProvider = provider;

    // Switch/ajout programmatique vers Pingala Chain (99999) côté wallet :
    // même garde que le flux historique. Non bloquant en cas de refus (4001) :
    // ensurePingalaNetwork() est rappelé avant chaque flux de transaction.
    try {
        await addPingalaNetwork(provider);
    } catch (e) {
        if (e && e.code !== 4001) {
            console.warn('[Web3] Réseau Pingala non activé côté WalletConnect :', e);
        }
    }

    _provider = new BrowserProvider(provider);
    return _provider;
}

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
        // CHEMIN PRIMAIRE : AppKit (Reown) — modal multi-wallets avec deep
        // links natifs iOS/Android ; corrige le spinner infini mobile (le
        // registre Reown ignore Pingala 99999, la modal legacy restait vide).
        try {
            return await connectWalletViaAppKit();
        } catch (appKitError) {
            // MODAL_CLOSED = choix utilisateur (fermeture/annulation) → NE PAS
            // retomber sur la legacy (elle re-ouvrirait une 2nde modal) :
            // propager pour un toast propre dans auth.js.
            if (appKitError && (appKitError.code === 'MODAL_CLOSED' || appKitError instanceof WalletConnectionClosedError)) {
                throw appKitError;
            }
            // Autre erreur : init AppKit impossible (import bloqué, projectId
            // absent) → FALLBACK legacy conservé pour ne pas régresser desktop.
            console.warn('[Web3] AppKit indisponible, fallback WalletConnect legacy :', appKitError);
        }

        // ═══ FALLBACK LEGACY (conservé) ════════════════════════════════════
        if (!_walletConnectProvider) {
            console.log(`[Web3] Initialisation de WalletConnect (legacy) avec RPC Pingala: ${PRANA_NETWORK.rpcUrl}`);

            _walletConnectProvider = await EthereumProvider.init({
                projectId: WC_PROJECT_ID,
                // La chaîne REQUISE au pairing est la chaîne de jeu (Pingala
                // 99999). Avec une chaîne « connue » (ex. mainnet), la session
                // ne mappe pas 99999 ; cette voie legacy reste néanmoins le
                // dernier recours (desktop), le registre Explorer retournant
                // une liste vide sur mobile pour 99999.
                chains: [PRANA_NETWORK.chainId],
                rpcMap: {
                    [PRANA_NETWORK.chainId]: PRANA_NETWORK.rpcUrl
                },
                showQrModal: true,
                qrModalOptions: {
                    themeMode: 'dark'
                },
                metadata: APPKIT_METADATA
            });
            _activeWcSource = 'legacy';
        }

        // Active la session de connexion
        await _walletConnectProvider.connect();

        // Switch/ajout programmatique vers Pingala Chain (99999)
        try {
            await addPingalaNetwork(_walletConnectProvider);
        } catch (e) {
            // Refus utilisateur ou échec : non bloquant, ensurePingalaNetwork
            // sera rappelé avant chaque flux de transaction.
            if (e && e.code !== 4001) {
                console.warn('[Web3] Réseau Pingala non activé côté WalletConnect :', e);
            }
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
    if (_walletConnectProvider && _activeWcSource === 'appkit') {
        // Le provider WC actif EST notre UniversalProvider (injecté à AppKit).
        // provider.disconnect() ferme la session WalletConnect réelle (delete
        // session côté relais) — la même voie que AppKit.disconnect() utilise
        // en interne (WalletConnectConnector.disconnect → provider.disconnect()).
        // NB : "Record was recently deleted" = session déjà fermée → non bloquant.
        try {
            await _walletConnectProvider.disconnect();
        } catch (e) {
            console.warn("[Web3] Erreur de déconnexion WalletConnect (AppKit):", e);
        }
        // Remise à zéro de l'état AppKit (accounts/provider affichés dans la
        // modal) pour que la prochaine connexion reparte d'un état propre.
        try {
            if (_appKit && typeof _appKit.disconnect === 'function' &&
                _appKit.getAccount?.('eip155')?.isConnected) {
                await _appKit.disconnect();
            }
        } catch (e) {
            console.warn("[Web3] Erreur de reset d'état AppKit:", e);
        }
    } else if (_walletConnectProvider) {
        try {
            await _walletConnectProvider.disconnect();
        } catch (e) {
            // session déjà fermée → non bloquant
        }
    }
    _provider = null;
    _signer = null;
    _walletConnectProvider = null;
    _activeWcSource = null;
    // L'UniversalProvider reste lié à _appKit (option universalProvider) :
    // on ne le nulle pas, seule sa session est fermée (reset() interne).
}

// Exposition minimale pour les handlers inline HTML (futur bouton
// « AJOUTER TOUS MES RÉSEAUX » dans les templates sans refactor import).
if (typeof window !== 'undefined') {
    window.ensurePingalaNetwork = ensurePingalaNetwork;
    window.addPingalaNetwork = addPingalaNetwork;
    window.PRANA_NETWORK = PRANA_NETWORK;
}
