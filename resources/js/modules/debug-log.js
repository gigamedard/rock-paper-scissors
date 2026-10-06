// resources/js/modules/debug-log.js
// ============================================================================
// BATTLEPOOL — JOURNAL VISUEL MOBILE (pop-up + copie presse-papiers)
// ============================================================================
// Objet : diagnostiquer sur l'iPhone l'utilisateur ce que la console ne montre
// pas. Capture : (1) window.showToast, (2) erreurs JS globales (window.onerror /
// unhandledrejection), (3) console.log/warn/error. Buffer ring de 300 lignes.
// UI : bouton flottant 🐞 (z-index 9,9e12 — au-dessus de tout, y compris
// l'overlay i18n) → pop-up plein écran → [Copier les logs] (presse-papiers,
// fallback execCommand) → l'utilisateur colle dans la conversation.
// Aucune dépendance. Activé par défaut (bêta ; on le retirera en prod finale).
// ============================================================================

const MAX_LINES = 300;
const _buf = [];
let _seq = 0;

function _ts() {
    const d = new Date();
    return d.toISOString().slice(11, 23);
}

export function debugLog(tag, message) {
    const line = `${_ts()} [${tag}] ${message}`;
    _buf.push(line);
    if (_buf.length > MAX_LINES) _buf.shift();
}

// — Capture des sources ————————————————————————————————————————————

// (1) Toasts : hook exposé aux modules (toast.js appelle _bpDebugLogHook).
// Le wrapper window.showToast reste pour les appels inline HTML.
const _origToast = window.showToast;
window.showToast = function (message, type, ms) {
    try { debugLog('TOAST', `${type || 'info'}: ${message}`); } catch (e) { /* noop */ }
    return _origToast ? _origToast(message, type, ms) : undefined;
};
window._bpDebugLogHook = debugLog;

// (2) Erreurs JS globales
window.addEventListener('error', (ev) => {
    debugLog('JS-ERR', `${ev.message} @ ${ev.filename ? ev.filename.split('/').pop() : '?'}:${ev.lineno}:${ev.colno}`);
}, true);
window.addEventListener('unhandledrejection', (ev) => {
    let why = '?';
    try {
        why = ev.reason?.message || ev.reason?.code || String(ev.reason);
    } catch (e) { /* noop */ }
    debugLog('PROMISE', why);
}, true);

// (3) Console (log/warn/error/info) — capture [Web3…] [Web3Mobile] [Web3WC] [Auth]…
for (const lvl of ['log', 'warn', 'error', 'info']) {
    const orig = console[lvl].bind(console);
    console[lvl] = (...args) => {
        try {
            const txt = args.map((a) => (typeof a === 'string' ? a : JSON.stringify(a))).join(' ');
            debugLog('CONSOLE', txt.slice(0, 300));
        } catch (e) { /* noop */ }
        orig(...args);
    };
}
debugLog('BOOT', `debug-log actif, UA=${navigator.userAgent.slice(0, 120)}`);
debugLog('BOOT', `BP-MOBILE-DEPLOY=2 Pré-switch Pingala activé, le popup « Switch Network » de Core est normal`);

// — UI : bouton flottant + pop-up ——————————————————————————————————

function ensurePanel() {
    let btn = document.getElementById('bp-debug-fab');
    if (btn) return btn;
    btn = document.createElement('button');
    btn.id = 'bp-debug-fab';
    btn.type = 'button';
    btn.textContent = '🐞';
    btn.setAttribute('aria-label', 'Journal de debug');
    btn.style.cssText = [
        'position:fixed',
        'bottom:14px',
        'right:14px',
        'z-index:999999999',
        'width:44px',
        'height:44px',
        'border-radius:50%',
        'border:2px solid rgba(0,229,255,0.65)',
        'background:rgba(6,24,36,0.92)',
        'color:#aef2ff',
        'font-size:20px',
        'line-height:1',
        'box-shadow:0 4px 16px rgba(0,0,0,0.55)',
        'pointer-events:auto'
    ].join(';');
    btn.addEventListener('click', openPanel);
    (document.body || document.documentElement).appendChild(btn);
    return btn;
}

function openPanel() {
    const old = document.getElementById('bp-debug-panel');
    if (old) { old.remove(); return; }

    const panel = document.createElement('div');
    panel.id = 'bp-debug-panel';
    panel.style.cssText = [
        'position:fixed',
        'inset:0',
        'z-index:999999999',
        'background:rgba(2,10,18,0.88)',
        'backdrop-filter:blur(3px)',
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'padding:12px'
    ].join(';');

    const card = document.createElement('div');
    card.style.cssText = [
        'background:#0a1a28',
        'border:1px solid rgba(0,229,255,0.4)',
        'border-radius:12px',
        'width:min(720px,96vw)',
        'max-height:86vh',
        'display:flex',
        'flex-direction:column',
        'box-shadow:0 12px 48px rgba(0,0,0,0.6)'
    ].join(';');

    const title = document.createElement('div');
    title.textContent = '🐞 Journal de debug — BattlePool';
    title.style.cssText = 'padding:12px 16px 6px;font-weight:700;color:#aef2ff;font-size:1rem';

    const info = document.createElement('div');
    // contexte utile pour le debug : URL, état connu du flux WC
    const wc = window._bpWcDebug?.() || {};
    info.textContent = `url=${location.href}\nUA=${navigator.userAgent}\nwcSession=${wc.hasSession ? 'OUI' : 'non'} wcUri=${wc.hasUri ? 'OUI' : 'non'} provider=${wc.provider || 'aucun'}`;
    info.style.cssText = 'padding:0 16px 8px;color:#7fd6ef;font-size:0.72rem;white-space:pre-wrap;word-break:break-all';

    const pre = document.createElement('pre');
    pre.textContent = _buf.join('\n') || '(vide)';
    pre.style.cssText = [
        'flex:1',
        'margin:0 16px',
        'padding:10px',
        'overflow:auto',
        '-webkit-overflow-scrolling:touch',
        'background:#050e17',
        'border:1px solid rgba(0,229,255,0.25)',
        'border-radius:8px',
        'color:#cde',
        'font-size:0.68rem',
        'line-height:1.45',
        'white-space:pre-wrap',
        'word-break:break-all'
    ].join(';');

    const row = document.createElement('div');
    row.style.cssText = 'display:flex;gap:10px;padding:12px 16px';

    const copyBtn = document.createElement('button');
    copyBtn.textContent = '📋 Copier les logs';
    copyBtn.style.cssText = [
        'flex:1',
        'padding:12px',
        'border-radius:8px',
        'border:none',
        'background:#00b7ff',
        'color:#04121d',
        'font-weight:700',
        'font-size:0.9rem'
    ].join(';');
    copyBtn.addEventListener('click', async () => {
        const payload = [
            `BP-DEBUG ${new Date().toISOString()}`,
            `URL ${location.href}`,
            `UA ${navigator.userAgent}`,
            `WC ${JSON.stringify(window._bpWcDebug?.() || {})}`,
            '',
            _buf.join('\n')
        ].join('\n');
        try {
            await navigator.clipboard.writeText(payload);
            copyBtn.textContent = '✅ Copié → colle ici';
        } catch (e) {
            // fallback iOS (selection textarea + execCommand)
            const ta = document.createElement('textarea');
            ta.value = payload;
            ta.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(ta);
            ta.focus(); ta.select();
            document.execCommand('copy');
            ta.remove();
            copyBtn.textContent = '✅ Copié (fallback) → colle ici';
        }
        setTimeout(() => { copyBtn.textContent = '📋 Copier les logs'; }, 2500);
    });

    const closeBtn = document.createElement('button');
    closeBtn.textContent = '✕ Fermer';
    closeBtn.style.cssText = [
        'padding:12px 16px',
        'border-radius:8px',
        'border:1px solid rgba(0,229,255,0.5)',
        'background:transparent',
        'color:#aef2ff',
        'font-weight:600',
        'font-size:0.9rem'
    ].join(';');
    closeBtn.addEventListener('click', () => panel.remove());
    row.appendChild(copyBtn);
    row.appendChild(closeBtn);
    card.appendChild(title);
    card.appendChild(info);
    card.appendChild(pre);
    card.appendChild(row);
    panel.appendChild(card);
    document.body.appendChild(panel);
}

export function initDebugLog() {
    if (document.body) ensurePanel();
    else document.addEventListener('DOMContentLoaded', ensurePanel, { once: true });
}