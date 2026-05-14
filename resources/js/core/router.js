// resources/js/core/router.js
/**
 * Routeur Vanilla basé sur le Hash de l'URL.
 */
import { clearAllTimers } from './timers.js';

const routes = {
    '': 'language-page', // Default to language or login if not auth
    '#/': 'autoplay-page', 
    '#/marketplace': 'marketplace-page',
    '#/referral': 'referral-dashboard-page',
    '#/influencer': 'influencer-dashboard-page',
};

export function initRouter() {
    window.addEventListener('hashchange', handleRouteChange);
    handleRouteChange(); // Trigger initial route
}

function handleRouteChange() {
    const hash = window.location.hash;
    const pageId = routes[hash] || 'autoplay-page'; // Fallback
    
    showPage(pageId);
    
    // Dispatch event to notify modules that route changed
    window.dispatchEvent(new CustomEvent('route:changed', { detail: { pageId, hash } }));
}

export function showPage(pageId) {
    // Nettoyer les timers en boucle pour éviter les fuites (ex: actualisation stats)
    clearAllTimers();

    document.querySelectorAll('.page').forEach(page => {
        page.classList.remove('active');
        page.style.display = 'none'; // Ensure it's hidden
    });
    
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
        targetPage.style.display = 'flex'; // Layout handled by CSS mostly, but flex is standard here
    } else {
        console.warn(`[Router] Page introuvable : ${pageId}`);
    }
}

export function navigateTo(hash) {
    window.location.hash = hash;
}
