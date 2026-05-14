// resources/js/core/echo.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { getAuthToken } from './api.js';

window.Pusher = Pusher;

export function initEcho() {
    const token = getAuthToken();
    if (!token) {
        console.warn("[Echo] Impossible d'initialiser Echo sans token.");
        return;
    }

    if (window.echoInstance) {
        window.echoInstance.disconnect();
    }

    window.echoInstance = new Echo({
        broadcaster: 'reverb',
        key: '***REMOVED***',
        wsHost: '127.0.0.1',
        wsPort: 8008,
        forceTLS: false,
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
