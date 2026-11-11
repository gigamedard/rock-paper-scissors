/**
 * Headless browser + wallet injecté (EIP-1193) : flux UI réel Start Session
 * (premoves -> approve -> deposit B+frais on-chain) pour un humain.
 *
 * Env : PK (clé wallet test), ADDRESS, TOKEN, USER_JSON, BASE, RPC.
 */
const { chromium } = require('playwright');

(async () => {
  const BASE = process.env.BASE || 'http://127.0.0.1:8001/';
  const RPC = process.env.RPC || '';
  const PK = process.env.PK || '';
  const ADDRESS = (process.env.ADDRESS || '').toLowerCase();
  const TOKEN = process.env.TOKEN || '';
  const USER_JSON = process.env.USER_JSON || '{}';
  let pass = 0, fail = 0;
  const ok = (m) => { pass++; console.log('PASS  ' + m); };
  const ko = (m, d = '') => { fail++; console.log('FAIL  ' + m + (d ? '  (' + d + ')' : '')); };
  const ck = (m, c, d = '') => c ? ok(m) : ko(m, d);

  if (!PK || !ADDRESS || !TOKEN) { ko('env manquant (PK/ADDRESS/TOKEN)'); process.exit(1); }

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  await page.addInitScript(({ token, user }) => {
    try {
      localStorage.setItem('user_locale', 'en');
      localStorage.setItem('auth_token', token);
      localStorage.setItem('user', user);
    } catch (e) {}
  }, { token: TOKEN, user: USER_JSON });

  // IPFS : on court-circuite (Pinata) pour ne pas bloquer le flux UI.
  await page.route('**/api/ipfs/upload', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, IpfsHash: 'QmTestCidUI123' }) })
  );

  const logs = [];
  page.on('console', (m) => logs.push(m.type() + ': ' + m.text()));
  page.on('pageerror', (e) => logs.push('PAGEERROR: ' + e.message));

  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForTimeout(2000);

  // ethers UMD depuis CDN + provider EIP-1193 signé par le wallet de test.
  await page.addScriptTag({ url: 'https://cdn.jsdelivr.net/npm/ethers@6.13.4/dist/ethers.umd.min.js' });
  await page.evaluate(({ rpcUrl, pk, address }) => {
    const rpc = new window.ethers.JsonRpcProvider(rpcUrl);
    const wallet = new window.ethers.Wallet(pk, rpc);
    window.ethereum = {
      isMetaMask: true,
      request: async ({ method, params }) => {
        if (method === 'eth_requestAccounts' || method === 'eth_accounts') return [address];
        if (method === 'eth_chainId') return '0x1869f';
        if (method === 'wallet_switchEthereumChain' || method === 'wallet_addEthereumChain') return null;
        if (method === 'eth_sendTransaction') {
          const t = params[0];
          const sent = await wallet.sendTransaction({
            to: t.to,
            value: t.value ? BigInt(t.value) : 0n,
            data: t.data,
            gasLimit: t.gas ? BigInt(t.gas) : undefined,
            nonce: t.nonce ? Number(t.nonce) : undefined,
          });
          return sent.hash;
        }
        return rpc.send(method, params || []);
      },
      on: () => {}, removeListener: () => {}, addListener: () => {},
    };
    window.__testWallet = wallet.address;
  }, { rpcUrl: RPC, pk: PK, address: ADDRESS });
  ok('provider EIP-1193 injecté');

  // Va au setup (dashboard -> join -> setup).
  try {
    await page.locator('#join-btn').waitFor({ state: 'visible', timeout: 12000 });
    await page.locator('#join-btn').click();
  } catch (e) { console.log('  [info] join-btn: ' + e.message); }
  await page.waitForTimeout(800);

  // 10 premoves.
  for (let i = 0; i < 10; i++) { await page.locator('#move-rock').click().catch(() => {}); }
  ck('start-session-btn visible', await page.locator('#start-session-btn').isVisible().catch(() => false));

  // Paramètres : base 0.0004 / target 1.05.
  await page.evaluate(() => {
    const b = document.getElementById('base-bet-input'); if (b) { b.value = '0.0004'; b.dispatchEvent(new Event('change', { bubbles: true })); }
    const q = document.getElementById('target-q-input'); if (q) { q.value = '1.05'; q.dispatchEvent(new Event('change', { bubbles: true })); }
  });
  await page.waitForTimeout(300);


  // Start Session
  await page.locator('#start-session-btn').click().catch((e) => console.log('  [info] start click: ' + e.message));

  // Attend que le dépôt se reflète (DB balance via indexeur OU status in_pool).
  let dbBal = 0, status = '', deposited = false;
  for (let i = 0; i < 48; i++) { // ~120s
    await page.waitForTimeout(2500);
    const st = await page.evaluate(async (token) => {
      try {
        const r = await fetch('/api/user/status', { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } });
        return await r.json();
      } catch (e) { return null; }
    }, TOKEN).catch(() => null);
    if (st) {
      dbBal = parseFloat(st.balance) || 0;
      status = st.status || '';
      if (dbBal > 0.05 || status === 'in_pool') { deposited = true; break; }
    }
  }

  ck('dépôt on-chain pris en compte (DB balance > 0.05 ou in_pool)', deposited, 'bal=' + dbBal + ' status=' + status);
  if (dbBal > 0) ok('balance DB synchronisée = ' + dbBal.toFixed(4));

  const txLogs = logs.filter((l) => /tx envoyée|tx minée|approve|deposit|Business/i.test(l));
  if (txLogs.length) console.log('  [info] logs tx:\n    ' + txLogs.slice(0, 8).join('\n    '));

  try { await page.screenshot({ path: '/tests/screenshot-wallet.png', fullPage: false }); ok('screenshot wallet'); } catch (e) {}

  const severe = logs.filter((l) => /^error:/.test(l) && !/websocket|reverb|ws:|favicon|net::ERR|API-FAIL|ipfs|pinata/i.test(l));
  ck('aucune erreur JS sévère', severe.length === 0, severe.slice(0, 2).join(' | '));

  await browser.close();
  console.log('=======================================');
  console.log('RÉSULTAT wallet UI : ' + pass + ' OK, ' + fail + ' échec(s)');
  process.exit(fail === 0 ? 0 : 1);
})();
