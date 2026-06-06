// resources/js/modules/i18n.js
/**
 * Module Internationalisation (i18n)
 * - Charge les fichiers JSON depuis /locales/{locale}.json
 * - Applique les traductions sur les éléments DOM [data-i18n]
 * - Expose la méthode globale t('key.nested') pour usage en JS
 * - Lit le paramètre ?lang= dans l'URL (lien de parrainage)
 */

let currentLocale = 'fr';
let translations = {};
const SUPPORTED_LOCALES = ['fr', 'en'];

export async function initI18n() {
    // 1. Priorité : paramètre URL (lien de parrainage avec ?lang=en)
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');

    // 2. Fallback : localStorage
    const savedLang = localStorage.getItem('user_locale');

    // 3. Fallback : langue du navigateur
    const browserLang = navigator.language?.split('-')[0];

    // Résolution finale
    const resolved = urlLang || savedLang || browserLang || 'fr';
    currentLocale = SUPPORTED_LOCALES.includes(resolved) ? resolved : 'fr';

    // Persiste le choix
    if (urlLang && SUPPORTED_LOCALES.includes(urlLang)) {
        localStorage.setItem('user_locale', urlLang);
    }

    await loadTranslations(currentLocale);
    applyTranslations();

    console.log(`[i18n] Langue active : ${currentLocale}`);
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
