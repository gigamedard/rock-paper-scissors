// Test E2E Puppeteer : flux d'authentification complet (mock MetaMask + signature réelle)
// Utilise le compte Hardhat #1 (0x70997970...) pour ne pas toucher à l'admin #0.
import puppeteer from 'puppeteer-core';
import { Wallet } from 'ethers';

const FRONTEND_URL = 'http://127.0.0.1:8001/';
const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

// Compte Hardhat #1 (réservé aux tests utilisateur)
const TEST_PK = '***REMOVED***';
const TEST_ADDRESS = '0x70997970C51812dc3A010C7d01b50e0d17dc79C8';

const wallet = new Wallet(TEST_PK);

function log(step, ok, detail = '') {
    const mark = ok ? '✅' : '❌';
    console.log(`${mark} ${step}${detail ? ' — ' + detail : ''}`);
}

(async () => {
    console.log('🚀 Test E2E Puppeteer — Authentification (mock MetaMask + signature réelle)\n');

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: CHROME_PATH,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });
    const page = await browser.newPage();

    // Collecter les erreurs console + requêtes échouées
    const consoleErrors = [];
    page.on('console', (msg) => {
        if (msg.type() === 'error') consoleErrors.push(msg.text());
    });
    page.on('pageerror', (err) => consoleErrors.push('PAGEERROR: ' + err.message));

    // 1. Injecter le mock MetaMask + pré-définir la langue AVANT le chargement
    // (sinon l'overlay de sélection de langue bloque initI18n() et le handler du bouton n'est jamais attaché)
    await page.evaluateOnNewDocument((address) => {
        try { localStorage.setItem('user_locale', 'en'); } catch (e) {}
        window.ethereum = {
            isMetaMask: true,
            on: () => {}, // no-op pour les listeners d'événements MetaMask
            removeListener: () => {},
            request: async ({ method, params }) => {
                if (method === 'eth_requestAccounts' || method === 'eth_accounts') {
                    return [address];
                }
                if (method === 'personal_sign' || method === 'eth_sign') {
                    // ethers v6 signMessage() envoie personal_sign avec [hexMessage, address]
                    // On passe le hexMessage brut à la fonction exposée (décodage côté Node).
                    let hexMessage;
                    if (method === 'eth_sign') {
                        hexMessage = params[1]; // [address, hexMessage]
                    } else {
                        hexMessage = params[0]; // [hexMessage, address]
                    }
                    return await window.__signMessage(hexMessage);
                }
                if (method === 'eth_chainId') {
                    return '0x539'; // 1337
                }
                if (method === 'net_version') {
                    return '1337';
                }
                return null;
            },
        };
    }, TEST_ADDRESS);

    // Exposer la fonction de signature réelle (côté Node, avec la PK)
    await page.exposeFunction('__signMessage', async (hexMessage) => {
        // Décoder l'hex en UTF-8 (côté Node, où Buffer existe)
        const msg = hexMessage.startsWith('0x')
            ? Buffer.from(hexMessage.slice(2), 'hex').toString('utf8')
            : hexMessage;
        return await wallet.signMessage(msg);
    });

    // 2. Naviguer vers le frontend
    await page.goto(FRONTEND_URL, { waitUntil: 'networkidle2', timeout: 30000 });
    log('Chargement de la page', true);

    // 3. Vérifier la présence du bouton de connexion
    const connectBtn = await page.$('#connect-btn');
    log('Bouton MetaMask présent', !!connectBtn);

    // 4. Cliquer sur le bouton de connexion
    await page.click('#connect-btn');
    log('Clic sur le bouton MetaMask', true);

    // 5. Attendre que le wallet soit connecté (badge visible)
    try {
        await page.waitForFunction(
            () => {
                const el = document.getElementById('connected-user');
                return el && el.style.display !== 'none';
            },
            { timeout: 15000 }
        );
        log('Wallet connecté (badge visible)', true);
    } catch (e) {
        log('Wallet connecté (badge visible)', false, 'timeout 15s');
    }

    // 6. Vérifier l'adresse affichée
    const walletText = await page.evaluate(() => {
        const el = document.getElementById('user-wallet');
        return el ? el.textContent.trim() : null;
    });
    log('Adresse affichée', !!walletText, walletText || 'null');
    if (walletText) {
        // L'adresse est tronquée (0x7099...79c8) : on compare le préfixe
        const normalized = walletText.toLowerCase();
        const expectedPrefix = TEST_ADDRESS.toLowerCase().slice(0, 6);
        log('Adresse correspond au compte #1', normalized.startsWith(expectedPrefix), walletText);
    }

    // 7. Vérifier le localStorage (token stocké)
    const authToken = await page.evaluate(() => localStorage.getItem('auth_token'));
    log('Token stocké en localStorage', !!authToken, authToken ? 'présent' : 'absent');

    // 8. Vérifier l'état utilisateur (userState)
    const userState = await page.evaluate(() => window.userState ? JSON.stringify({ id: window.userState.id, status: window.userState.status }) : null);
    log('userState initialisé', !!userState, userState || 'null');

    // 9. Récapitulatif des erreurs console
    console.log('\n=== Erreurs console ===');
    if (consoleErrors.length === 0) {
        console.log('✅ Aucune erreur console');
    } else {
        consoleErrors.slice(0, 10).forEach((e) => console.log('  ❌', e));
    }

    await browser.close();
    console.log('\n🏁 Test terminé.');
})().catch((e) => {
    console.error('❌ FATAL:', e.message);
    process.exit(1);
});
