// js/config.js
export const CONFIG = {
    API_URL: `http://${window.location.hostname}:8001/api`,
    REVERB_HOST: window.location.hostname,
    REVERB_PORT: 8008,
    REVERB_KEY: '***REMOVED***', 
    HARDHAT_RPC: "http://127.0.0.1:8545",
    MOCK_WALLET_ADDRESS: "0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266", // Account #0
    DEFAULT_SECURITY_COEFFICIENT: 1000,
    DEFAULT_FEE_PERCENTAGE: 2.5
};

export const MOVES = {
    1: { name: 'rock', icon: 'circle', color: '#ff4b2b' },
    2: { name: 'paper', icon: 'hand', color: '#24d1f0' },
    3: { name: 'scissors', icon: 'scissors', color: '#f6d32d' }
};
