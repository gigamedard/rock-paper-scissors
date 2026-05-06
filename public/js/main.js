// js/main.js
import { CONFIG, MOVES } from './config.js';
import { ApiClient } from './core/api.js';
import { Web3Provider } from './core/web3.js';
import { UIManager } from './ui/ui-manager.js';

export class GameApp {
    constructor() {
        this.web3 = new Web3Provider();
        this.state = {
            user: null,
            moves: [],
            bet: 0.05,
            contractConfig: null
        };
    }

    async init() {
        console.log("Initializing DBS Arena...");
        
        // Load User from LocalStorage
        const savedUser = localStorage.getItem('user');
        if (savedUser) {
            this.state.user = JSON.parse(savedUser);
        }

        try {
            // Load Contract Artefacts
            const artefacts = await ApiClient.getArtefacts();
            this.state.contractConfig = artefacts;

            // Initialize Web3
            await this.web3.initialize(artefacts.address, artefacts.abi);
            
            // Check auth status
            if (this.state.user) {
                this.setupEcho();
                this.startPolling();
                UIManager.showPage('autoplay-page');
            } else {
                UIManager.showPage('lang-page');
            }
        } catch (error) {
            console.error("Initialization failed:", error);
            // alert("Failed to connect to the arena. Please check your connection.");
        }
    }

    async setLanguage(lang) {
        console.log("Setting language:", lang);
        // In a full refactor, we would load i18n here.
        // For now, let's just proceed to login.
        await this.login();
    }

    async login() {
        if (!this.state.contractConfig) {
            alert("Veuillez attendre que l'arène soit prête...");
            return;
        }
        try {
            const address = await this.web3.getAccount();
            
            // 1. Get Challenge
            const msgRes = await ApiClient.secureFetch('/wallet/generate-message', {
                method: 'POST',
                body: JSON.stringify({ wallet_address: address })
            });
            const { message } = await msgRes.json();

            // 2. Sign
            const signature = await this.web3.signMessage(message, address);

            // 3. Verify
            const userData = await ApiClient.login(address, signature, message);
            this.state.user = userData.user;

            this.setupEcho();
            this.startPolling();
            UIManager.showPage('autoplay-page');
        } catch (error) {
            console.error("Login failed:", error);
            alert("Could not join the tournament: " + error.message);
        }
    }

    addMove(moveId) {
        if (this.state.moves.length >= 100) return;
        this.state.moves.push(moveId);
        this.renderMoves();
    }

    renderMoves() {
        const display = document.getElementById('selected-moves-display');
        display.innerHTML = '';
        this.state.moves.forEach((m, idx) => {
            const span = document.createElement('span');
            span.className = 'glass-card';
            span.style.padding = '5px 10px';
            span.style.fontSize = '0.7rem';
            span.innerText = MOVES[m].name;
            display.appendChild(span);
        });
    }

    reviewGame() {
        const bet = parseFloat(document.getElementById('bet-amount-input').value);
        if (isNaN(bet) || bet <= 0) return alert("Mise invalide");
        if (this.state.moves.length < 2) return alert("Sélectionnez au moins 2 coups");

        this.state.bet = bet;
        document.getElementById('confirm-moves-count').innerText = this.state.moves.length;
        const total = bet * (this.state.contractConfig.security_coefficient || 1000);
        document.getElementById('confirm-total-deposit').innerText = total.toFixed(4);
        
        UIManager.showPage('confirmation-page');
    }

    async submitGame() {
        const overlay = document.getElementById('submission-overlay');
        overlay.style.display = 'block';
        
        try {
            // Step 1: Dummy CID for now (Mock mode pattern)
            UIManager.setSubmissionStep('step-ipfs', 'active');
            const cid = "bagaaier" + Math.random().toString(36).substring(2) + Math.random().toString(36).substring(2);
            await new Promise(r => setTimeout(r, 800));
            UIManager.setSubmissionStep('step-ipfs', 'done');

            // Step 2: Backend
            UIManager.setSubmissionStep('step-backend', 'active');
            const res = await ApiClient.secureFetch('/user/pre-moves', {
                method: 'POST',
                body: JSON.stringify({
                    user_id: this.state.user.id,
                    pre_moves: this.state.moves.map(m => MOVES[m].name),
                    cid: cid,
                    bet_amount: this.state.bet
                })
            });
            if (!res.ok) throw new Error("Backend rejection");
            UIManager.setSubmissionStep('step-backend', 'done');

            // Step 3: Blockchain
            UIManager.setSubmissionStep('step-blockchain', 'active');
            if (!this.web3.isMock) {
                const betWei = Web3.utils.toWei(this.state.bet.toString(), 'ether');
                const multiplier = 1000;
                const feeFactor = 0.975; // 1 - 2.5%
                const totalWei = Web3.utils.toWei(((this.state.bet * multiplier) / feeFactor).toFixed(6).toString(), 'ether');
                await this.web3.contract.methods.submitPremoveCID(betWei, cid).send({
                    from: await this.web3.getAccount(),
                    value: totalWei
                });
            } else {
                await new Promise(r => setTimeout(r, 1200));
            }
            UIManager.setSubmissionStep('step-blockchain', 'done');

            setTimeout(() => {
                overlay.style.display = 'none';
                UIManager.showPage('autoplay-page');
            }, 1000);

        } catch (error) {
            console.error("Submission error:", error);
            document.getElementById('submission-error').innerText = error.message;
            document.getElementById('submission-error').style.display = 'block';
            document.getElementById('submission-close-btn').style.display = 'block';
        }
    }

    closeSubmission() {
        document.getElementById('submission-overlay').style.display = 'none';
        document.getElementById('submission-error').style.display = 'none';
        document.getElementById('submission-close-btn').style.display = 'none';
    }

    setupEcho() {
        const token = ApiClient.getToken();
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: CONFIG.REVERB_KEY,
            wsHost: CONFIG.REVERB_HOST,
            wsPort: CONFIG.REVERB_PORT,
            forceTLS: false,
            authEndpoint: `${CONFIG.API_URL}/broadcasting/auth`,
            auth: { headers: { Authorization: `Bearer ${token}` } }
        });

        // Use the shorter channel name for simplicity and consistency
        window.Echo.private(`user.${this.state.user.id}`)
            .listen('MatchFound', (e) => {
                console.log("--- EVENT: MatchFound ---", e);
                UIManager.updateStatus('COMBAT IMMINENT...', 'warning');
                UIManager.showCombatAura();
            })
            .listen('SessionFinished', (e) => {
                console.log("--- EVENT: SessionFinished ---", e);
                UIManager.showCombatAura(false);
                alert(`Combat terminé ! Résultat: ${e.reason}`);
                this.syncHudWithBackend({ status: 'idle' });
            })
            .listen('PoolJoined', (e) => {
                console.log("--- EVENT: PoolJoined ---", e);
                UIManager.updateStatus("RECHERCHE D'ADVERSAIRES...", 'info');
            });
            
        console.log(`Web3: Listening on private channel: user.${this.state.user.id}`);
    }

    startPolling() {
        setInterval(async () => {
            if (!this.state.user) return;
            try {
                const res = await ApiClient.secureFetch('/user/polling-status');
                const data = await res.json();
                this.syncHudWithBackend(data);
            } catch (e) {}
        }, 3000);
    }

    syncHudWithBackend(data) {
        if (data.status === 'idle') {
            UIManager.updateBattleHud(null);
            return;
        }

        let hudData = {
            statusText: data.status === 'in_pool' ? "COMBAT IMMINENT..." : "RECHERCHE D'ADVERSAIRES...",
            progress: 50, // Static for now, can be improved
            balance: data.battle_balance || 0,
            isLoss: false
        };

        if (data.session_started) {
            hudData.statusText = "COMBAT EN COURS ⚔️";
            hudData.progress = 75;
        }

        UIManager.updateBattleHud(hudData);
    }

    goBack() {
        UIManager.showPage('autoplay-page');
    }
}
