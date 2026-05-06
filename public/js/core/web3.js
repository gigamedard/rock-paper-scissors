// js/core/web3.js
import { CONFIG } from '../config.js';

export class Web3Provider {
    constructor() {
        this.instance = null;
        this.contract = null;
        this.isMock = false;
        this.userAddress = null;
    }

    async initialize(contractAddress, abi) {
        const urlParams = new URLSearchParams(window.location.search);
        const forceMock = urlParams.has('mock');
        const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';

        if (forceMock) {
            console.log("Web3: Initializing Mock Mode");
            this.instance = new Web3(CONFIG.HARDHAT_RPC);
            this.isMock = true;
        } else if (window.ethereum || window.avalanche) {
            console.log("Web3: Initializing Provider Mode");
            this.instance = new Web3(window.ethereum || window.avalanche);
        } else {
            throw new Error("No Web3 provider detected.");
        }

        if (contractAddress && abi) {
            this.contract = new this.instance.eth.Contract(abi, contractAddress);
        }
    }

    async getAccount() {
        if (!this.instance) throw new Error("Web3 not initialized");
        if (this.isMock) {
            return CONFIG.MOCK_WALLET_ADDRESS;
        }
        const accounts = await this.instance.eth.requestAccounts();
        return accounts[0];
    }

    async signMessage(message, account) {
        if (!this.instance) throw new Error("Web3 not initialized");
        if (this.isMock) {
            // Logic for mock signature (or skip if backend allows)
            console.warn("Mock Signature used: TEST_BYPASS");
            return "TEST_BYPASS";
        }
        return await this.instance.eth.personal.sign(message, account, "");
    }
}
