// resources/js/modules/referral.js
/**
 * Module Parrainage (Referral & Influencer)
 * - Capture le paramètre ?ref= à l'arrivée sur le site
 * - Affiche les statistiques de parrainage de l'utilisateur
 * - Affiche le leaderboard des meilleurs parrains
 */
import { secureFetch, getAuthToken } from '../core/api.js';
import { t } from './i18n.js';
import { getCurrentLocale } from './i18n.js';
import { showToast } from '../core/toast.js';

export function initReferral() {
    console.log("[Referral] Initialisation du module Parrainage");

    // Bind le bouton "Copier le lien" (direct property to prevent double-binding)
    const copyBtn = document.getElementById('referral-copy-btn');
    if (copyBtn) {
        copyBtn.onclick = copyReferralLink;
    }

    // Bind le formulaire d'application de code (direct property to prevent double-binding)
    const applyBtn = document.getElementById('referral-apply-btn');
    if (applyBtn) {
        applyBtn.onclick = applyReferralCode;
    }

    // Écouter les changements de langue pour re-rendre (guarding against double-binding)
    if (!window.referralListenersInitialized) {
        window.referralListenersInitialized = true;

        // Intercepter le code de parrainage de l'URL à l'arrivée (lien d'invitation)
        captureReferralFromUrl();

        window.addEventListener('i18n:changed', loadReferralData);

        window.addEventListener('auth:success', () => {
            loadReferralData();
        });

        window.addEventListener('referral:refresh', () => {
            loadReferralData();
            loadLeaderboard();
        });
    }

    // Charger les données à chaque montage/init du module
    loadReferralData();
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
        const res = await secureFetch('/user/set-referral', {
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
    // P4.3 : silencieux 401 cosmétique avant auth — sans token, secureFetch
    // lève systématiquement ; on évite le console.error au chargement déconnecté.
    if (!getAuthToken()) return;
    try {
        const res = await secureFetch('/referral/status'); // route: api/referral/status
        if (!res.ok) return;
        const data = await res.json();

        // Afficher le code
        const codeEl = document.getElementById('referral-my-code');
        if (codeEl) codeEl.textContent = data.code || '---';

        // Lien complet VISIBLE (le user vérifie que le code du parrain y est)
        const linkEl = document.getElementById('referral-my-link');
        if (linkEl && data.code) {
            linkEl.textContent = `${window.location.origin}/?ref=${data.code}`;
        }

        // Afficher les stats
        const elTotal = document.getElementById('referral-stat-total');
        const elValidated = document.getElementById('referral-stat-validated');
        const elPending = document.getElementById('referral-stat-pending');
        const elRewards = document.getElementById('referral-stat-rewards');

        if (elTotal) elTotal.textContent = data.totalReferrals || 0;
        if (elValidated) elValidated.textContent = data.validatedReferrals || 0;
        if (elPending) elPending.textContent = data.pendingReferrals || 0;
        if (elRewards) elRewards.textContent = t('referral.rewards_amount', { amount: data.totalRewards || 0 });
    } catch (e) {
        console.error('[Referral] Erreur chargement stats:', e);
    }
}

async function loadLeaderboard() {
    // P4.3 : idem — pas de log 401 avant connexion.
    if (!getAuthToken()) return;
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
        container.innerHTML = `<p class="ref-empty">${t('referral.no_leaderboard')}</p>`;
        return;
    }

    const medals = ['🥇', '🥈', '🥉'];

    container.innerHTML = entries.map((entry, index) => `
        <div class="ref-leaderboard-row ${index < 3 ? 'top-three' : ''}">
            <span class="ref-rank">${medals[index] || `#${index + 1}`}</span>
            <span class="ref-wallet">${entry.wallet_address}</span>
            <span class="ref-count">${t('referral.referrals_count', { count: entry.referral_count })}</span>
            <span class="ref-rewards">${t('referral.rewards_amount', { amount: entry.rewards_earned })}</span>
        </div>
    `).join('');
}

function copyReferralLink() {
    const codeEl = document.getElementById('referral-my-code');
    if (!codeEl || codeEl.textContent === '---') return;

    const code = codeEl.textContent;
    const locale = getCurrentLocale();
    const link = `${window.location.origin}/?ref=${code}&lang=${locale}`;

    // Partage natif mobile (share sheet iOS/Android — WhatsApp, etc.) :
    // fallback clipboard si navigator.share absent (desktop).
    const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
    if (isMobile && navigator.share) {
        navigator.share({ title: 'BattlePool', text: 'Entrez dans l\'arène !', url: link })
            .catch(() => { /* annulation utilisateur — non bloquant */ });
        return;
    }
    navigator.clipboard.writeText(link).then(() => {
        const btn = document.getElementById('referral-copy-btn');
        if (btn) {
            const original = btn.textContent;
            btn.textContent = '✅ ' + t('referral.copied');
            setTimeout(() => btn.textContent = original, 2000);
        }
    });
}

async function applyReferralCode() {
    const input = document.getElementById('referral-apply-input');
    if (!input || !input.value.trim()) return;

    const code = input.value.trim().toUpperCase();

    try {
        const res = await secureFetch('/user/set-referral', {
            method: 'POST',
            body: JSON.stringify({ referral_code: code })
        });
        const data = await res.json();

        if (res.ok) {
            showToast('✅ ' + data.message, 'success');
            input.value = '';
            await loadMyStats();
        } else {
            showToast('❌ ' + data.message, 'error');
        }
    } catch (e) {
        console.error('[Referral] Erreur application code:', e);
    }
}
