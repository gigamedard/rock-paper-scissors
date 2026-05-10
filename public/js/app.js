const app = {
    user: {
        id: null,
        wallet: null,
        balance: 0,
        bet_amount: 0.01,
        status: 'disconnected'
    },
    token: null,
    preMoves: [],
    pendingClaim: null,
    
    // Contract details
    contractAddress: '0x5FbDB2315678afecb367f032d93F642f64180aa3', // Default Hardhat addr

    init() {
        console.log("Battlepool UI Initialized");
        this.renderSlots();
        this.updateUI();
    },

    renderSlots() {
        const grid = document.getElementById('premove-slots');
        if (!grid) return;
        grid.innerHTML = '';
        for (let i = 0; i < 10; i++) {
            const slot = document.createElement('div');
            slot.className = 'move-slot' + (this.preMoves[i] ? ' filled' : '');
            slot.innerText = this.getIcon(this.preMoves[i]) || '';
            grid.appendChild(slot);
        }
    },

    getIcon(move) {
        const icons = { 'rock': '✊', 'paper': '🖐️', 'scissors': '✌️' };
        return icons[move];
    },

    addMove(move) {
        if (this.preMoves.length < 10) {
            this.preMoves.push(move);
            this.renderSlots();
        }
    },

    clearMoves() {
        this.preMoves = [];
        this.renderSlots();
    },

    async connect() {
        if (typeof window.ethereum === 'undefined') {
            alert("MetaMask is required!");
            return;
        }

        try {
            console.log("Connecting...");
            const provider = new ethers.BrowserProvider(window.ethereum);
            const signer = await provider.getSigner();
            this.user.wallet = await signer.getAddress();
            
            // 1. Get Challenge
            const challengeRes = await fetch('http://127.0.0.1:8001/api/wallet/generate-message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ wallet_address: this.user.wallet })
            });
            const { message } = await challengeRes.json();

            // 2. Sign Challenge
            const signature = await signer.signMessage(message);

            // 3. Verify Signature
            const verifyRes = await fetch('http://127.0.0.1:8001/api/wallet/verify-signature', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    wallet_address: this.user.wallet,
                    signature: signature
                })
            });
            const data = await verifyRes.json();
            
            if (!verifyRes.ok) throw new Error(data.message);

            this.user.id = data.user.id;
            this.user.balance = data.user.balance;
            this.token = data.token;
            this.user.status = 'dashboard';
            
            this.initEcho();
            this.updateUI();
            this.addToFeed("🔓 Authenticated: " + this.user.wallet.substring(0,6) + "...", "var(--success)");
            
        } catch (error) {
            console.error("Auth failed", error);
            alert("Authentication failed: " + error.message);
        }
    },

    initEcho() {
        if (typeof Echo === 'undefined') {
            console.error("Laravel Echo library not loaded!");
            return;
        }

        window.echoInstance = new Echo({
            broadcaster: 'reverb',
            key: '***REMOVED***',
            wsHost: '127.0.0.1',
            wsPort: 8008,
            forceTLS: false,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: 'http://127.0.0.1:8001/api/broadcasting/auth',
            auth: {
                headers: {
                    Authorization: `Bearer ${this.token}`
                }
            }
        });

        console.log("Echo Initialized for User:", this.user.id);

        window.echoInstance.private(`App.Models.User.${this.user.id}`)
            .listen('.BalanceUpdated', (e) => {
                const newBalance = parseFloat(e.balance) || 0;
                this.animateValue('balance-val', this.user.balance, newBalance, 4);
                this.user.balance = newBalance;
            })
            .listen('.UserBalanceUpdated', (e) => {
                const newBalance = parseFloat(e.user.balance) || 0;
                this.animateValue('balance-val', this.user.balance, newBalance, 4);
                this.user.balance = newBalance;
            })
            .listen('.SessionFinished', (e) => {
                console.log("🏁 Session Finished:", e);
                this.user.status = 'stopped';
                this.hideCombatOverlay();
                
                if (e.signature) {
                    this.pendingClaim = {
                        amount: e.user.balance, // Or final amount from event
                        signature: e.signature
                    };
                    document.getElementById('claim-section').style.display = 'block';
                }

                if (e.reason === 'SUCCESS') {
                    this.addToFeed(`🏆 VICTORY! Payout: ${e.final_q} ETH`, "var(--success)");
                } else {
                    this.addToFeed(`💀 RUIN: Capital exhausted.`, "var(--accent)");
                }
                this.updateUI();
            })
            .listen('.FightResult', (e) => {
                this.triggerClash(e.my_move, e.opponent_move, e.result, e.delta);
                this.animateValue('balance-val', this.user.balance, e.current_balance, 4);
                this.user.balance = e.current_balance;
            });

        window.echoInstance.channel('pools')
            .listen('.PoolEmitted', (e) => {
                if (e.users.includes(this.user.wallet.toLowerCase())) {
                    this.showCombatOverlay(`POOL FOUND`);
                    this.addToFeed(`⚔️ Match Found! Entering Pool`, "var(--primary)");
                }
            });
    },

    showSetup() {
        this.user.status = 'setup';
        this.updateUI();
    },

    async startSession() {
        if (this.preMoves.length < 10) {
            alert("Please select 10 pre-moves!");
            return;
        }

        const bet = document.getElementById('base-bet-input').value;
        const btn = document.getElementById('start-session-btn');
        btn.innerText = "UPLOADING TO IPFS...";
        btn.disabled = true;

        try {
            // 1. IPFS Upload
            const ipfsRes = await fetch('http://127.0.0.1:8001/api/ipfs/upload', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                },
                body: JSON.stringify({ data: { moves: this.preMoves } })
            });
            const ipfsData = await ipfsRes.json();
            const cid = ipfsData.IpfsHash;
            this.addToFeed("⛓️ Requesting Blockchain Stake...", "var(--primary)");

            // 2. Blockchain Transaction (submitPremoveCID)
            const provider = new ethers.BrowserProvider(window.ethereum);
            const signer = await provider.getSigner();
            
            // ALIGNMENT WITH BOT STANDARD (TB Protocol)
            // Stake = bet * 1000. Total to send = Stake + 10% margin = bet * 1100
            const securityCoefficient = 1000; 
            const totalCoefficient = 1100; // 1000 (stake) + 100 (10% margin)
            
            const amountToSendWei = ethers.parseUnits((parseFloat(bet) * totalCoefficient).toFixed(18), 18);
            const baseBetWei = ethers.parseUnits(parseFloat(bet).toFixed(18), 18);
            
            const abi = ["function submitPremoveCID(uint256 baseBet, string cid) external payable"];
            const contract = new ethers.Contract(this.contractAddress, abi, signer);
            
            const tx = await contract.submitPremoveCID(baseBetWei, cid, {
                value: amountToSendWei,
                gasLimit: 300000
            });
            
            this.addToFeed("⏳ Transaction pending: " + tx.hash.substring(0,10) + "...", "var(--primary)");
            await tx.wait();
            this.addToFeed("✅ Stake confirmed on-chain!", "var(--success)");

            // 3. Store Pre-moves & Join Backend
            const joinRes = await fetch('http://127.0.0.1:8001/api/user/pre-moves', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                },
                body: JSON.stringify({
                    pre_moves: this.preMoves,
                    user_id: this.user.id,
                    bet_amount: bet,
                    cid: cid
                })
            });

            if (joinRes.ok) {
                this.user.status = 'dashboard';
                this.updateUI();
                this.addToFeed("🚀 Session Initialized. Waiting for pool...", "var(--primary)");
            } else {
                throw new Error("Failed to join pool");
            }
        } catch (error) {
            console.error(error);
            alert("Error: " + error.message);
        } finally {
            btn.innerText = "INITIALIZE BATTLE SEQUENCE";
            btn.disabled = false;
        }
    },

    async claim() {
        if (!this.pendingClaim) return;
        
        const btn = document.getElementById('claim-btn');
        btn.innerText = "PROCESSING CLAIM...";
        btn.disabled = true;

        try {
            const provider = new ethers.BrowserProvider(window.ethereum);
            const signer = await provider.getSigner();
            
            // Minimal ABI for claimAndExit
            const abi = ["function claimAndExit(uint256 amount, bytes signature) external"];
            const contract = new ethers.Contract(this.contractAddress, abi, signer);
            
            // Amount must be in Wei
            const amountWei = ethers.parseEther(this.pendingClaim.amount.toString());
            
            const tx = await contract.claimAndExit(amountWei, this.pendingClaim.signature);
            this.addToFeed("⏳ Transaction sent: " + tx.hash.substring(0,10) + "...", "var(--primary)");
            
            await tx.wait();
            this.addToFeed("✅ Funds claimed successfully!", "var(--success)");
            document.getElementById('claim-section').style.display = 'none';
            this.pendingClaim = null;

        } catch (error) {
            console.error(error);
            alert("Claim failed: " + error.message);
        } finally {
            btn.innerText = "CLAIM & EXIT ARENA";
            btn.disabled = false;
        }
    },

    updateUI() {
        const views = {
            'disconnected': document.getElementById('view-disconnected'),
            'setup': document.getElementById('view-setup'),
            'dashboard': document.getElementById('view-dashboard')
        };

        // Hide all
        Object.values(views).forEach(v => v.style.display = 'none');
        
        // Show active
        if (views[this.user.status]) {
            views[this.user.status].style.display = (this.user.status === 'dashboard') ? 'grid' : 'block';
        }

        const connectBtn = document.getElementById('connect-btn');
        const connectedUser = document.getElementById('connected-user');

        if (this.user.status === 'disconnected') {
            connectBtn.style.display = 'block';
            connectedUser.style.display = 'none';
        } else {
            connectBtn.style.display = 'none';
            connectedUser.style.display = 'flex';
            document.getElementById('user-wallet').innerText = this.user.wallet.substring(0,6) + "..." + this.user.wallet.substring(38);
            
            const balance = parseFloat(this.user.balance) || 0;
            const bet = parseFloat(this.user.bet_amount) || 0;
            
            document.getElementById('balance-val').innerHTML = balance.toFixed(4) + ' <span class="unit">ETH</span>';
            document.getElementById('bet-val').innerHTML = bet.toFixed(4) + ' <span class="unit">ETH</span>';
        }
    },

    addToFeed(msg, color = "var(--text-dim)") {
        const feed = document.getElementById('feed');
        const time = new Date().toLocaleTimeString();
        const entry = document.createElement('div');
        entry.className = 'feed-entry';
        entry.style.borderLeftColor = color;
        entry.innerHTML = `<span class="feed-time">${time}</span><span style="color: ${color}">${msg}</span>`;
        feed.prepend(entry);
        if (feed.children.length > 10) feed.removeChild(feed.lastChild);
    },

    animateValue(id, start, end, decimals) {
        const obj = document.getElementById(id);
        if (!obj) return;
        const duration = 1000;
        let startTimestamp = null;
        const sVal = parseFloat(start) || 0;
        const eVal = parseFloat(end) || 0;
        
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            const val = progress * (eVal - sVal) + sVal;
            obj.innerHTML = val.toFixed(decimals) + ' <span class="unit">ETH</span>';
            if (progress < 1) window.requestAnimationFrame(step);
            else {
                obj.classList.add('hud-value-update');
                setTimeout(() => obj.classList.remove('hud-value-update'), 500);
            }
        };
        window.requestAnimationFrame(step);
    },

    showCombatOverlay(text) {
        const overlay = document.getElementById('combat-overlay');
        document.getElementById('combat-text').innerText = text;
        overlay.style.display = 'flex';
    },

    hideCombatOverlay() {
        document.getElementById('combat-overlay').style.display = 'none';
    },

    triggerClash(myMove, opponentMove, result, delta) {
        const overlay = document.getElementById('combat-overlay');
        const combatText = document.getElementById('combat-text');
        this.showCombatOverlay("ROUND CLASH!");
        const clashDiv = document.createElement('div');
        clashDiv.className = 'clash-container fade-in';
        clashDiv.innerHTML = `<div class="clash-side left">${this.getIcon(myMove)}</div><div class="clash-vs">VS</div><div class="clash-side right">${this.getIcon(opponentMove)}</div>`;
        overlay.appendChild(clashDiv);
        document.getElementById('impact-flash').classList.add('active');
        setTimeout(() => document.getElementById('impact-flash').classList.remove('active'), 300);
        this.triggerShake('combat-overlay');
        setTimeout(() => {
            const color = result === 'win' ? 'var(--success)' : (result === 'draw' ? 'var(--primary)' : 'var(--accent)');
            combatText.style.color = color;
            combatText.innerText = result.toUpperCase() + " (" + delta + " ETH)";
            clashDiv.classList.add('fade-out');
            setTimeout(() => overlay.removeChild(clashDiv), 500);
        }, 1500);
    },

    triggerShake(id) {
        const obj = document.getElementById(id);
        obj.style.animation = 'none';
        setTimeout(() => obj.style.animation = 'shake 0.5s cubic-bezier(.36,.07,.19,.97) both', 10);
    }
};

app.init();
