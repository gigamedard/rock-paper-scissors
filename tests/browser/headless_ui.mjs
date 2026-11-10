/**
 * Headless browser smoke test (Playwright/Chromium).
 * Lance : la SPA, vérifie les sélecteurs clés, l'API P3, capture les erreurs console.
 * Exécution via l'image Playwright :
 *   docker run --rm --network host -v "$PWD/tests/browser:/tests" -e BASE=http://127.0.0.1:8001 \
 *     mcr.microsoft.com/playwright:v1.47.0-jammy sh -c 'NODE_PATH=$(npm root -g) node /tests/headless_ui.mjs'
 */
import { chromium } from 'playwright';

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

// Sélecteurs clés présents dans le DOM
for (const sel of ['#p3-breakdown', '#base-bet-input', '#target-q-input', '#start-session-btn', '#claim-section']) {
  const n = await page.locator(sel).count();
  ck('sélecteur présent ' + sel, n >= 1, 'count=' + n);
}

// API P3 depuis le contexte navigateur (même origine)
const art = await page.evaluate(async () => {
  try { const r = await fetch('/api/artefacts'); return await r.json(); } catch (e) { return null; }
});
ck('API artefacts joignable', !!art);
if (art) {
  ck('bankroll_enabled=true', art.bankroll_enabled === true);
  ck('target=1.05', Number(art.bankroll_session_target_q) === 1.05);
  ck('X≈23.1', Math.abs(Number(art.bankroll_recovery_factor) - 23.1) < 0.2, 'X=' + art.bankroll_recovery_factor);
}

// Valeurs C/R/B calculées par le bundle si le setup est affichable (non bloquant)
const p3 = await page.evaluate(() => {
  const g = (id) => document.getElementById(id)?.innerText || null;
  return { C: g('display-session-capital'), R: g('display-recovery-reserve'), B: g('display-bankroll'), pol: g('display-refund-policy') };
});
console.log('  [info] C/R/B/refund =', JSON.stringify(p3));

// Capture écran d'évidence
try {
  await page.screenshot({ path: '/tests/screenshot.png', fullPage: false });
  ok('screenshot généré (/tests/screenshot.png)');
} catch (e) {
  console.log('  [warn] screenshot: ' + e.message);
}

// Erreurs console/pages : on tolère le bruit WebSocket/Reverb, mais on signale
const severe = errors.filter((e) => !/websocket|reverb|ws:|favicon|net::ERR/i.test(e));
if (errors.length) console.log('  [info] console errors (' + errors.length + ') ex: ' + errors.slice(0, 3).join(' | '));
ck('aucune erreur JS sévère', severe.length === 0, severe.slice(0, 2).join(' | '));

await browser.close();
console.log('=======================================');
console.log('RÉSULTAT navigateur : ' + pass + ' OK, ' + fail + ' échec(s)');
process.exit(fail === 0 ? 0 : 1);
