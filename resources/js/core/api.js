// resources/js/core/api.js
/**
 * Utilitaires pour les requêtes HTTP.
 */

const API_URL = '/api';

export function getAuthToken() {
    return localStorage.getItem('auth_token') || localStorage.getItem('token');
}

export async function secureFetch(endpoint, options = {}, retries = 2) {
    const token = getAuthToken();
    if (!token) {
        throw new Error(window.t ? window.t('errors.auth_missing') : "Jeton d'authentification non trouvé. Veuillez vous reconnecter.");
    }

    const headers = {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(options.headers || {})
    };

    const isFormData = options.body instanceof FormData;
    if (isFormData) {
        delete headers['Content-Type']; // Le navigateur gère le boundary
    }

    let lastError;
    for (let i = 0; i <= retries; i++) {
        try {
            const response = await fetch(`${API_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`, {
                ...options,
                headers
            });

            if (response.status === 401) {
                localStorage.removeItem('auth_token');
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                window.dispatchEvent(new CustomEvent('auth:expired'));
                throw new Error(window.t ? window.t('connect.session_expired') : "Session expirée. Veuillez vous reconnecter.");
            }

            if (!response.ok && response.status >= 500 && i < retries) {
                console.warn(`[API] Erreur 5xx, retry ${i + 1}/${retries} pour ${endpoint}`);
                await new Promise(r => setTimeout(r, 1000 * (i + 1))); // backoff
                continue;
            }

            return response;
        } catch (error) {
            lastError = error;
            // Retry on fetch/network failures
            if (i < retries && (error.name === 'TypeError' || error.message.includes('fetch'))) {
                console.warn(`[API] Erreur réseau, retry ${i + 1}/${retries} pour ${endpoint}`);
                await new Promise(r => setTimeout(r, 1000 * (i + 1))); // backoff
                continue;
            }
            throw error;
        }
    }
    
    // Si toutes les tentatives ont échoué, afficher un message d'erreur réseau convivial
    console.error(`[API] Toutes les tentatives (${retries}) ont échoué pour ${endpoint}`);
    throw new Error(window.t ? window.t('errors.network_issue') : lastError.message);
}
