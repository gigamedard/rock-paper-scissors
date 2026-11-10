/**
 * Headless browser test "connecté" : restaure une session (auth_token + user)
 * et vérifie le rendu P3 (dashboard) : état rattrapage, remboursements en attente/reçus,
 * ligne de claim « dont remboursement ».
 *
 * Env requis : TOKEN, USER_JSON (JSON string de l'utilisateur), BASE.
 */
const { chromium } = require('playwright');

(async () => {
  const BASE = process.env.BASE || 'http://127.0.0.1:8001/';
  const TOKEN = process.env.TOKEN || '';
  const USER_JSON = process.env.USER_JSON || '{}';
  let pass = 0, fail = 0;
  const ok = (m) => { pass++; console.log('PASS  ' + m); };
  const ko = (m, d = '') => { fail++; console.log('FAIL  ' + m + (d ? '  (' + d + ')' : '')); };
  const ck = (m, c, d = '') => c ? ok(m) : ko(m, d);

  if (!TOKEN) { ko('TOKEN manquant'); process.exit(1); }

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  await page.addInitScript(({ token, user }) => {
    try {
      localStorage.setItem('user_locale', 'en');
      localStorage.setItem('auth_token', token);
      localStorage.setItem('user', user);
    } catch (e) {}
  }, { token: TOKEN, user: USER_JSON });

  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));

  await page.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });

  // Attend que le panneau P3 devienne visible (après /user/status).
  let visible = false;
  try {
    await page.locator('#p3-panel').waitFor({ state: 'visible', timeout: 12000 });
    visible = true;
  } catch (e) {}
  ck('panneau P3 visible (session restaurée)', visible);

  const txt = await page.evaluate(() => document.getElementById('p3-panel')?.innerText || '');
  ck('état = Rattrapage', /Rattrapage/i.test(txt), txt.slice(0, 80));
  ck('mention remboursement en attente', /remboursement en attente/i.test(txt));
  ck('dette FIFO 1.96 affichée', /1\.96/.test(txt), txt.slice(0, 120));
  ck('remboursement reçu 2.0000 affiché', /2\.0000/.test(txt), txt.slice(0, 160));

  const pendingCount = await page.evaluate(() => document.getElementById('p3-refund-count')?.innerText || '');
  ck('p3-refund-count = 1', pendingCount.trim() === '1', 'got=' + pendingCount);

  const claimRefund = await page.evaluate(() => document.getElementById('claim-refund-amount')?.innerText || '');
  ck('claim « dont remboursement » = 2.0000', claimRefund.trim() === '2.0000', 'got=' + claimRefund);

  try { await page.screenshot({ path: '/tests/screenshot-connected.png', fullPage: false }); ok('screenshot connecté'); } catch (e) {}

  const severe = errors.filter((e) => !/websocket|reverb|ws:|favicon|net::ERR|API-FAIL|metrics\/collect/i.test(e));
  if (errors.length) console.log('  [info] console errors (' + errors.length + '): ' + errors.slice(0, 3).join(' | '));
  ck('aucune erreur JS sévère', severe.length === 0, severe.slice(0, 2).join(' | '));

  await browser.close();
  console.log('=======================================');
  console.log('RÉSULTAT connecté : ' + pass + ' OK, ' + fail + ' échec(s)');
  process.exit(fail === 0 ? 0 : 1);
})();
