// resources/js/modules/game.js
import { BrowserProvider, Contract, parseUnits, parseEther } from 'ethers';
import { secureFetch } from '../core/api.js';
import { parseRpcError } from '../core/auth.js';

const gameState = {
    preMoves: [],
    pendingClaim: null,
    config: {},
    contractAddress: '0x5FbDB2315678afecb367f032d93F642f64180aa3'
};

export function initGame() {
    console.log("[Game] Initialisation du module Game");
    
    // Bind UI buttons using direct properties to prevent double binding
    const betInput = document.getElementById('base-bet-input');
    if (betInput) {
        betInput.onchange = updateFeeDisplay;
    }
    
    const startBtn = document.getElementById('start-session-btn');
    if (startBtn) {
        startBtn.onclick = startSession;
    }
    
    const claimBtn = document.getElementById('claim-btn');
    if (claimBtn) {
        claimBtn.onclick = claim;
    }

    const joinBtn = document.getElementById('join-btn');
    if (joinBtn) {
        joinBtn.onclick = () => {
            window.userState.status = 'setup';
            updateUI();
        };
    }

    // Bind strategy pre-move buttons (ensuring no duplicate click listeners)
    const btnRock = document.getElementById('move-rock');
    if (btnRock) {
        btnRock.onclick = () => addMove('rock');
    }
    const btnPaper = document.getElementById('move-paper');
    if (btnPaper) {
        btnPaper.onclick = () => addMove('paper');
    }
    const btnScissors = document.getElementById('move-scissors');
    if (btnScissors) {
        btnScissors.onclick = () => addMove('scissors');
    }
    const btnReset = document.getElementById('move-reset');
    if (btnReset) {
        btnReset.onclick = () => clearMoves();
    }

    // Bind event listeners from Echo (guarding against double-binding)
    if (!window.gameListenersInitialized) {
        window.gameListenersInitialized = true;

        window.addEventListener('game:balanceUpdated', (e) => {
            const newBalance = parseFloat(e.detail.balance || e.detail.user?.balance) || 0;
            animateValue('balance-val', window.userState.balance || 0, newBalance, 4);
            window.userState.balance = newBalance;
        });

        window.addEventListener('game:userBalanceUpdated', (e) => {
            const newBalance = parseFloat(e.detail.user?.balance) || 0;
            animateValue('balance-val', window.userState.balance || 0, newBalance, 4);
            window.userState.balance = newBalance;
        });

        window.addEventListener('game:sessionStarted', (e) => {
            window.userState.balance = parseFloat(e.detail.initial_balance) || 0;
            window.userState.status = 'in_pool';
            showCombatOverlay("RECHERCHE D'ADVERSAIRES...");
            updateUI();
        });

        window.addEventListener('game:sessionFinished', (e) => {
            window.userState.status = 'stopped';
            hideCombatOverlay();
            
            if (e.detail.signature) {
                gameState.pendingClaim = {
                    amount: e.detail.user.balance,
                    signature: e.detail.signature
                };
                const claimSection = document.getElementById('claim-section');
                if (claimSection) {
                    claimSection.style.display = 'block';
                    document.getElementById('claim-amount-display').innerText = parseFloat(e.detail.user.balance).toFixed(4);
                }
            }

            if (e.detail.reason === 'SUCCESS') {
                const profit = (parseFloat(e.detail.user.balance) - parseFloat(window.userState.session_start_balance || 0)).toFixed(4);
                addToFeed(`🏆 VICTORY! Final Balance: ${parseFloat(e.detail.user.balance).toFixed(4)} ETH (Profit: ${profit})`, "var(--success)");
            } else {
                addToFeed(`💀 SESSION ENDED: ${e.detail.reason}`, "var(--accent)");
            }
            updateUI();
        });

        window.addEventListener('game:fightResult', (e) => {
            triggerClash(e.detail.my_move, e.detail.opponent_move, e.detail.result, e.detail.delta);
            animateValue('balance-val', window.userState.balance || 0, e.detail.current_balance, 4);
            window.userState.balance = e.detail.current_balance;
        });

        window.addEventListener('game:martingaleUpdated', (e) => {
            window.userState.bet_amount = parseFloat(e.detail.next_bet) || 0;
            addToFeed(`📈 Martingale : Prochaine mise à ${window.userState.bet_amount.toFixed(4)} ETH`, "var(--primary)");
            updateUI();
        });

        window.addEventListener('game:poolEmitted', (e) => {
            if (window.userState?.walletAddress && e.detail.users.includes(window.userState.walletAddress.toLowerCase())) {
                showCombatOverlay(`POOL FOUND`);
                addToFeed(`⚔️ Match Found! Entering Pool`, "var(--primary)");
            }
        });

        window.addEventListener('auth:success', () => {
            fetchUserStatus();
        });
    }

    // Make functions globally available for inline HTML onclick handlers (temporary until HTML is cleaned)
    window.addMove = addMove;
    window.clearMoves = clearMoves;

    fetchConfig();
    fetchUserStatus();
    renderSlots();
}

async function fetchUserStatus() {
    if (!window.userState?.id) return;
    try {
        const res = await secureFetch('/user/status');
        if (res.ok) {
            const data = await res.json();
            window.userState.balance = data.balance;
            window.userState.bet_amount = data.bet_amount;
            // Ne pas écraser l'état 'setup' local si le serveur dit 'available'
            if (window.userState.status !== 'setup' || data.status !== 'available') {
                window.userState.status = data.status;
            }
            
            if (data.payout_signature) {
                gameState.pendingClaim = { amount: data.balance, signature: data.payout_signature };
            } else {
                gameState.pendingClaim = null;
            }
            
            const claimSection = document.getElementById('claim-section');
            if (claimSection) {
                if (gameState.pendingClaim && window.userState.status === 'stopped') {
                    claimSection.style.display = 'block';
                    document.getElementById('claim-amount-display').innerText = parseFloat(data.balance).toFixed(4);
                } else {
                    claimSection.style.display = 'none';
                }
            }
            updateUI();
        }
    } catch (e) {
        console.error("Failed to fetch user status", e);
    }
}

async function fetchConfig() {
    try {
        // secureFetch uses /api prefix automatically
        const res = await fetch('/api/artefacts'); 
        if (res.ok) {
            const data = await res.json();
            gameState.config.security_coefficient = data.security_coefficient;
            gameState.config.smart_contract_fee_percentage = data.smart_contract_fee_percentage;
            gameState.contractAddress = data.address || gameState.contractAddress;
            updateFeeDisplay();
        }
    } catch (e) {
        console.error("Failed to fetch config", e);
    }
}

function updateFeeDisplay() {
    const betInput = document.getElementById('base-bet-input');
    if (!betInput) return;

    const bet = parseFloat(betInput.value) || 0;
    const coeff = gameState.config.security_coefficient || 1;
    const feePct = gameState.config.smart_contract_fee_percentage || 0;
    
    const stake = bet * coeff;
    const fee = stake * (feePct / 100);
    const total = stake + fee;

    const elCoeff = document.getElementById('display-coeff');
    const elStake = document.getElementById('display-stake');
    const elFeePct = document.getElementById('display-fee-percent');
    const elFeeAmt = document.getElementById('display-fee-amount');
    const elTotal = document.getElementById('display-total-deposit');

    if (elCoeff) elCoeff.innerText = coeff;
    if (elStake) elStake.innerText = stake.toFixed(4) + " ETH";
    if (elFeePct) elFeePct.innerText = feePct;
    if (elFeeAmt) elFeeAmt.innerText = fee.toFixed(4) + " ETH";
    if (elTotal) elTotal.innerText = total.toFixed(4) + " ETH";
}

function getIcon(move) {
    const icons = { 'rock': '✊', 'paper': '🖐️', 'scissors': '✌️' };
    return icons[move];
}

export function addMove(move) {
    if (gameState.preMoves.length < 10) {
        gameState.preMoves.push(move);
        renderSlots();
    }
}

export function clearMoves() {
    gameState.preMoves = [];
    renderSlots();
}

function renderSlots() {
    const grid = document.getElementById('premove-slots');
    if (!grid) return;
    grid.innerHTML = '';
    for (let i = 0; i < 10; i++) {
        const slot = document.createElement('div');
        slot.className = 'move-slot' + (gameState.preMoves[i] ? ' filled' : '');
        slot.innerText = getIcon(gameState.preMoves[i]) || '';
        grid.appendChild(slot);
    }
}

async function startSession() {
    if (gameState.preMoves.length < 10) {
        alert("Please select 10 pre-moves!");
        return;
    }

    const bet = document.getElementById('base-bet-input').value;
    const targetQ = document.getElementById('target-q-input') ? parseFloat(document.getElementById('target-q-input').value) : 2.0;
    const cooldownTime = document.getElementById('cooldown-input') ? parseInt(document.getElementById('cooldown-input').value, 10) : 1440;
    const btn = document.getElementById('start-session-btn');
    btn.innerText = "UPLOADING TO IPFS...";
    btn.disabled = true;

    try {
        // 1. IPFS Upload
        const ipfsRes = await secureFetch('/ipfs/upload', {
            method: 'POST',
            body: JSON.stringify({ data: { moves: gameState.preMoves } })
        });
        const ipfsData = await ipfsRes.json();
        const cid = ipfsData.IpfsHash;
        addToFeed("⛓️ Requesting Blockchain Stake...", "var(--primary)");

        // 2. Blockchain Transaction
        const provider = new BrowserProvider(window.ethereum);
        const signer = await provider.getSigner();
        
        const securityCoefficient = gameState.config.security_coefficient; 
        const feePercentage = gameState.config.smart_contract_fee_percentage;
        
        const stakeWei = parseUnits((parseFloat(bet) * securityCoefficient).toFixed(18), 18);
        const feeWei = (stakeWei * BigInt(Math.round(feePercentage * 100))) / BigInt(10000);
        const amountToSendWei = stakeWei + feeWei;
        const baseBetWei = parseUnits(parseFloat(bet).toFixed(18), 18);
        
        const abi = ["function submitPremoveCID(uint256 baseBet, string cid) external payable"];
        const contract = new Contract(gameState.contractAddress, abi, signer);
        
        const tx = await contract.submitPremoveCID(baseBetWei, cid, {
            value: amountToSendWei,
            gasLimit: 500000
        });
        
        addToFeed("⏳ Transaction pending: " + tx.hash.substring(0,10) + "...", "var(--primary)");
        await tx.wait();
        addToFeed("✅ Stake confirmed on-chain!", "var(--success)");

        // 3. Store Pre-moves
        const joinRes = await secureFetch('/user/pre-moves', {
            method: 'POST',
            body: JSON.stringify({
                pre_moves: gameState.preMoves,
                user_id: window.userState.id,
                bet_amount: bet,
                cid: cid,
                target_q: targetQ,
                cooldown_time: cooldownTime
            })
        });

        if (joinRes.ok) {
            window.userState.status = 'dashboard';
            window.userState.bet_amount = parseFloat(bet) || 0;
            window.userState.session_start_balance = parseFloat(window.userState.balance) || 0;
            updateUI();
            addToFeed("🚀 Session Initialized. Waiting for pool...", "var(--primary)");
        } else {
            throw new Error("Failed to join pool");
        }
    } catch (error) {
        console.error(error);
        alert("Error: " + parseRpcError(error));
        addToFeed("❌ Error: " + parseRpcError(error), "var(--accent)");
    } finally {
        btn.innerText = "INITIALIZE BATTLE SEQUENCE";
        btn.disabled = false;
    }
}

async function claim() {
    if (!gameState.pendingClaim) return;
    
    const btn = document.getElementById('claim-btn');
    btn.innerText = "PROCESSING CLAIM...";
    btn.disabled = true;

    try {
        const provider = new BrowserProvider(window.ethereum);
        const signer = await provider.getSigner();
        
        const abi = ["function claimAndExit(uint256 amount, bytes signature) external"];
        const contract = new Contract(gameState.contractAddress, abi, signer);
        
        const amountWei = parseEther(gameState.pendingClaim.amount.toString());
        
        const tx = await contract.claimAndExit(amountWei, gameState.pendingClaim.signature);
        addToFeed("⏳ Transaction sent: " + tx.hash.substring(0,10) + "...", "var(--primary)");
        
        await tx.wait();
        addToFeed("✅ Funds claimed successfully!", "var(--success)");
        document.getElementById('claim-section').style.display = 'none';
        gameState.pendingClaim = null;

    } catch (error) {
        console.error(error);
        alert("Claim failed: " + parseRpcError(error));
        addToFeed("❌ Claim Failed: " + parseRpcError(error), "var(--accent)");
    } finally {
        btn.innerText = "CLAIM & EXIT ARENA";
        btn.disabled = false;
    }
}

export function updateUI() {
    if (!window.userState) return;

    const views = {
        'disconnected': document.getElementById('view-disconnected'),
        'setup': document.getElementById('view-setup'),
        'dashboard': document.getElementById('view-dashboard')
    };

    Object.values(views).forEach(v => { if(v) v.style.display = 'none' });
    
    let activeView = null;
    if (window.userState.status === 'disconnected' || !window.userState.id) {
        activeView = views['disconnected'];
    } else if (window.userState.status === 'setup') {
        activeView = views['setup'];
    } else {
        activeView = views['dashboard'];
    }

    if (activeView) {
        activeView.style.display = (activeView === views['dashboard']) ? 'grid' : 'block';
        if (activeView === views['setup']) {
            applyActiveLimits();
        }
    }

    const connectBtn = document.getElementById('connect-btn');
    const connectedUser = document.getElementById('connected-user');

    if (!window.userState.id) {
        if(connectBtn) connectBtn.style.display = 'block';
        if(connectedUser) connectedUser.style.display = 'none';
    } else {
        if(connectBtn) connectBtn.style.display = 'none';
        if(connectedUser) connectedUser.style.display = 'flex';
        
        const walletDisplay = document.getElementById('user-wallet');
        if (walletDisplay && window.userState.walletAddress) {
            const w = window.userState.walletAddress;
            walletDisplay.innerText = w.substring(0, 6) + "..." + w.substring(w.length - 4);
        }
        
        const balance = parseFloat(window.userState.balance) || 0;
        const bet = parseFloat(window.userState.bet_amount) || 0;
        
        const balanceEl = document.getElementById('balance-val');
        if(balanceEl && !balanceEl.classList.contains('animating')) {
            balanceEl.innerHTML = balance.toFixed(4) + ' <span class="unit">ETH</span>';
        }
        
        const betEl = document.getElementById('bet-val');
        if(betEl) betEl.innerHTML = bet.toFixed(4) + ' <span class="unit">ETH</span>';

        if (activeView === views['dashboard']) {
            const joinBtn = document.getElementById('join-btn');
            const statusText = document.getElementById('status-text');

            if (window.userState.status === 'dashboard' || window.userState.status === 'available') {
                if (joinBtn) joinBtn.style.display = 'block';
                if (statusText) {
                    statusText.innerText = "Available";
                    statusText.className = "battle-status-tag status-online";
                }
                hideCombatOverlay();
            } else if (window.userState.status === 'stopped') {
                if (joinBtn) joinBtn.style.display = 'block';
                if (statusText) {
                    statusText.innerText = "Session terminée — Relancer ?";
                    statusText.className = "battle-status-tag status-busy";
                }
                hideCombatOverlay();
            } else if (window.userState.status === 'waiting' || window.userState.status === 'in_pool') {
                if (joinBtn) joinBtn.style.display = 'none';
                if (statusText) {
                    statusText.innerText = "Waiting for Match";
                    statusText.className = "battle-status-tag status-busy";
                }
                showCombatOverlay("WAITING FOR OPPONENT...");
            } else if (window.userState.status === 'in_fight') {
                if (joinBtn) joinBtn.style.display = 'none';
                if (statusText) {
                    statusText.innerText = "In Combat";
                    statusText.className = "battle-status-tag status-busy";
                }
                showCombatOverlay("COMBAT IN PROGRESS");
            }
        }
    }
}

export function addToFeed(msg, color = "var(--text-dim)") {
    const feed = document.getElementById('feed');
    if(!feed) return;
    const time = new Date().toLocaleTimeString();
    const entry = document.createElement('div');
    entry.className = 'feed-entry';
    entry.style.borderLeftColor = color;
    entry.innerHTML = `<span class="feed-time">${time}</span><span style="color: ${color}">${msg}</span>`;
    feed.prepend(entry);
    if (feed.children.length > 10) feed.removeChild(feed.lastChild);
}

function animateValue(id, start, end, decimals) {
    const obj = document.getElementById(id);
    if (!obj) return;
    obj.classList.add('animating');
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
            obj.classList.remove('animating');
            obj.classList.add('hud-value-update');
            setTimeout(() => obj.classList.remove('hud-value-update'), 500);
        }
    };
    window.requestAnimationFrame(step);
}

function showCombatOverlay(text) {
    const overlay = document.getElementById('combat-overlay');
    const combatText = document.getElementById('combat-text');
    if(combatText) combatText.innerText = text;
    if(overlay) overlay.style.display = 'flex';
}

function hideCombatOverlay() {
    const overlay = document.getElementById('combat-overlay');
    if(overlay) overlay.style.display = 'none';
}

function triggerClash(myMove, opponentMove, result, delta) {
    const overlay = document.getElementById('combat-overlay');
    if(!overlay) return;
    const combatText = document.getElementById('combat-text');
    showCombatOverlay("ROUND CLASH!");
    const clashDiv = document.createElement('div');
    clashDiv.className = 'clash-container fade-in';
    clashDiv.innerHTML = `<div class="clash-side left">${getIcon(myMove)}</div><div class="clash-vs">VS</div><div class="clash-side right">${getIcon(opponentMove)}</div>`;
    overlay.appendChild(clashDiv);
    
    const impact = document.getElementById('impact-flash');
    if(impact) {
        impact.classList.add('active');
        setTimeout(() => impact.classList.remove('active'), 300);
    }
    
    triggerShake('combat-overlay');
    setTimeout(() => {
        const color = result === 'win' ? 'var(--success)' : (result === 'draw' ? 'var(--primary)' : 'var(--accent)');
        if(combatText) {
            combatText.style.color = color;
            combatText.innerText = result.toUpperCase() + " (" + delta + " ETH)";
        }
        clashDiv.classList.add('fade-out');
        setTimeout(() => overlay.removeChild(clashDiv), 500);
    }, 1500);
}

function triggerShake(id) {
    const obj = document.getElementById(id);
    if(!obj) return;
    obj.style.animation = 'none';
    setTimeout(() => obj.style.animation = 'shake 0.5s cubic-bezier(.36,.07,.19,.97) both', 10);
}

async function applyActiveLimits() {
    if (!window.userState?.id) return;
    try {
        const res = await secureFetch('/user');
        if (res.ok) {
            const data = await res.json();
            const limits = data.active_limits;
            if (!limits) return;

            const betSelect = document.getElementById('base-bet-input');
            if (betSelect) {
                let firstValid = null;
                let currentValValid = false;
                Array.from(betSelect.options).forEach(opt => {
                    const val = parseFloat(opt.value);
                    if (val > limits.max_base_bet) {
                        opt.disabled = true;
                    } else {
                        opt.disabled = false;
                        if (firstValid === null) firstValid = opt.value;
                        if (betSelect.value === opt.value) currentValValid = true;
                    }
                });
                if (!currentValValid && firstValid !== null) {
                    betSelect.value = firstValid;
                    updateFeeDisplay();
                }
            }

            const qSelect = document.getElementById('target-q-input');
            if (qSelect) {
                let firstValid = null;
                let currentValValid = false;
                Array.from(qSelect.options).forEach(opt => {
                    const val = parseFloat(opt.value);
                    if (val > limits.max_q) {
                        opt.disabled = true;
                    } else {
                        opt.disabled = false;
                        if (firstValid === null) firstValid = opt.value;
                        if (qSelect.value === opt.value) currentValValid = true;
                    }
                });
                if (!currentValValid && firstValid !== null) {
                    qSelect.value = firstValid;
                }
            }

            const cooldownSelect = document.getElementById('cooldown-input');
            if (cooldownSelect) {
                let firstValid = null;
                let currentValValid = false;
                Array.from(cooldownSelect.options).forEach(opt => {
                    const val = parseInt(opt.value, 10);
                    if (val < limits.min_cooldown) {
                        opt.disabled = true;
                    } else {
                        opt.disabled = false;
                        if (firstValid === null) firstValid = opt.value;
                        if (cooldownSelect.value === opt.value) currentValValid = true;
                    }
                });
                if (!currentValValid && firstValid !== null) {
                    cooldownSelect.value = firstValid;
                }
            }
        }
    } catch (e) {
        console.error("Failed to fetch and apply active user limits", e);
    }
}
