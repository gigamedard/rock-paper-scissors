// resources/js/modules/game.js
import { Contract, parseUnits, parseEther } from 'ethers';
import { getProvider } from '../web3/web3-core.js';
import { secureFetch } from '../core/api.js';
import { parseRpcError } from '../core/auth.js';
import { t } from './i18n.js';
import { showToast } from '../core/toast.js';

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
            // Garde : empêcher si déjà en pool/en combat
            if (window.userState.status === 'in_pool' || window.userState.status === 'in_fight' || window.userState.status === 'waiting') {
                showToast("Vous êtes déjà dans une battle. Attendez la fin.", 'warn');
                return;
            }
            if (gameState.pendingClaim) {
                showToast(t('errors.claim_previous'), 'warn');
                return;
            }
            if (isUserInCooldown()) {
                const remaining = Math.ceil((new Date(window.userState.cooldown_until).getTime() - Date.now()) / 1000);
                showToast(t('errors.cooldown_active').replace(':remaining', remaining), 'warn');
                return;
            }
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

        // Fonction centralisée : applique le solde depuis n'importe quel objet user du payload
        function applyBalance(userObj) {
            if (!userObj) return;
            const b  = parseFloat(userObj.balance)        || 0;
            const bb = parseFloat(userObj.battle_balance)  || 0;
            const total = b + bb;
            const prev = parseFloat(window.userState._displayBalance) || 0;
            window.userState.balance         = b;
            window.userState.battle_balance  = bb;
            window.userState._displayBalance = total;
            if (userObj.bet_amount !== undefined) {
                window.userState.bet_amount = parseFloat(userObj.bet_amount) || 0;
            }
            // Ne pas écraser 'setup' local avec le status serveur (même logique que fetchUserStatus)
            if (userObj.status !== undefined && window.userState.status !== 'setup') {
                window.userState.status = userObj.status;
            }
            // Anime si la valeur a changé, sinon met à jour directement
            if (Math.abs(total - prev) > 0.0001) {
                animateValue('balance-val', prev, total, 4);
            } else {
                const el = document.getElementById('balance-val');
                if (el && !el.classList.contains('animating')) {
                    el.innerHTML = total.toFixed(4) + ' <span class="unit">ETH</span>';
                }
            }
            updateUI();
        }

        window.addEventListener('game:balanceUpdated', (e) => {
            applyBalance(e.detail.user || { balance: e.detail.balance, battle_balance: e.detail.battle_balance });
        });

        window.addEventListener('game:userBalanceUpdated', (e) => {
            applyBalance(e.detail.user);
        });

        window.addEventListener('game:sessionStarted', (e) => {
            window.userState.balance = parseFloat(e.detail.initial_balance) || 0;
            window.userState._displayBalance = window.userState.balance;
            window.userState.status = 'in_pool';
            showCombatOverlay("RECHERCHE D'ADVERSAIRES...");
            updateUI();
        });

        window.addEventListener('game:sessionFinished', (e) => {
            window.userState.status = 'stopped';
            hideCombatOverlay();
            
            if (e.detail.user) {
                window.userState.cooldown_until = e.detail.user.cooldown_until;
                window.userState.balance = parseFloat(e.detail.user.balance) || 0;
                window.userState.battle_balance = parseFloat(e.detail.user.battle_balance) || 0;
                window.userState._displayBalance = window.userState.balance + window.userState.battle_balance;
            }

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
                addToFeed(t('feed.victory', { balance: parseFloat(e.detail.user.balance).toFixed(4), profit: profit }), "var(--success)");
            } else {
                addToFeed(t('feed.session_ended', { reason: e.detail.reason }), "var(--accent)");
            }
            updateUI();
        });

        window.addEventListener('game:fightResult', (e) => {
            triggerClash(e.detail.my_move, e.detail.opponent_move, e.detail.result, e.detail.delta);
            // Utilise user.balance + user.battle_balance (valeurs correctes après transfert en DB)
            applyBalance(e.detail.user);
        });

        window.addEventListener('game:martingaleUpdated', (e) => {
            const nextBet = parseFloat(e.detail.next_bet) || 0;
            addToFeed(t('feed.martingale', { nextBet: nextBet.toFixed(4) }), "var(--primary)");
            // L'event contient aussi l'objet user complet → synchro balance + bet en même temps
            if (e.detail.user) {
                applyBalance(e.detail.user);
            } else {
                window.userState.bet_amount = nextBet;
                updateUI();
            }
        });

        window.addEventListener('game:poolEmitted', (e) => {
            if (window.userState?.walletAddress && e.detail.users.includes(window.userState.walletAddress.toLowerCase())) {
                window.userState.status = 'in_fight';
                showCombatOverlay("POOL FOUND — COMBAT EN COURS...");
                addToFeed(t('feed.match_found'), "var(--primary)");
                updateUI();
            }
        });

        window.addEventListener('auth:success', () => {
            fetchUserStatus();
        });

        window.addEventListener('app:refresh', async () => {
            const refreshBtn = document.getElementById('refresh-btn');
            const icon = refreshBtn?.querySelector('.refresh-icon');
            if (icon) {
                const currentRotation = parseInt(icon.dataset.rotation || '0') + 360;
                icon.dataset.rotation = currentRotation;
                icon.style.transform = `rotate(${currentRotation}deg)`;
            }
            addToFeed(t('feed.refreshing'), "var(--primary)");
            
            // 1. Status utilisateur (balance, cooldown, payout, etc.)
            await fetchUserStatus();
            
            // 2. Recharger la config (security_coefficient, fee, etc.)
            await fetchConfig();
            
            // 3. Recharger l'inventaire des cartes (via event pour le module marketplace)
            window.dispatchEvent(new CustomEvent('marketplace:refresh'));
            
            // 4. Recharger le leaderboard de parrainage
            window.dispatchEvent(new CustomEvent('referral:refresh'));
            
            // 5. Recharger le dashboard influenceur
            window.dispatchEvent(new CustomEvent('influencer:refresh'));
            
            // 6. Recharger les slots de pre-moves (UI locale)
            renderSlots();
            
            // 7. Mettre a jour l'UI
            updateUI();
            
            addToFeed(t('feed.status_updated'), "var(--success)");
        });
    }

    // Make functions globally available for inline HTML onclick handlers (temporary until HTML is cleaned)
    window.addMove = addMove;
    window.clearMoves = clearMoves;

    fetchConfig();
    fetchUserStatus();
    renderSlots();
    updateUI();

    // Polling de secours toutes les 30s pour maintenir la balance synchronisée
    // (en complément des événements WebSocket temps réel)
    if (!window._balancePollInterval) {
        window._balancePollInterval = setInterval(() => {
            if (window.userState?.id) fetchUserStatus();
        }, 30000);
    }

    if (!window._cooldownTickerInterval) {
        window._cooldownTickerInterval = setInterval(() => {
            if (isUserInCooldown()) {
                // Mettre à jour juste le texte du cooldown sans re-render complet
                // (évite le clignotement de l'overlay)
                const statusText = document.getElementById('status-text');
                const joinBtn = document.getElementById('join-btn');
                const remaining = Math.ceil((new Date(window.userState.cooldown_until).getTime() - Date.now()) / 1000);
                if (statusText) statusText.innerText = `Cooldown actif (${remaining}s)`;
                if (joinBtn) joinBtn.innerText = `COOLDOWN (${remaining}s)`;
            }
        }, 1000);
    }
}

async function fetchUserStatus() {
    if (!window.userState?.id) return;
    try {
        const res = await secureFetch('/user/status');
        if (res.ok) {
            const data = await res.json();
            // Main balance = balance + battle_balance (total funds in the system)
            const b = parseFloat(data.balance) || 0;
            const bb = parseFloat(data.battle_balance) || 0;
            window.userState.balance = b;
            window.userState.battle_balance = bb;
            window.userState._displayBalance = b + bb;
            window.userState.bet_amount = data.bet_amount;
            window.userState.cooldown_until = data.cooldown_until;
            // Ne pas écraser les états actifs locaux (in_pool, in_fight, waiting)
            // si le serveur dit 'available' (désynchronisation DB/blockchain possible)
            const activeLocalStatuses = ['in_pool', 'in_fight', 'waiting', 'setup'];
            if (activeLocalStatuses.includes(window.userState.status) && data.status === 'available') {
                // Garder le statut local, juste mettre à jour balance/cooldown
            } else {
                if (window.userState.status !== data.status) {
                    if (data.status === 'in_pool' || data.status === 'setup') {
                        gameState.hasClaimed = false;
                    }
                }
                window.userState.status = data.status;
            }
            
            // Start or Update Client-Driven Batch Engine (Stealth Tick)
            const intervalMs = data.client_batch_interval || 5000;
            if (!window._clientBatchEngineInterval || window._currentBatchIntervalMs !== intervalMs) {
                if (window._clientBatchEngineInterval) clearInterval(window._clientBatchEngineInterval);
                window._currentBatchIntervalMs = intervalMs;
                window._clientBatchEngineInterval = setInterval(() => {
                    if (window.userState?.id) {
                        // Fire-and-forget stealth tick to advance the matchmaking batches
                        secureFetch('/metrics/collect', { method: 'POST' }).catch(() => {});
                    }
                }, intervalMs);
            }
            
            if (data.payout_signature && !gameState.hasClaimed) {
                gameState.pendingClaim = { amount: b + bb, signature: data.payout_signature };
            } else {
                gameState.pendingClaim = null;
            }
            
            const claimSection = document.getElementById('claim-section');
            if (claimSection) {
                if (gameState.pendingClaim && window.userState.status === 'stopped') {
                    claimSection.style.display = 'block';
                    document.getElementById('claim-amount-display').innerText = (b + bb).toFixed(4);
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
    // Garde : empêcher de rejoindre si déjà en pool/en combat/en cooldown
    if (window.userState.status === 'in_pool' || window.userState.status === 'in_fight' || window.userState.status === 'waiting') {
        showToast("Vous êtes déjà dans une battle. Attendez la fin.", 'warn');
        return;
    }
    if (isUserInCooldown()) {
        const remaining = Math.ceil((new Date(window.userState.cooldown_until).getTime() - Date.now()) / 1000);
        showToast(`Cooldown actif (${remaining}s restantes).`, 'warn');
        return;
    }
    if (gameState.isStartingSession) {
        showToast("Session en cours de démarrage...", 'warn');
        return;
    }
    if (gameState.preMoves.length < 10) {
        showToast(t('errors.select_premoves'), 'warn');
        return;
    }

    const bet = document.getElementById('base-bet-input').value;
    const targetQ = document.getElementById('target-q-input') ? parseFloat(document.getElementById('target-q-input').value) : 2.0;
    const cooldownTime = document.getElementById('cooldown-input') ? parseInt(document.getElementById('cooldown-input').value, 10) : 1440;
    const btn = document.getElementById('start-session-btn');
    btn.innerText = "UPLOADING TO IPFS...";
    btn.disabled = true;
    gameState.isStartingSession = true;

    try {
        // 1. IPFS Upload
        const ipfsRes = await secureFetch('/ipfs/upload', {
            method: 'POST',
            body: JSON.stringify({ data: { moves: gameState.preMoves } })
        });
        const ipfsData = await ipfsRes.json();
        const cid = ipfsData.IpfsHash;
        addToFeed(t('feed.staking'), "var(--primary)");

        // 2. Blockchain Transaction
        const provider = await getProvider();
        const signer = await provider.getSigner();
        
        const securityCoefficient = gameState.config.security_coefficient; 
        const feePercentage = gameState.config.smart_contract_fee_percentage;
        
        const baseBetWei = parseUnits(bet, 18);
        const stakeWei = baseBetWei * BigInt(securityCoefficient);
        const feeWei = (stakeWei * BigInt(Math.round(feePercentage * 100))) / BigInt(10000);
        const amountToSendWei = stakeWei + feeWei;
        
        const abi = ["function submitPremoveCID(uint256 baseBet, string cid) external payable"];
        const contract = new Contract(gameState.contractAddress, abi, signer);
        
        const tx = await contract.submitPremoveCID(baseBetWei, cid, {
            value: amountToSendWei,
            gasLimit: 500000
        });
        
        addToFeed(t('feed.pending_tx', { hash: tx.hash.substring(0,10) }), "var(--primary)");
        await tx.wait();
        addToFeed(t('feed.stake_confirmed'), "var(--success)");

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
            window.userState.status = 'in_pool';
            window.userState.bet_amount = parseFloat(bet) || 0;
            window.userState.session_start_balance = parseFloat(window.userState.balance) || 0;
            // Afficher l'overlay IMMÉDIATEMENT — l'utilisateur est en pool
            showCombatOverlay("RECHERCHE D'ADVERSAIRES...");
            addToFeed(t('feed.session_initialized'), "var(--primary)");
            updateUI();
        } else {
            throw new Error("Failed to join pool");
        }
    } catch (error) {
        console.error(error);
        showToast(t('errors.error_prefix') + parseRpcError(error), 'error');
        addToFeed(t('feed.error', { error: parseRpcError(error) }), "var(--accent)");
    } finally {
        gameState.isStartingSession = false;
        // Ne pas réactiver le bouton si on est en pool — updateUI() gère l'état du bouton
        if (window.userState.status !== 'in_pool' && window.userState.status !== 'in_fight' && window.userState.status !== 'waiting') {
            btn.innerText = "INITIALIZE BATTLE SEQUENCE";
            btn.disabled = false;
        }
    }
}

async function claim() {
    if (!gameState.pendingClaim) return;
    
    const btn = document.getElementById('claim-btn');
    btn.innerText = "PROCESSING CLAIM...";
    btn.disabled = true;

    try {
        const provider = await getProvider();
        const signer = await provider.getSigner();
        
        const abi = ["function claimAndExit(uint256 amount, bytes signature) external"];
        const contract = new Contract(gameState.contractAddress, abi, signer);
        
        const amountWei = parseEther(gameState.pendingClaim.amount.toString());
        
        const tx = await contract.claimAndExit(amountWei, gameState.pendingClaim.signature);
        addToFeed(t('feed.tx_sent', { hash: tx.hash.substring(0,10) }), "var(--primary)");
        
        await tx.wait();
        addToFeed(t('feed.claim_success'), "var(--success)");
        gameState.hasClaimed = true;
        document.getElementById('claim-section').style.display = 'none';
        gameState.pendingClaim = null;

        // Fast poll for 10 seconds to wait for bridge sync and hide button automatically
        let attempts = 0;
        const syncInterval = setInterval(async () => {
            attempts++;
            await fetchUserStatus();
            if (!gameState.pendingClaim || attempts >= 5) {
                clearInterval(syncInterval);
            }
        }, 2000);

    } catch (error) {
        console.error(error);
        showToast(t('errors.claim_failed') + parseRpcError(error), 'error');
        addToFeed(t('feed.claim_failed', { error: parseRpcError(error) }), "var(--accent)");
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

    const connectionButtons = document.getElementById('connection-buttons');
    const connectedUser = document.getElementById('connected-user');

    if (!window.userState.id) {
        if(connectionButtons) connectionButtons.style.display = 'flex';
        if(connectedUser) connectedUser.style.display = 'none';
    } else {
        if(connectionButtons) connectionButtons.style.display = 'none';
        if(connectedUser) connectedUser.style.display = 'flex';
        
        const walletDisplay = document.getElementById('user-wallet');
        if (walletDisplay && window.userState.walletAddress) {
            const w = window.userState.walletAddress;
            walletDisplay.innerText = w.substring(0, 6) + "..." + w.substring(w.length - 4);
        }
        
        // Display total balance = balance + battle_balance
        const displayBalance = parseFloat(window.userState._displayBalance ?? (window.userState.balance + (window.userState.battle_balance || 0))) || 0;
        const bet = parseFloat(window.userState.bet_amount) || 0;
        
        const balanceEl = document.getElementById('balance-val');
        if(balanceEl && !balanceEl.classList.contains('animating')) {
            balanceEl.innerHTML = displayBalance.toFixed(4) + ' <span class="unit">ETH</span>';
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
                if (gameState.pendingClaim) {
                    if (statusText) {
                        statusText.innerText = "Gains en attente de réclamation";
                        statusText.className = "battle-status-tag status-busy";
                    }
                    if (joinBtn) {
                        joinBtn.innerText = "RÉCLAMER VOS GAINS D'ABORD";
                        joinBtn.disabled = true;
                        joinBtn.style.opacity = '0.5';
                        joinBtn.style.cursor = 'not-allowed';
                    }
                } else if (isUserInCooldown()) {
                    const remaining = Math.ceil((new Date(window.userState.cooldown_until).getTime() - Date.now()) / 1000);
                    if (statusText) {
                        statusText.innerText = `Cooldown actif (${remaining}s)`;
                        statusText.className = "battle-status-tag status-busy";
                    }
                    if (joinBtn) {
                        joinBtn.innerText = `COOLDOWN (${remaining}s)`;
                        joinBtn.disabled = true;
                        joinBtn.style.opacity = '0.5';
                        joinBtn.style.cursor = 'not-allowed';
                    }
                } else if (!isUserInCooldown()) {
                    if (statusText && !gameState.isStartingSession) {
                        statusText.innerText = "Session terminée — Relancer ?";
                        statusText.className = "battle-status-tag status-offline";
                    }
                    if (joinBtn && !gameState.isStartingSession) {
                        joinBtn.innerText = "REJOINDRE LA POOL";
                        joinBtn.disabled = false;
                        joinBtn.style.opacity = '1';
                        joinBtn.style.cursor = 'pointer';
                        joinBtn.style.display = 'block';
                    }
                    hideCombatOverlay();
                }
                // Si en cooldown, ne pas cacher l'overlay (il affiche le cooldown)
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

            const cooldownInput = document.getElementById('cooldown-input');
            const cooldownDisplay = document.getElementById('cooldown-display');
            if (cooldownInput) {
                cooldownInput.value = limits.min_cooldown;
            }
            if (cooldownDisplay) {
                const mins = limits.min_cooldown;
                if (mins < 1) {
                    const secs = mins * 60;
                    cooldownDisplay.innerText = `${secs.toFixed(0)} sec${secs > 1 ? 's' : ''}`;
                } else if (mins < 60) {
                    cooldownDisplay.innerText = `${mins.toFixed(mins % 1 === 0 ? 0 : 1)} min${mins > 1 ? 's' : ''}`;
                } else {
                    const hrs = mins / 60;
                    cooldownDisplay.innerText = `${hrs.toFixed(1)} Hour${hrs > 1 ? 's' : ''}`;
                }
            }
        }
    } catch (e) {
        console.error("Failed to fetch and apply active user limits", e);
    }
}

export function isUserInCooldown() {
    if (!window.userState || !window.userState.cooldown_until) return false;
    const cooldownTime = new Date(window.userState.cooldown_until).getTime();
    return cooldownTime > Date.now();
}
