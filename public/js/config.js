// js/config.js
// Mobile (Tailscale HTTPS) : le proxy bp-proxy route / vers l'app (8001) et /app/ vers Reverb (8008)
// sur la MEME origine -> API_URL relatif. Desktop local : comportement historique.
const IS_HTTPS = window.location.protocol === 'https:';
const API_BASE = IS_HTTPS
    ? `${window.location.origin}/api`
    : `http://${window.location.hostname}:8001/api`;

export const CONFIG = {
    API_URL: API_BASE,
    REVERB_HOST: window.location.hostname,
    REVERB_PORT: 8008,
    REVERB_KEY: '***REMOVED***', 
    HARDHAT_RPC: "http://127.0.0.1:8546",
    MOCK_WALLET_ADDRESS: "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266", // Account #0
    DEFAULT_SECURITY_COEFFICIENT: 1000,
    DEFAULT_FEE_PERCENTAGE: 2.5
};

export const MOVES = {
    1: { name: 'rock', icon: 'circle', color: '#ff4b2b' },
    2: { name: 'paper', icon: 'hand', color: '#24d1f0' },
    3: { name: 'scissors', icon: 'scissors', color: '#f6d32d' }
};
