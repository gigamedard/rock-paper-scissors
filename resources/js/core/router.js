// resources/js/core/router.js
/**
 * Routeur Vanilla basé sur le Hash de l'URL.
 */
import { clearAllTimers } from './timers.js';

// Map hash -> id de la div dans index.html
const routes = {
    '':               'autoplay-page', // Chargement direct sans hash → Arène
    '#/':             'autoplay-page',
    '#/marketplace':  'marketplace-page',
    '#/referral':     'referral-dashboard-page',
    '#/influencer':   'influencer-dashboard-page',
};

// Map page-id -> display CSS approprié
const pageDisplay = {
    'autoplay-page':            'block',
    'marketplace-page':         'block',
    'referral-dashboard-page':  'block',
    'influencer-dashboard-page':'block',
};

export function initRouter() {
    window.addEventListener('hashchange', handleRouteChange);
    handleRouteChange(); // Déclencher la route initiale au chargement
}

function handleRouteChange() {
    const hash = window.location.hash;
    const pageId = routes[hash] !== undefined ? routes[hash] : (routes['#/' + hash.slice(2)] || 'autoplay-page');

    showPage(pageId);

    // Notifier les modules du changement de route
    window.dispatchEvent(new CustomEvent('route:changed', { detail: { pageId, hash } }));
}

export function showPage(pageId) {
    // Nettoyer les timers (évite les fuites mémoire lors de la nav)
    clearAllTimers();

    // Masquer toutes les pages
    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
        page.style.display = 'none';
    });

    // Afficher la page cible
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
        targetPage.style.display = pageDisplay[pageId] || 'block';
    } else {
        // Fallback : afficher l'arène si la page n'est pas trouvée
        console.warn(`[Router] Page introuvable : ${pageId}, fallback sur autoplay-page`);
        const fallback = document.getElementById('autoplay-page');
        if (fallback) {
            fallback.classList.add('active');
            fallback.style.display = 'block';
        }
    }
}

export function navigateTo(hash) {
    window.location.hash = hash;
}
