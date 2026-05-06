// js/core/api.js
import { CONFIG } from '../config.js';

export class ApiClient {
    static getToken() {
        return localStorage.getItem('api_token');
    }

    static async secureFetch(endpoint, options = {}) {
        const token = this.getToken();
        const url = endpoint.startsWith('http') ? endpoint : `${CONFIG.API_URL}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            ...options.headers
        };

        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }

        try {
            const response = await fetch(url, { ...options, headers });
            
            if (response.status === 401) {
                console.warn("Unauthorized. Clearing session.");
                localStorage.removeItem('user');
                localStorage.removeItem('api_token');
                window.dispatchEvent(new CustomEvent('auth-expired'));
                return response;
            }

            return response;
        } catch (error) {
            console.error("Fetch error:", error);
            throw error;
        }
    }

    static async login(walletAddress, signature, message) {
        const response = await this.secureFetch('/wallet/verify-signature', {
            method: 'POST',
            body: JSON.stringify({ wallet_address: walletAddress, signature, message })
        });
        const data = await response.json();
        if (data.token) {
            localStorage.setItem('api_token', data.token);
            localStorage.setItem('user', JSON.stringify(data.user));
        }
        return data;
    }

    static async getArtefacts() {
        const response = await this.secureFetch('/artefacts');
        return response.json();
    }
}
