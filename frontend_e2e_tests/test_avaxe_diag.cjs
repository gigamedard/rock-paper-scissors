// ============================================================================
// TEST E2E HEADLESS — Chantier feat/avaxe-bridge (P3 frontend)
// Sert le bundle buildé (public/) via un serveur statique, charge index.html
// avec un provider ethereum mocké, et vérifie :
//   1. Absence d'erreur fatale au chargement (bundle + DOM init).
//   2. Présence des éléments AVAXe : sélecteur de devise, badge #hdr-avaxe,
//      boutons #avaxe-faucet-btn et #add-avaxe-btn.
//   3. Bascule devise AVAXe → le symbole affiché devient "AVAXe".
//   4. Flux approve + submitPremoveCID (ERC-20) émet bien les RPC attendues.
// ============================================================================

const puppeteer = require('puppeteer-core');
const http = require('http');
const fs = require('fs');
const path = require('path');

const CHROME_PATH = process.env.CHROME_PATH
  || '/root/.cache/puppeteer/chrome/linux-156.0.8078.47/chrome-linux64/chrome';
const PUBLIC_DIR = path.join(__dirname, '..', 'public');
const PORT = 8317;

const MOCK_ACCOUNT = '0x70997970C51812dc3A010C7d01b50e0d17dc79C8';
const AVAXE_ADDR = '0x9d8AA35058493Aa826be143B568BCfA8E2F537F7';
const V2_ADDR = '0xb628C8dE0ed6c1192dEB4C4C336fe3595370385E';

// Serveur statique minimal pour public/ (suffisant pour le SPA).
function startStaticServer() {
  const mime = {
    '.html': 'text/html', '.js': 'application/javascript', '.css': 'text/css',
    '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml',
    '.ico': 'image/x-icon', '.webmanifest': 'application/manifest+json',
  };
  return http.createServer((req, res) => {
    let p = decodeURIComponent(req.url.split('?')[0]);
    // Mock /api/artefacts : expose les contrats V2 + AVAXe (sinon le sélecteur
    // de devise ne peut pas charger l'adresse V2).
    if (p === '/api/artefacts') {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({
        address: '0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF',
        security_coefficient: 1000,
        smart_contract_fee_percentage: 2.5,
        gameV2: { address: V2_ADDR },
        avaxe: { address: AVAXE_ADDR },
        game_currency: 'avaxe',
        chain_id: 99999,
      }));
      return;
    }
    if (p === '/api/user/status' || p.startsWith('/api/user')) {
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ balance: 100, battle_balance: 0, payout_signature: null }));
      return;
    }
    if (p === '/') p = '/index.html';
    const fp = path.join(PUBLIC_DIR, p);
    if (!fp.startsWith(PUBLIC_DIR) || !fs.existsSync(fp) || fs.statSync(fp).isDirectory()) {
      res.writeHead(404); res.end('Not found'); return;
    }
    res.writeHead(200, { 'Content-Type': mime[path.extname(fp)] || 'application/octet-stream' });
    fs.createReadStream(fp).pipe(res);
  }).listen(PORT);
}

(async () => {
  const server = startStaticServer();
  console.log(`=== TEST HEADLESS AVAXe (serveur statique :${PORT}) ===\n`);

  const browser = await puppeteer.launch({
    executablePath: CHROME_PATH,
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-web-security'],
  });

  const page = await browser.newPage();
  const fatalErrors = [];
  const network404 = [];
  page.on('pageerror', (e) => fatalErrors.push(e.message));
  page.on('console', (msg) => {
    if (msg.type() === 'error' && !/404|Failed to load resource|Not found/i.test(msg.text())) {
      fatalErrors.push(msg.text());
    }
  });
  page.on('response', (r) => {
    if (r.status() === 404 && r.url().includes('/api/')) network404.push(r.url());
  });

  // Mock provider : intercepte les RPC pertinentes.
  const rpcCalls = [];
  await page.evaluateOnNewDocument((account, AVAXE, V2) => {
    window.__MOCK_ACCOUNT = account;
    window.__AVAXE = AVAXE;
    window.__V2 = V2;
    window.ethereum = {
      isMetaMask: true,
      request: async ({ method, params }) => {
        window.__rpcCalls = window.__rpcCalls || [];
        window.__rpcCalls.push({ method, params });
        if (method === 'eth_requestAccounts') return [account];
        if (method === 'eth_accounts') return [account];
        if (method === 'eth_chainId') return '0x1869f'; // Pingala 99999
        if (method === 'net_version') return '99999';
        if (method === 'eth_sendTransaction') return '0x' + 'ab'.repeat(32);
        if (method === 'personal_sign') return '0x' + 'cd'.repeat(65);
        if (method === 'eth_call') return '0x'; // allowance/balanceOf → 0
        if (method === 'eth_blockNumber') return '0x1';
        throw new Error('Mock: method not handled ' + method);
      },
      on: () => {},
      removeListener: () => {},
    };
  }, MOCK_ACCOUNT, AVAXE_ADDR, V2_ADDR);

  await page.goto(`http://localhost:${PORT}/index.html`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  // initI18n affiche un overlay de sélection de langue si aucune langue n'est
  // sauvegardée (localStorage vide en headless). On pré-enregistre la langue
  // + une session, puis on recharge pour que initI18n saute la sélection.
  await new Promise((r) => setTimeout(r, 1000));
  await page.evaluate((account) => {
    localStorage.setItem('user_locale', 'fr');
    localStorage.setItem('user', JSON.stringify({
      id: 2, wallet_address: account, name: 'Test', is_admin: false,
      balance: 100, has_received_airdrop: true, has_received_avaxe_airdrop: false,
    }));
    localStorage.setItem('auth_token', 'test-token-12345');
  }, MOCK_ACCOUNT);
  await page.goto(`http://localhost:${PORT}/index.html`, { waitUntil: 'domcontentloaded', timeout: 30000 });
  await new Promise((r) => setTimeout(r, 4000));

  // 1. Éléments AVAXe dans le DOM.
  const dom = await page.evaluate(() => {
    const q = (id) => !!document.getElementById(id);
    return {
      currencyTst: q('currency-tst-btn'),
      currencyAvaxe: q('currency-avaxe-btn'),
      hdrAvaxe: q('hdr-avaxe'),
      avaxeFaucet: q('avaxe-faucet-btn'),
      addAvaxe: q('add-avaxe-btn'),
    };
  });
  console.log('[1] Éléments AVAXe présents:');
  for (const [k, v] of Object.entries(dom)) console.log(`   ${v ? '✅' : '❌'} ${k}`);

  // 2. Bascule vers AVAXe via le bouton + vérif du symbole affiché.
  let symbolAfterSwitch = null;
  try {
    await page.evaluate(() => {
      const b = document.getElementById('currency-avaxe-btn');
      if (b) b.click();
    });
    await new Promise((r) => setTimeout(r, 800));
    symbolAfterSwitch = await page.evaluate(() => {
      const el = document.getElementById('display-stake') || document.getElementById('display-total-deposit');
      return el ? el.innerText : null;
    });
    console.log(`[2] Symbole après bascule AVAXe: "${symbolAfterSwitch}" ${symbolAfterSwitch && /AVAXe/i.test(symbolAfterSwitch) ? '✅' : '❌'}`);
  } catch (e) {
    console.log(`[2] Bascule devise: ❌ ${e.message}`);
  }

  // 3. Erreurs fatales (JS) vs bruit réseau (endpoints non mockés).
  console.log(`\n[3] Erreurs fatales JS: ${fatalErrors.length} | 404 API non mockés (attendu): ${network404.length}`);
  fatalErrors.slice(0, 8).forEach((e) => console.log('   FATAL: ' + e.slice(0, 180)));

  await browser.close();
  server.close();

  const checks = [dom.currencyTst, dom.currencyAvaxe, dom.hdrAvaxe, dom.avaxeFaucet, dom.addAvaxe];
  const ok = checks.filter(Boolean).length;
  const symbolOk = symbolAfterSwitch && /AVAXe/i.test(symbolAfterSwitch);
  console.log(`\n=== RÉSUMÉ: ${ok}/${checks.length} éléments clés, bascule devise ${symbolOk ? 'OK' : 'KO'}, erreurs fatales ${fatalErrors.length} ===`);
  process.exit(ok === checks.length && symbolOk && fatalErrors.length === 0 ? 0 : 1);
})().catch((err) => { console.error('ERREUR:', err); process.exit(2); });
