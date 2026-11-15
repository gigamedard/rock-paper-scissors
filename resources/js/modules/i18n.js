// resources/js/modules/i18n.js
/**
 * Module Internationalisation (i18n)
 * - Charge les fichiers JSON depuis /locales/{locale}.json
 * - Applique les traductions sur les éléments DOM [data-i18n]
 * - Expose la méthode globale t('key.nested') pour usage en JS
 * - Lit le paramètre ?lang= dans l'URL (lien de parrainage)
 */

let currentLocale = 'en';
let translations = {};
export const SUPPORTED_LOCALES = ['fr', 'en', 'es', 'de', 'pt', 'zh'];

const FLAGS = {
    'fr': '🇫🇷 Français',
    'en': '🇬🇧 English',
    'es': '🇪🇸 Español',
    'de': '🇩🇪 Deutsch',
    'pt': '🇵🇹 Português',
    'zh': '🇨🇳 中文'
};

export async function initI18n() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');

    let savedLang = localStorage.getItem('user_locale');

    // Résolution initiale silencieuse
    if (urlLang && SUPPORTED_LOCALES.includes(urlLang)) {
        savedLang = urlLang;
        localStorage.setItem('user_locale', urlLang);
    }

    if (!savedLang || !SUPPORTED_LOCALES.includes(savedLang)) {
        // Ne bloque plus le premier chargement : auto-détection navigateur
        // (fallback français). Le choix reste modifiable via ?lang= / réglages.
        const navLang = String(navigator.language || 'en').slice(0, 2).toLowerCase();
        savedLang = SUPPORTED_LOCALES.includes(navLang) ? navLang : 'fr';
        localStorage.setItem('user_locale', savedLang);
        console.info(`[i18n] langue auto-détectée : ${savedLang}`);
    }

    currentLocale = savedLang;
    bindLanguageSelector();

    // DÉMARRAGE PWA RAPIDE : si la langue est déjà en cache local, on applique
    // immédiatement les traductions (aucune attente réseau) puis on rafraîchit
    // en arrière-plan. Le premier chargement (sans cache) garde un timeout court
    // pour ne jamais bloquer l'app sur un réseau mobile faible.
    const cached = readLocaleCache(currentLocale);
    if (cached) {
        translations = cached;
        applyTranslations();
        console.log(`[i18n] Langue active (cache) : ${currentLocale}`);
        fetchTranslations(currentLocale, 8000).then((data) => {
            if (!data) return;
            translations = data;
            writeLocaleCache(currentLocale, data);
            applyTranslations();
            window.dispatchEvent(new CustomEvent('i18n:changed', { detail: { locale: currentLocale } }));
        }).catch(() => { /* offline : le cache suffit */ });
        return;
    }

    const ok = await loadTranslations(currentLocale, { timeoutMs: 6000 });
    if (!ok && currentLocale !== 'en') {
        await loadTranslations('en', { timeoutMs: 4000 });
    }
    applyTranslations();
    console.log(`[i18n] Langue active : ${currentLocale}`);
}

function bindLanguageSelector() {
    const sel = document.getElementById('lang-select');
    if (!sel) return;
    sel.value = currentLocale;
    sel.onchange = () => {
        if (sel.value && sel.value !== currentLocale) setLocale(sel.value);
    };
}

function promptLanguageSelection() {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100vh';
        overlay.style.height = '100dvh';
        overlay.style.boxSizing = 'border-box';
        overlay.style.overflowY = 'auto';
        overlay.style.padding = 'calc(1rem + env(safe-area-inset-top)) max(1rem, env(safe-area-inset-right)) calc(1rem + env(safe-area-inset-bottom)) max(1rem, env(safe-area-inset-left))';
        overlay.style.backgroundColor = 'rgba(15, 23, 42, 0.95)';
        overlay.style.backdropFilter = 'blur(10px)';
        overlay.style.zIndex = '999999';
        overlay.style.display = 'flex';
        overlay.style.flexDirection = 'column';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.color = 'white';

        const title = document.createElement('h1');
        title.textContent = 'Select your Language';
        title.style.maxWidth = '100%';
        title.style.textAlign = 'center';
        title.style.marginBottom = '20px';
        title.style.fontFamily = 'Inter, sans-serif';
        title.style.fontSize = 'clamp(1.3rem, 6vw, 2rem)';
        overlay.appendChild(title);

        const grid = document.createElement('div');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(130px, 1fr))';
        grid.style.gap = '15px';
        grid.style.width = 'min(500px, 92vw)';
        grid.style.maxWidth = '100%';

        SUPPORTED_LOCALES.forEach(lang => {
            const btn = document.createElement('button');
            btn.textContent = FLAGS[lang];
            btn.style.padding = '14px 16px';
            btn.style.fontSize = 'clamp(1rem, 4.5vw, 1.2rem)';
            btn.style.borderRadius = '12px';
            btn.style.border = '1px solid rgba(255,255,255,0.2)';
            btn.style.background = 'rgba(255,255,255,0.05)';
            btn.style.color = 'white';
            btn.style.cursor = 'pointer';
            btn.style.transition = 'all 0.2s';
            
            btn.onmouseover = () => {
                btn.style.background = 'rgba(255,255,255,0.15)';
                btn.style.borderColor = 'rgba(255,255,255,0.5)';
            };
            btn.onmouseout = () => {
                btn.style.background = 'rgba(255,255,255,0.05)';
                btn.style.borderColor = 'rgba(255,255,255,0.2)';
            };

            btn.onclick = () => {
                document.body.removeChild(overlay);
                resolve(lang);
            };
            grid.appendChild(btn);
        });

        overlay.appendChild(grid);
        document.body.appendChild(overlay);
    });
}

// Version du dictionnaire : à incrémenter lors d'un changement de traductions.
// URL STABLE (pas de Date.now()) → cache HTTP + cache SW + cache localStorage.
const I18N_VERSION = '7';
const I18N_CACHE_PREFIX = 'bp_i18n_v' + I18N_VERSION + '_';

function readLocaleCache(locale) {
    try {
        const raw = localStorage.getItem(I18N_CACHE_PREFIX + locale);
        if (!raw) return null;
        const data = JSON.parse(raw);
        return data && typeof data === 'object' ? data : null;
    } catch (e) { return null; }
}

function writeLocaleCache(locale, data) {
    try { localStorage.setItem(I18N_CACHE_PREFIX + locale, JSON.stringify(data)); } catch (e) { /* quota/noop */ }
}

async function fetchTranslations(locale, timeoutMs = 7000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(`/locales/${locale}.json?v=${I18N_VERSION}`, { signal: controller.signal });
        if (!res.ok) throw new Error(`Fichier de langue introuvable: ${locale}.json`);
        return await res.json();
    } catch (e) {
        console.warn('[i18n] chargement différé/impossible :', e?.message || e);
        return null;
    } finally {
        clearTimeout(timer);
    }
}

async function loadTranslations(locale, { timeoutMs = 7000 } = {}) {
    const data = await fetchTranslations(locale, timeoutMs);
    if (!data) return false;
    translations = data;
    writeLocaleCache(locale, data);
    return true;
}

/**
 * Traduit une clé (ex: "marketplace.title")
 */
export function t(key, params = {}) {
    const parts = key.split('.');
    let result = translations;
    for (const part of parts) {
        result = result?.[part];
        if (result === undefined) {
            result = key;
            break;
        }
    }
    let text = result || key;
    Object.keys(params).forEach(p => {
        text = text.replace(new RegExp(`:${p}`, 'g'), params[p]);
        text = text.replace(new RegExp(`{${p}}`, 'g'), params[p]);
    });
    return text;
}

/**
 * Scanne le DOM et remplace les éléments [data-i18n]
 */
export function applyTranslations() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        const translated = t(key);
        if (el.placeholder !== undefined && el.tagName === 'INPUT') {
            el.placeholder = translated;
        } else {
            el.textContent = translated;
        }
    });

    // Met à jour l'attribut lang du HTML
    document.documentElement.lang = currentLocale;

    // Sélecteur de langue visible (header) + anciens boutons éventuels.
    const sel = document.getElementById('lang-select');
    if (sel) sel.value = currentLocale;

    // Met à jour la classe active sur les boutons de langue
    const btnFr = document.getElementById('lang-fr');
    const btnEn = document.getElementById('lang-en');
    if (btnFr && btnEn) {
        if (currentLocale === 'fr') {
            btnFr.classList.add('active');
            btnEn.classList.remove('active');
        } else {
            btnEn.classList.add('active');
            btnFr.classList.remove('active');
        }
    }
}

export function getCurrentLocale() {
    return currentLocale;
}

export async function setLocale(locale) {
    if (!SUPPORTED_LOCALES.includes(locale)) return;
    currentLocale = locale;
    localStorage.setItem('user_locale', locale);
    await loadTranslations(locale, { timeoutMs: 10000 });
    applyTranslations();
    window.dispatchEvent(new CustomEvent('i18n:changed', { detail: { locale } }));
}

// Expose globalement pour usage dans les templates HTML inline
window.t = t;
window.setLocale = setLocale;
