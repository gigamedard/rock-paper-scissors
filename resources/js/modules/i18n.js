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
        savedLang = await promptLanguageSelection();
        localStorage.setItem('user_locale', savedLang);
    }

    currentLocale = savedLang;
    await loadTranslations(currentLocale);
    applyTranslations();

    console.log(`[i18n] Langue active : ${currentLocale}`);
}

function promptLanguageSelection() {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
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
        title.style.marginBottom = '30px';
        title.style.fontFamily = 'Inter, sans-serif';
        title.style.fontSize = '2rem';
        overlay.appendChild(title);

        const grid = document.createElement('div');
        grid.style.display = 'grid';
        grid.style.gridTemplateColumns = 'repeat(2, 1fr)';
        grid.style.gap = '15px';
        grid.style.maxWidth = '500px';
        grid.style.width = '90%';

        SUPPORTED_LOCALES.forEach(lang => {
            const btn = document.createElement('button');
            btn.textContent = FLAGS[lang];
            btn.style.padding = '15px 20px';
            btn.style.fontSize = '1.2rem';
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

async function loadTranslations(locale) {
    try {
        const res = await fetch(`/locales/${locale}.json?v=${Date.now()}`);
        if (!res.ok) throw new Error(`Fichier de langue introuvable: ${locale}.json`);
        translations = await res.json();
    } catch (e) {
        console.error('[i18n] Erreur de chargement :', e);
        // Fallback sur les clés brutes si le JSON échoue
        translations = {};
    }
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
    await loadTranslations(locale);
    applyTranslations();
    window.dispatchEvent(new CustomEvent('i18n:changed', { detail: { locale } }));
}

// Expose globalement pour usage dans les templates HTML inline
window.t = t;
window.setLocale = setLocale;
