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

  // Évite le modal de sélection de langue au 1er chargement (sinon initGame ne démarre pas).
  await page.addInitScript(() => { try { localStorage.setItem('user_locale', 'en'); } catch (e) {} });

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

  for (const sel of ['#p3-breakdown', '#p3-panel', '#p3-refund-paid', '#claim-refund-line', '#base-bet-input', '#target-q-input', '#start-session-btn', '#claim-section']) {
    const n = await page.locator(sel).count();
    ck('sélecteur présent ' + sel, n >= 1, 'count=' + n);
  }

  const art = await page.evaluate(async () => {
    try { const r = await fetch('/api/artefacts'); return await r.json(); } catch (e) { return null; }
  });
  ck('API artefacts joignable', !!art);
  if (art) {
    ck('bankroll_enabled=true', art.bankroll_enabled === true);
    ck('target=1.2', Number(art.bankroll_session_target_q) === 1.2);
    ck('X≈6.03', Math.abs(Number(art.bankroll_recovery_factor) - 6.03) < 0.05, 'X=' + art.bankroll_recovery_factor);
  }

  // La cible P3 doit être sélectionnable dans l'UI (1.05 et 1.2).
  const opts = await page.evaluate(() => {
    const q = document.getElementById('target-q-input');
    return q ? Array.from(q.options).map((o) => o.value) : [];
  });
  ck('option target 1.05', opts.includes('1.05'), 'opts=' + opts.join(','));
  ck('option target 1.2', opts.includes('1.2'), 'opts=' + opts.join(','));

  // Déclenche le recalcul (onchange -> updateFeeDisplay) même si le panneau est masqué.
  // Cible active = BANKROLL_SESSION_TARGET_Q (1.2).
  await page.evaluate(() => {
    const b = document.getElementById('base-bet-input');
    if (b) { b.value = '0.0004'; b.dispatchEvent(new Event('change', { bubbles: true })); }
    const q = document.getElementById('target-q-input');
    if (q) { q.value = '1.2'; q.dispatchEvent(new Event('change', { bubbles: true })); }
  }).catch(() => {});
  await page.waitForTimeout(800);
  const p3 = await page.evaluate(() => {
    const g = (id) => document.getElementById(id)?.innerText || null;
    return { C: g('display-session-capital'), R: g('display-recovery-reserve'), B: g('display-bankroll'), pol: g('display-refund-policy') };
  });
  console.log('  [info] C/R/B/refund =', JSON.stringify(p3));
  ck('C ≈ 0.04 rendu', Math.abs(parseFloat(p3.C) - 0.04) < 0.002, 'C=' + p3.C);
  ck('R ≈ 0.2412 rendu', Math.abs(parseFloat(p3.R) - 0.2412) < 0.002, 'R=' + p3.R);
  ck('B ≈ 0.2812 rendu', Math.abs(parseFloat(p3.B) - 0.2812) < 0.002, 'B=' + p3.B);

  // Bascule sur la cible 1.05 -> X plus grand, donc R/B plus grands.
  await page.evaluate(() => {
    const q = document.getElementById('target-q-input');
    if (q) { q.value = '1.05'; q.dispatchEvent(new Event('change', { bubbles: true })); }
  }).catch(() => {});
  await page.waitForTimeout(500);
  const p3b = await page.evaluate(() => {
    const g = (id) => document.getElementById(id)?.innerText || null;
    return { R: g('display-recovery-reserve'), B: g('display-bankroll') };
  });
  ck('cible 1.05 -> R ≈ 0.8442', Math.abs(parseFloat(p3b.R) - 0.8442) < 0.003, 'R=' + p3b.R);
  ck('cible 1.05 -> B ≈ 0.8842', Math.abs(parseFloat(p3b.B) - 0.8842) < 0.003, 'B=' + p3b.B);

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
