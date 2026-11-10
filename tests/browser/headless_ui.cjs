/**
 * Headless browser smoke test (Playwright/Chromium) — CommonJS (require) pour
 * fonctionner avec NODE_PATH=$(npm root -g) dans l'image Playwright.
 */
const { chromium } = require('playwright');

(async () => {
  const BASE = process.env.BASE || 'http://127.0.0.1:8001/';
  let pass = 0, fail = 0;
  const ok = (m) => { pass++; console.log('PASS  ' + m); };
  const ko = (m, d = '') => { fail++; console.log('FAIL  ' + m + (d ? '  (' + d + ')' : '')); };
  const ck = (m, c, d = '') => c ? ok(m) : ko(m, d);

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  const errors = [];
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));

  let resp = null;
  try {
    resp = await page.goto(BASE, { waitUntil: 'networkidle', timeout: 60000 });
    ok('page chargée (HTTP ' + (resp ? resp.status() : '?') + ')');
  } catch (e) {
    ko('page load', e.message);
  }

  await page.waitForTimeout(1500);

  const title = await page.title();
  ck('titre présent', !!title, 'title=' + title);

  for (const sel of ['#p3-breakdown', '#base-bet-input', '#target-q-input', '#start-session-btn', '#claim-section']) {
    const n = await page.locator(sel).count();
    ck('sélecteur présent ' + sel, n >= 1, 'count=' + n);
  }

  const art = await page.evaluate(async () => {
    try { const r = await fetch('/api/artefacts'); return await r.json(); } catch (e) { return null; }
  });
  ck('API artefacts joignable', !!art);
  if (art) {
    ck('bankroll_enabled=true', art.bankroll_enabled === true);
    ck('target=1.05', Number(art.bankroll_session_target_q) === 1.05);
    ck('X≈23.1', Math.abs(Number(art.bankroll_recovery_factor) - 23.1) < 0.2, 'X=' + art.bankroll_recovery_factor);
  }

  // Déclenche le recalcul (onchange -> updateFeeDisplay) puis vérifie les valeurs C/R/B.
  await page.selectOption('#base-bet-input', '0.0004').catch(() => {});
  await page.waitForTimeout(600);
  const p3 = await page.evaluate(() => {
    const g = (id) => document.getElementById(id)?.innerText || null;
    return { C: g('display-session-capital'), R: g('display-recovery-reserve'), B: g('display-bankroll'), pol: g('display-refund-policy') };
  });
  console.log('  [info] C/R/B/refund =', JSON.stringify(p3));
  ck('C ≈ 0.12 rendu', Math.abs(parseFloat(p3.C) - 0.12) < 0.002, 'C=' + p3.C);
  ck('R ≈ 2.772 rendu', Math.abs(parseFloat(p3.R) - 2.772) < 0.02, 'R=' + p3.R);
  ck('B ≈ 2.892 rendu', Math.abs(parseFloat(p3.B) - 2.892) < 0.02, 'B=' + p3.B);

  try {
    await page.screenshot({ path: '/tests/screenshot.png', fullPage: false });
    ok('screenshot généré (/tests/screenshot.png)');
  } catch (e) {
    console.log('  [warn] screenshot: ' + e.message);
  }

  const severe = errors.filter((e) => !/websocket|reverb|ws:|favicon|net::ERR/i.test(e));
  if (errors.length) console.log('  [info] console errors (' + errors.length + ') ex: ' + errors.slice(0, 3).join(' | '));
  ck('aucune erreur JS sévère', severe.length === 0, severe.slice(0, 2).join(' | '));

  await browser.close();
  console.log('=======================================');
  console.log('RÉSULTAT navigateur : ' + pass + ' OK, ' + fail + ' échec(s)');
  process.exit(fail === 0 ? 0 : 1);
})();
