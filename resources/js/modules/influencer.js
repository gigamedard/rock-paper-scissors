/**
 * Module Influencer (VIP) — Battlepool
 * Gère le programme d'affiliation premium pour les créateurs.
 */
import { secureFetch } from '../core/api.js';
import { t } from './i18n.js';
import { showToast } from '../core/toast.js';

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
        if (e.target.id === 'btn-add-social-link') {
            const container = document.getElementById('social-links-container');
            const row = document.createElement('div');
            row.className = 'social-link-row';
            row.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
            row.innerHTML = `
                <select style="padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;">
                    <option value="Twitter">Twitter / X</option>
                    <option value="YouTube">YouTube</option>
                    <option value="Twitch">Twitch</option>
                    <option value="Telegram">Telegram</option>
                    <option value="Discord">Discord</option>
                    <option value="Other">Autre</option>
                </select>
                <input type="text" placeholder="URL du profil" style="flex: 1; padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;" required>
                <button type="button" class="btn-remove-link" style="background: rgba(239, 68, 68, 0.2); border: none; color: #ef4444; border-radius: 8px; padding: 0 1rem; cursor: pointer;">X</button>
            `;
            container.appendChild(row);
        }
        if (e.target.classList.contains('btn-remove-link')) {
            e.target.parentElement.remove();
        }
        if (e.target.id === 'btn-retry-application') {
            const container = document.getElementById('influencer-dashboard-page');
            renderJoinView(container);
        }
    });

    document.addEventListener('submit', async (e) => {
        if (e.target.id === 'influencer-application-form') {
            e.preventDefault();
            await submitApplication(e.target);
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
            checkApplicationStatus(container);
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

async function checkApplicationStatus(container) {
    try {
        const res = await secureFetch('/influencer/application-status');
        const data = await res.json();
        
        if (data.status === 'pending') {
            renderPendingView(container);
        } else if (data.status === 'rejected') {
            renderRejectedView(container, data.can_retry, data.attempts_left);
        } else {
            renderJoinView(container);
        }
    } catch (err) {
        renderJoinView(container);
    }
}

function renderPendingView(container) {
    container.innerHTML = `
        <div class="inf-header">
            <h1>Candidature en cours</h1>
        </div>
        <div class="inf-join-panel fade-in" style="border-color: #f59e0b;">
            <h2>⏳ En cours d'examen</h2>
            <p>Votre demande est actuellement en cours de traitement par notre équipe.</p>
            <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 1rem;">Vous recevrez une notification dès qu'elle sera traitée.</p>
        </div>
    `;
}

function renderRejectedView(container, canRetry, attemptsLeft) {
    let retryHtml = '';
    if (canRetry) {
        retryHtml = `
            <p style="font-size: 0.8rem; color: var(--text-dim); margin-top: 1rem;">Il vous reste ${attemptsLeft} tentative(s).</p>
            <button class="btn-inf-join" id="btn-retry-application" style="margin-top: 1.5rem; background: transparent; border: 1px solid rgba(255,255,255,0.2);">Réessayer</button>
        `;
    } else {
        retryHtml = `
            <p style="font-size: 0.9rem; color: #ef4444; margin-top: 1.5rem; font-weight: bold;">⚠️ Limite de candidatures atteinte.</p>
        `;
    }

    container.innerHTML = `
        <div class="inf-header">
            <h1>Candidature Refusée</h1>
        </div>
        <div class="inf-join-panel fade-in" style="border-color: #ef4444;">
            <h2>❌ Candidature Refusée</h2>
            <p>Désolé, votre profil ne correspond pas à nos critères actuels.</p>
            ${retryHtml}
        </div>
    `;
}

function renderJoinView(container) {
    container.innerHTML = `
        <div class="inf-header">
            <h1>${t('influencer.title') || 'Devenir Influenceur'}</h1>
        </div>
        <div class="inf-join-panel fade-in">
            <h2>Rejoignez notre programme</h2>
            <p>Remplissez le formulaire ci-dessous pour soumettre votre candidature.</p>
            <form id="influencer-application-form" style="text-align: left; margin-top: 1.5rem;">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dim);">Pseudo d'Influenceur</label>
                    <input type="text" id="app-pseudo" required placeholder="Votre nom public" style="width: 100%; padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;">
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-dim);">Réseaux Sociaux (ex: Twitter, YouTube)</label>
                    <div id="social-links-container">
                        <div class="social-link-row" style="display: flex; gap: 10px; margin-bottom: 10px;">
                            <select style="padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;">
                                <option value="Twitter">Twitter / X</option>
                                <option value="YouTube">YouTube</option>
                                <option value="Twitch">Twitch</option>
                                <option value="Telegram">Telegram</option>
                                <option value="Discord">Discord</option>
                                <option value="Other">Autre</option>
                            </select>
                            <input type="text" placeholder="URL du profil" style="flex: 1; padding: 0.8rem; border-radius: 8px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;" required>
                        </div>
                    </div>
                    <button type="button" id="btn-add-social-link" style="background: transparent; border: 1px dashed var(--text-dim); color: var(--text-dim); padding: 0.5rem 1rem; border-radius: 8px; cursor: pointer; font-size: 0.8rem; margin-top: 0.5rem;">+ Ajouter un lien</button>
                </div>
                <button type="submit" class="btn-inf-join" style="width: 100%; margin-top: 1rem;" id="btn-submit-application">Envoyer ma Candidature</button>
                <div style="text-align: center; margin-top: 1rem;">
                    <button type="button" id="btn-join-influencer" style="background: transparent; border: none; color: var(--text-dim); font-size: 0.7rem; text-decoration: underline; cursor: pointer;">(Dev) Bypass & Join Test Program</button>
                </div>
            </form>
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
            showToast(t('influencer.join_success'), 'success');
            loadInfluencerDashboard();
        } else {
            showToast(t('influencer.join_error'), 'error');
        }
    } catch (err) {
        showToast(t('influencer.join_error'), 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function submitApplication(form) {
    const btn = form.querySelector('#btn-submit-application');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Envoi...';
    }

    const pseudo = form.querySelector('#app-pseudo').value;
    const rows = form.querySelectorAll('.social-link-row');
    const social_links = [];

    rows.forEach(row => {
        const platform = row.querySelector('select').value;
        let url = row.querySelector('input').value.trim();
        if (url) {
            if (!/^https?:\/\//i.test(url)) {
                url = 'https://' + url;
            }
            social_links.push({ platform, url });
        }
    });

    if (social_links.length === 0) {
        showToast("Veuillez ajouter au moins un réseau social.", 'warn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Envoyer ma Candidature';
        }
        return;
    }

    try {
        const res = await secureFetch('/influencer/apply', {
            method: 'POST',
            body: JSON.stringify({ pseudo, social_links })
        });
        
        const data = await res.json();
        if (res.ok) {
            loadInfluencerDashboard();
        } else {
            showToast(data.message || data.error || "Erreur lors de l'envoi.", 'error');
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Envoyer ma Candidature';
            }
        }
    } catch (err) {
        showToast("Erreur réseau.", 'error');
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Envoyer ma Candidature';
        }
    }
}

async function claimReward() {
    try {
        const res = await secureFetch('/influencer/claim-reward', { method: 'POST' });
        const data = await res.json();
        if (res.ok) {
            showToast(t('influencer.claim_success', { amount: data.amount }), 'success');
            loadInfluencerDashboard();
        } else {
            showToast(data.error || t('influencer.claim_error'), 'error');
        }
    } catch (err) {
        showToast(t('influencer.network_error'), 'error');
    }
}
