// resources/js/modules/referral.js
/**
 * Module Parrainage (Referral & Influencer)
 * - Capture le paramètre ?ref= à l'arrivée sur le site
 * - Affiche les statistiques de parrainage de l'utilisateur
 * - Affiche le leaderboard des meilleurs parrains
 */
import { secureFetch } from '../core/api.js';
import { t } from './i18n.js';
import { getCurrentLocale } from './i18n.js';

export function initReferral() {
    console.log("[Referral] Initialisation du module Parrainage");

    // Intercepter le code de parrainage de l'URL à l'arrivée (lien d'invitation)
    captureReferralFromUrl();

    // Charger les données au montage de la page
    loadReferralData();

    // Bind le bouton "Copier le lien"
    const copyBtn = document.getElementById('referral-copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', copyReferralLink);
    }

    // Bind le formulaire d'application de code
    const applyBtn = document.getElementById('referral-apply-btn');
    if (applyBtn) {
        applyBtn.addEventListener('click', applyReferralCode);
    }

    // Écouter les changements de langue pour re-rendre
    window.addEventListener('i18n:changed', loadReferralData);
}

/**
 * À l'arrivée sur le site, si ?ref=CODE est dans l'URL, on le sauvegarde.
 * Il sera envoyé automatiquement après la connexion du wallet.
 */
function captureReferralFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const refCode = urlParams.get('ref');

    if (refCode) {
        localStorage.setItem('pending_referral_code', refCode.toUpperCase());
        console.log(`[Referral] Code de parrainage capturé : ${refCode}`);
    }

    // Si l'utilisateur vient de se connecter ET qu'il y a un code en attente
    window.addEventListener('auth:success', () => {
        const pendingCode = localStorage.getItem('pending_referral_code');
        if (pendingCode) {
            applyCodeSilently(pendingCode);
        }
    });
}

async function applyCodeSilently(code) {
    try {
        const res = await secureFetch('/referral/apply', {
            method: 'POST',
            body: JSON.stringify({ referral_code: code })
        });
        if (res.ok) {
            localStorage.removeItem('pending_referral_code');
            console.log('[Referral] Code appliqué automatiquement :', code);
        }
    } catch(e) {
        // Silent fail - pas critique
    }
}

async function loadReferralData() {
    await Promise.all([loadMyStats(), loadLeaderboard()]);
}

async function loadMyStats() {
    try {
        const res = await secureFetch('/referral/status');
        if (!res.ok) return;
        const data = await res.json();

        // Afficher le code
        const codeEl = document.getElementById('referral-my-code');
        if (codeEl) codeEl.textContent = data.code || '---';

        // Afficher les stats
        const elTotal = document.getElementById('referral-stat-total');
        const elValidated = document.getElementById('referral-stat-validated');
        const elPending = document.getElementById('referral-stat-pending');
        const elRewards = document.getElementById('referral-stat-rewards');

        if (elTotal) elTotal.textContent = data.totalReferrals || 0;
        if (elValidated) elValidated.textContent = data.validatedReferrals || 0;
        if (elPending) elPending.textContent = data.pendingReferrals || 0;
        if (elRewards) elRewards.textContent = (data.totalRewards || 0) + ' SNT';
    } catch (e) {
        console.error('[Referral] Erreur chargement stats:', e);
    }
}

async function loadLeaderboard() {
    try {
        const res = await secureFetch('/referral/leaderboard');
        if (!res.ok) return;
        const leaderboard = await res.json();

        renderLeaderboard(leaderboard);
    } catch (e) {
        console.error('[Referral] Erreur chargement leaderboard:', e);
    }
}

function renderLeaderboard(entries) {
    const container = document.getElementById('referral-leaderboard');
    if (!container) return;

    if (!entries || entries.length === 0) {
        container.innerHTML = '<p class="ref-empty">Aucun classement disponible.</p>';
        return;
    }

    const medals = ['🥇', '🥈', '🥉'];

    container.innerHTML = entries.map((entry, index) => `
        <div class="ref-leaderboard-row ${index < 3 ? 'top-three' : ''}">
            <span class="ref-rank">${medals[index] || `#${index + 1}`}</span>
            <span class="ref-wallet">${entry.wallet_address}</span>
            <span class="ref-count">${entry.referral_count} parrainages</span>
            <span class="ref-rewards">${entry.rewards_earned} SNT</span>
        </div>
    `).join('');
}

function copyReferralLink() {
    const codeEl = document.getElementById('referral-my-code');
    if (!codeEl || codeEl.textContent === '---') return;

    const code = codeEl.textContent;
    const locale = getCurrentLocale();
    const link = `${window.location.origin}/?ref=${code}&lang=${locale}`;

    navigator.clipboard.writeText(link).then(() => {
        const btn = document.getElementById('referral-copy-btn');
        if (btn) {
            const original = btn.textContent;
            btn.textContent = '✅ Copié !';
            setTimeout(() => btn.textContent = original, 2000);
        }
    });
}

async function applyReferralCode() {
    const input = document.getElementById('referral-apply-input');
    if (!input || !input.value.trim()) return;

    const code = input.value.trim().toUpperCase();

    try {
        const res = await secureFetch('/referral/apply', {
            method: 'POST',
            body: JSON.stringify({ referral_code: code })
        });
        const data = await res.json();

        if (res.ok) {
            alert('✅ ' + data.message);
            input.value = '';
            await loadMyStats();
        } else {
            alert('❌ ' + data.message);
        }
    } catch (e) {
        console.error('[Referral] Erreur application code:', e);
    }
}
