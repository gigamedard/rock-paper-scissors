// ============================================================================
// TEST E2E DIAGNOSTIC — MARKETPLACE APP 1 (Battlepool)
// Navigation directe dans index.html (pas le portail iframe)
// ============================================================================

const puppeteer = require('puppeteer-core');

const APP_URL = 'http://localhost:8001';
const CHROME_PATH = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const HARDHAT_ACCOUNTS = {
    1: { address: '0x70997970C51812dc3A010C7d01b50e0d17dc79C8', pk: '***REMOVED***' },
};

(async () => {
    console.log('=== DIAGNOSTIC MARKETPLACE (index.html direct) ===\n');

    const browser = await puppeteer.launch({
        executablePath: CHROME_PATH,
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security']
    });

    const page = await browser.newPage();

    // Collecter TOUS les logs
    const allLogs = [];
    page.on('console', msg => {
        const text = msg.text();
        allLogs.push({ type: msg.type(), text });
        if (/Marketplace|marketplace|approve|Approuver|Erreur|error|Wallet|connect|session|SPA|init|artefacts|DOM/i.test(text)) {
            console.log(`  [${msg.type}] ${text}`);
        }
    });

    // Injecter mock ethereum + session AVANT navigation
    await page.evaluateOnNewDocument((account) => {
        window.__MOCK_ACCOUNT = account;
        window.ethereum = {
            isMetaMask: true,
            request: async ({ method, params }) => {
                if (method === 'eth_requestAccounts') return [account.address];
                if (method === 'personal_sign') {
                    // On ne peut pas importer ethers dans evaluateOnNewDocument
                    // On va pré-signer un message connu
                    return '0x0000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000';
                }
                if (method === 'eth_chainId') return '0x7A69';
                throw new Error(`Mock: ${method}`);
            },
            on: () => {},
            removeListener: () => {},
        };
    }, HARDHAT_ACCOUNTS[1]);

    // Pré-remplir le localStorage avec une session valide
    await page.goto(APP_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.evaluate((account) => {
        const user = {
            id: 2,
            wallet_address: account.address,
            name: 'Test User',
            email: 'test@test.com',
            is_admin: false,
            balance: 100,
            tokens: 1000,
        };
        localStorage.setItem('user', JSON.stringify(user));
        localStorage.setItem('auth_token', 'test-token-12345');
        localStorage.setItem('user_locale', 'fr');
    }, HARDHAT_ACCOUNTS[1]);

    // Naviguer vers index.html
    console.log('[1] Navigation vers index.html...');
    await page.goto(`${APP_URL}/index.html`, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await new Promise(r => setTimeout(r, 3000));

    // Vérifier les logs marketplace
    const mpInitLogs = allLogs.filter(l => /Marketplace.*Initialisation/.test(l.text));
    const mpDomLogs = allLogs.filter(l => /Marketplace.*DOM elements/.test(l.text));
    const mpAddrLogs = allLogs.filter(l => /Marketplace.*Adresses/.test(l.text));
    console.log(`\n[2] Logs marketplace:`);
    console.log(`  initMarketplace: ${mpInitLogs.length > 0 ? '✅' : '❌'}`);
    console.log(`  DOM elements: ${mpDomLogs.length > 0 ? '✅' : '❌'}`);
    console.log(`  Adresses chargées: ${mpAddrLogs.length > 0 ? '✅' : '❌'}`);

    // Vérifier l'état des éléments marketplace (ils sont dans le DOM mais cachés)
    console.log('\n[3] État des éléments marketplace (dans le DOM, page autoplay active):');
    const mpDomState = await page.evaluate(() => {
        const approveBtn = document.getElementById('marketplace-approve-btn');
        const createBtn = document.getElementById('marketplace-create-btn');
        const sntInput = document.getElementById('mp-snt-amount');
        const avaxInput = document.getElementById('mp-avax-amount');
        const mpPage = document.getElementById('marketplace-page');
        return {
            approveBtn: approveBtn ? { disabled: approveBtn.disabled, display: getComputedStyle(approveBtn).display, onclick: !!approveBtn.onclick } : 'NOT IN DOM',
            createBtn: createBtn ? { disabled: createBtn.disabled, display: getComputedStyle(createBtn).display, onclick: !!createBtn.onclick } : 'NOT IN DOM',
            sntInput: sntInput ? { display: getComputedStyle(sntInput).display } : 'NOT IN DOM',
            avaxInput: avaxInput ? { display: getComputedStyle(avaxInput).display } : 'NOT IN DOM',
            mpPage: mpPage ? { display: getComputedStyle(mpPage).display, active: mpPage.classList.contains('active') } : 'NOT IN DOM',
        };
    });
    console.log(JSON.stringify(mpDomState, null, 2));

    // Naviguer vers la page marketplace
    console.log('\n[4] Navigation vers #/marketplace...');
    await page.evaluate(() => {
        window.location.hash = '#/marketplace';
    });
    await new Promise(r => setTimeout(r, 2000));

    // Vérifier l'état après navigation
    const mpStateAfterNav = await page.evaluate(() => {
        const approveBtn = document.getElementById('marketplace-approve-btn');
        const createBtn = document.getElementById('marketplace-create-btn');
        const sntInput = document.getElementById('mp-snt-amount');
        const mpPage = document.getElementById('marketplace-page');
        const banner = document.getElementById('mp-contract-error');
        return {
            mpPageActive: mpPage ? mpPage.classList.contains('active') : false,
            approveBtn: approveBtn ? { disabled: approveBtn.disabled, text: approveBtn.textContent?.trim().substring(0, 40), onclick: !!approveBtn.onclick } : 'NOT IN DOM',
            createBtn: createBtn ? { disabled: createBtn.disabled, text: createBtn.textContent?.trim().substring(0, 40), onclick: !!createBtn.onclick } : 'NOT IN DOM',
            sntInput: sntInput ? { value: sntInput.value } : 'NOT IN DOM',
            banner: banner ? { display: banner.style.display, text: banner.textContent?.trim().substring(0, 60) } : 'NOT IN DOM',
        };
    });
    console.log(JSON.stringify(mpStateAfterNav, null, 2));

    // Essayer de remplir le montant SNT et cliquer sur Approuver
    console.log('\n[5] Test du flux Approuver...');
    
    const fillResult = await page.evaluate(() => {
        const input = document.getElementById('mp-snt-amount');
        if (!input) return 'SNT input NOT FOUND';
        input.value = '10';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        return 'filled 10 SNT';
    });
    console.log(`  Remplissage SNT: ${fillResult}`);

    // Vérifier l'état du bouton après remplissage
    const btnState = await page.evaluate(() => {
        const approveBtn = document.getElementById('marketplace-approve-btn');
        const createBtn = document.getElementById('marketplace-create-btn');
        return {
            approveDisabled: approveBtn ? approveBtn.disabled : 'NOT FOUND',
            approveText: approveBtn ? approveBtn.textContent?.trim().substring(0, 40) : 'NOT FOUND',
            createDisabled: createBtn ? createBtn.disabled : 'NOT FOUND',
        };
    });
    console.log(`  État boutons: ${JSON.stringify(btnState)}`);

    // Cliquer sur Approuver
    const clickResult = await page.evaluate(() => {
        const btn = document.getElementById('marketplace-approve-btn');
        if (!btn) return 'NOT FOUND';
        if (btn.disabled) return 'DISABLED';
        btn.click();
        return 'CLICKED';
    });
    console.log(`  Clic Approuver: ${clickResult}`);

    // Attendre la réaction
    await new Promise(r => setTimeout(r, 4000));

    // Vérifier l'état final
    const finalState = await page.evaluate(() => {
        const approveBtn = document.getElementById('marketplace-approve-btn');
        const createBtn = document.getElementById('marketplace-create-btn');
        const notification = document.getElementById('mp-notification');
        const banner = document.getElementById('mp-contract-error');
        return {
            approveBtn: approveBtn ? { disabled: approveBtn.disabled, text: approveBtn.textContent?.trim().substring(0, 40) } : 'NOT FOUND',
            createBtn: createBtn ? { disabled: createBtn.disabled, text: createBtn.textContent?.trim().substring(0, 40) } : 'NOT FOUND',
            notification: notification ? { display: notification.style.display, text: notification.textContent?.trim().substring(0, 100) } : 'NOT FOUND',
            banner: banner ? { display: banner.style.display, text: banner.textContent?.trim().substring(0, 100) } : 'NOT FOUND',
        };
    });
    console.log(`\n[6] État final:`);
    console.log(JSON.stringify(finalState, null, 2));

    // Erreurs console
    const errors = allLogs.filter(l => l.type === 'error');
    console.log(`\n[7] Erreurs console (${errors.length}):`);
    errors.slice(0, 10).forEach(e => console.log(`  ${e.text.substring(0, 200)}`));

    // Logs pertinents post-clic
    const postClickLogs = allLogs.filter(l => /approve|Approuver|allowance|Erreur|error|revert|rejected|insufficient|fonds|MetaMask/i.test(l.text));
    console.log(`\n[8] Logs post-clic pertinents (${postClickLogs.length}):`);
    postClickLogs.slice(-15).forEach(l => console.log(`  [${l.type}] ${l.text.substring(0, 200)}`));

    // Résumé
    console.log('\n=== RÉSUMÉ ===');
    const checks = [
        { label: 'initMarketplace exécuté', ok: mpInitLogs.length > 0 },
        { label: 'Éléments DOM trouvés', ok: mpDomState.approveBtn !== 'NOT IN DOM' },
        { label: 'Bouton Approuver a un onclick', ok: mpStateAfterNav.approveBtn !== 'NOT IN DOM' && mpStateAfterNav.approveBtn.onclick === true },
        { label: 'Bouton Approuver NON grisé', ok: btnState.approveDisabled === false },
        { label: 'Clic Approuver accepté', ok: clickResult === 'CLICKED' },
        { label: 'Page marketplace active après nav', ok: mpStateAfterNav.mpPageActive === true },
    ];
    let ok = 0;
    checks.forEach(c => { console.log(`  ${c.ok ? '✅' : '❌'} ${c.label}`); if (c.ok) ok++; });
    console.log(`\n  ${ok}/${checks.length} OK`);

    await browser.close();
    console.log('\n=== DIAGNOSTIC TERMINÉ ===');
    process.exit(ok === checks.length ? 0 : 1);
})().catch(err => { console.error('ERREUR:', err); process.exit(2); });
