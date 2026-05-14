// resources/js/core/timers.js
/**
 * Gestionnaire centralisé des timers (setInterval/setTimeout).
 * Prévient les fuites de mémoire lors de la navigation SPA.
 */

const appTimers = {};

export function setAppTimer(name, intervalId) {
    clearAppTimer(name); // Nettoie au cas où
    appTimers[name] = intervalId;
}

export function clearAppTimer(name) {
    if (appTimers[name]) {
        clearInterval(appTimers[name]);
        clearTimeout(appTimers[name]);
        delete appTimers[name];
    }
}

export function clearAllTimers() {
    Object.keys(appTimers).forEach(name => clearAppTimer(name));
}
