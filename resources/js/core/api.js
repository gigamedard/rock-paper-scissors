// resources/js/core/api.js
/**
 * Utilitaires pour les requêtes HTTP.
 */

const API_URL = '/api';

export function getAuthToken() {
    return localStorage.getItem('auth_token') || localStorage.getItem('token');
}

export async function secureFetch(endpoint, options = {}) {
    const token = getAuthToken();
    if (!token) {
        throw new Error("Jeton d'authentification non trouvé. Veuillez vous reconnecter.");
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

    const response = await fetch(`${API_URL}${endpoint.startsWith('/') ? endpoint : '/' + endpoint}`, {
        ...options,
        headers
    });

    if (response.status === 401) {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.dispatchEvent(new CustomEvent('auth:expired'));
        throw new Error("Session expirée. Veuillez vous reconnecter.");
    }

    return response;
}
