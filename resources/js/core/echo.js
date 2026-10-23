// resources/js/core/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getAuthToken } from './api.js';

window.Pusher = Pusher;

export async function initEcho() {
    const token = getAuthToken();
    if (!token) {
        console.warn("[Echo] Impossible d'initialiser Echo sans token.");
        return;
    }

    if (window.echoInstance) {
        window.echoInstance.disconnect();
    }

    // Clé Reverb côté client. Elle était scrubée dans le repo (`***REMOVED***`)
    // → le WS ne s'authentifiait jamais → aucun événement (overlay « waiting »
    // figé). On la récupère depuis l'API (clé publique, le secret reste serveur).
    let appKey = '***REMOVED***';
    try {
        const kr = await fetch('/api/broadcasting-key', { headers: { 'Accept': 'application/json' } });
        if (kr.ok) {
            const kd = await kr.json();
            if (kd && kd.key) appKey = kd.key;
        } else {
            console.warn('[Echo] /api/broadcasting-key HTTP', kr.status);
        }
    } catch (e) {
        console.warn('[Echo] clé Reverb indisponible, fallback:', e?.message || e);
    }
    console.info('[Echo] clé Reverb (longueur):', String(appKey).length);

// Reverb : en https (mobile Tailscale), le WS passe par le proxy même origine
// (wss://<host>:<port>/app/<key>) — bp-proxy(-tls) route /app/ vers Reverb.
// En http desktop local : direct 127.0.0.1:8008 (comportement historique).
const IS_HTTPS = window.location.protocol === 'https:';

window.echoInstance = new Echo({
    broadcaster: 'reverb',
    key: appKey,
    wsHost: IS_HTTPS ? window.location.hostname : '127.0.0.1',
    wsPort: IS_HTTPS ? window.location.port || 443 : 8008,
    wssPort: IS_HTTPS ? window.location.port || 443 : 8008,
    forceTLS: IS_HTTPS,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
        auth: {
            headers: {
                Authorization: `Bearer ${token}`
            }
        }
    });

    const userId = window.userState?.id;
    if (!userId) return;

    console.log("[Echo] Connecté à Reverb pour l'utilisateur:", userId);

    // Écoute des événements privés
    window.echoInstance.private(`App.Models.User.${userId}`)
        .listen('.BalanceUpdated', (e) => {
            window.dispatchEvent(new CustomEvent('game:balanceUpdated', { detail: e }));
        })
        .listen('.UserBalanceUpdated', (e) => {
            window.dispatchEvent(new CustomEvent('game:userBalanceUpdated', { detail: e }));
        })
        .listen('.SessionFinished', (e) => {
            window.dispatchEvent(new CustomEvent('game:sessionFinished', { detail: e }));
        })
        .listen('.FightResult', (e) => {
            window.dispatchEvent(new CustomEvent('game:fightResult', { detail: e }));
        })
        .listen('.SessionStarted', (e) => {
            window.dispatchEvent(new CustomEvent('game:sessionStarted', { detail: e }));
        })
        .listen('.MartingaleUpdated', (e) => {
            window.dispatchEvent(new CustomEvent('game:martingaleUpdated', { detail: e }));
        });

    // Écoute des événements globaux
    window.echoInstance.channel('pools')
        .listen('.PoolEmitted', (e) => {
            window.dispatchEvent(new CustomEvent('game:poolEmitted', { detail: e }));
        });
}
