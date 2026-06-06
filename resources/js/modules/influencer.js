/**
 * Module Influencer (VIP) — Battlepool
 * Gère le programme d'affiliation premium pour les créateurs.
 */
import { secureFetch } from '../core/api.js';
import { t } from './i18n.js';

let influencerData = null;

export async function initInfluencer() {
    console.log("💎 Influencer Module Initialized");
    
    // Écouter le changement de route pour charger les données
    window.addEventListener('route:changed', (e) => {
        if (e.detail.pageId === 'influencer-dashboard-page') {
            loadInfluencerDashboard();
        }
    });

    // Écouteur pour le bouton de join (test program)
    document.addEventListener('click', async (e) => {
        if (e.target.id === 'btn-join-influencer') {
            await joinInfluencerProgram();
        }
        if (e.target.id === 'btn-claim-influencer-reward') {
            await claimReward();
        }
    });

    // Écouter le changement de langue pour re-rendre le dashboard dynamiquement
    window.addEventListener('i18n:changed', () => {
        const container = document.getElementById('influencer-dashboard-page');
        if (container && container.classList.contains('active')) {
            loadInfluencerDashboard();
        }
    });
}

export async function loadInfluencerDashboard() {
    const container = document.getElementById('influencer-dashboard-page');
    if (!container) return;

    try {
        const res = await secureFetch('/influencer/dashboard');
        
        if (res.status === 403) {
            renderJoinView(container);
            return;
        }

        if (!res.ok) throw new Error("Erreur dashboard influencer");

        const data = await res.json();
        influencerData = data;
        renderDashboardView(container, data);
    } catch (err) {
        console.error("Dashboard error:", err);
        container.innerHTML = `<div class="error-msg">${t('influencer.error_loading')}</div>`;
    }
}

function renderJoinView(container) {
    container.innerHTML = `
        <div class="inf-header">
            <h1>${t('influencer.title')}</h1>
        </div>
        <div class="inf-join-panel fade-in">
            <h2>${t('influencer.join_title')}</h2>
            <p>${t('influencer.join_desc')}</p>
            <button class="btn-inf-join" id="btn-join-influencer">${t('influencer.join_btn')}</button>
            <p style="font-size: 0.7rem; margin-top: 1rem; opacity: 0.5;">${t('influencer.join_note')}</p>
        </div>
    `;
}

function renderDashboardView(container, data) {
    const { myStats, myPool, leaderboard } = data;

    container.innerHTML = `
        <div class="inf-header">
            <h1>${t('influencer.title')}</h1>
        </div>

        <div class="inf-main-grid fade-in">
            <div class="inf-left-col">
                <!-- My Active Pool Card -->
                <div class="inf-pool-card">
                    <div class="inf-pool-header">
                        <div class="inf-pool-title">
                            <span>${t('influencer.active_pool')}</span>
                            <h2>${myPool?.name || t('marketplace.loading_inventory')}</h2>
                        </div>
                        <div class="inf-reward-badge">
                            ${t('influencer.avax_pool', { amount: myPool?.reward_amount || 0 })}
                        </div>
                    </div>

                    <!-- Personal Progress -->
                    <div class="inf-progress-section">
                        <div class="inf-progress-label">
                            <span>${t('influencer.personal_goal')}</span>
                            <b>${t('influencer.refs', { current: myPool?.personal_referrals_count || 0, target: myPool?.personal_milestone_target || 5 })}</b>
                        </div>
                        <div class="inf-progress-bar-bg">
                            <div class="inf-progress-fill" style="width: ${myPool?.personal_progress_percentage || 0}%"></div>
                        </div>
                    </div>

                    <!-- Global Pool Progress -->
                    <div class="inf-progress-section">
                        <div class="inf-progress-label">
                            <span>${t('influencer.global_progress')}</span>
                            <b>${t('influencer.refs', { current: myPool?.current_referrals || 0, target: myPool?.pool_milestone || 50 })}</b>
                        </div>
                        <div class="inf-progress-bar-bg">
                            <div class="inf-progress-fill pool-fill" style="width: ${myPool?.progress_percentage || 0}%"></div>
                        </div>
                    </div>

                    <!-- Claim Box -->
                    <div class="inf-claim-box">
                        <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 1rem;">
                            ${t('influencer.claim_box_note')}
                        </p>
                        <button class="btn-inf-claim" id="btn-claim-influencer-reward" ${!myPool?.canClaim ? 'disabled' : ''}>
                            ${myPool?.hasClaimed ? t('influencer.already_claimed') : t('influencer.claim_btn')}
                        </button>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="inf-leaderboard">
                    <h3 style="margin-bottom: 1rem; color: var(--text-dim); font-size: 0.8rem; letter-spacing: 1px;">${t('influencer.top_influencers')}</h3>
                    <div class="inf-lb-list">
                        ${leaderboard.map(inf => `
                            <div class="inf-lb-row">
                                <div class="inf-lb-rank ${inf.rank === 1 ? 'top1' : ''}">#${inf.rank}</div>
                                <div class="inf-lb-name">${inf.name}</div>
                                <div class="inf-lb-count">${t('influencer.refs_count', { count: inf.referral_count })}</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <div class="inf-right-col">
                <div class="inf-sidebar-stats">
                    <div class="inf-stat-mini">
                        <span>${t('influencer.my_rank')}</span>
                        <b>#${myStats.myRank}</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>${t('influencer.referrals')}</span>
                        <b>${myStats.personalReferrals}</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>${t('influencer.conversion')}</span>
                        <b>${myStats.conversionRate}%</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>${t('influencer.avax_generated')}</span>
                        <b>${myStats.avaxSpent} Ξ</b>
                    </div>
                </div>
                
                <div style="margin-top: 2rem; background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 1rem; font-size: 0.75rem; color: var(--text-dim);">
                    <h4 style="color: #fff; margin-bottom: 0.5rem;">${t('influencer.how_it_works')}</h4>
                    <p>${t('influencer.how_it_works_desc')}</p>
                </div>
            </div>
        </div>
    `;
}

async function joinInfluencerProgram() {
    const btn = document.getElementById('btn-join-influencer');
    if (btn) btn.disabled = true;

    try {
        const res = await secureFetch('/influencer/join-test', { method: 'POST' });
        if (res.ok) {
            alert(t('influencer.join_success'));
            loadInfluencerDashboard();
        } else {
            alert(t('influencer.join_error'));
        }
    } catch (err) {
        alert(t('influencer.join_error'));
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function claimReward() {
    try {
        const res = await secureFetch('/influencer/claim-reward', { method: 'POST' });
        const data = await res.json();
        if (res.ok) {
            alert(t('influencer.claim_success', { amount: data.amount }));
            loadInfluencerDashboard();
        } else {
            alert(data.error || t('influencer.claim_error'));
        }
    } catch (err) {
        alert(t('influencer.network_error'));
    }
}
