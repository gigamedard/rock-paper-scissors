/**
 * Module Influencer (VIP) — Battlepool
 * Gère le programme d'affiliation premium pour les créateurs.
 */
import { secureFetch } from '../core/api.js';

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
        container.innerHTML = `<div class="error-msg">Erreur de chargement du dashboard.</div>`;
    }
}

function renderJoinView(container) {
    container.innerHTML = `
        <div class="inf-header">
            <h1>Influencer Program</h1>
        </div>
        <div class="inf-join-panel fade-in">
            <h2>Devenez un Guerrier VIP</h2>
            <p>Rejoignez notre programme d'influenceurs et gagnez des récompenses en AVAX en invitant votre communauté dans l'arène.</p>
            <button class="btn-inf-join" id="btn-join-influencer">REJOINDRE LE PROGRAMME (TEST)</button>
            <p style="font-size: 0.7rem; margin-top: 1rem; opacity: 0.5;">Note: En production, cela nécessite une validation manuelle.</p>
        </div>
    `;
}

function renderDashboardView(container, data) {
    const { myStats, myPool, leaderboard } = data;

    container.innerHTML = `
        <div class="inf-header">
            <h1 data-i18n="influencer.title">Influencer Dashboard</h1>
        </div>

        <div class="inf-main-grid fade-in">
            <div class="inf-left-col">
                <!-- My Active Pool Card -->
                <div class="inf-pool-card">
                    <div class="inf-pool-header">
                        <div class="inf-pool-title">
                            <span>ACTIVE POOL</span>
                            <h2>${myPool?.name || 'Loading...'}</h2>
                        </div>
                        <div class="inf-reward-badge">
                            ${myPool?.reward_amount || 0} AVAX POOL
                        </div>
                    </div>

                    <!-- Personal Progress -->
                    <div class="inf-progress-section">
                        <div class="inf-progress-label">
                            <span>Objectif Personnel</span>
                            <b>${myPool?.personal_referrals_count || 0} / ${myPool?.personal_milestone_target || 5} Refs</b>
                        </div>
                        <div class="inf-progress-bar-bg">
                            <div class="inf-progress-fill" style="width: ${myPool?.personal_progress_percentage || 0}%"></div>
                        </div>
                    </div>

                    <!-- Global Pool Progress -->
                    <div class="inf-progress-section">
                        <div class="inf-progress-label">
                            <span>Progression Globale de la Pool</span>
                            <b>${myPool?.current_referrals || 0} / ${myPool?.pool_milestone || 50} Refs</b>
                        </div>
                        <div class="inf-progress-bar-bg">
                            <div class="inf-progress-fill pool-fill" style="width: ${myPool?.progress_percentage || 0}%"></div>
                        </div>
                    </div>

                    <!-- Claim Box -->
                    <div class="inf-claim-box">
                        <p style="font-size: 0.8rem; color: var(--text-dim); margin-bottom: 1rem;">
                            Les récompenses sont distribuées une fois les objectifs de la pool atteints.
                        </p>
                        <button class="btn-inf-claim" id="btn-claim-influencer-reward" ${!myPool?.canClaim ? 'disabled' : ''}>
                            ${myPool?.hasClaimed ? 'DÉJÀ RÉCLAMÉ' : 'RÉCLAMER MA PART'}
                        </button>
                    </div>
                </div>

                <!-- Leaderboard -->
                <div class="inf-leaderboard">
                    <h3 style="margin-bottom: 1rem; color: var(--text-dim); font-size: 0.8rem; letter-spacing: 1px;">TOP INFLUENCERS</h3>
                    <div class="inf-lb-list">
                        ${leaderboard.map(inf => `
                            <div class="inf-lb-row">
                                <div class="inf-lb-rank ${inf.rank === 1 ? 'top1' : ''}">#${inf.rank}</div>
                                <div class="inf-lb-name">${inf.name}</div>
                                <div class="inf-lb-count">${inf.referral_count} Refs</div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <div class="inf-right-col">
                <div class="inf-sidebar-stats">
                    <div class="inf-stat-mini">
                        <span>Mon Rang</span>
                        <b>#${myStats.myRank}</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>Parrainages</span>
                        <b>${myStats.personalReferrals}</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>Conversion</span>
                        <b>${myStats.conversionRate}%</b>
                    </div>
                    <div class="inf-stat-mini">
                        <span>AVAX Généré</span>
                        <b>${myStats.avaxSpent} Ξ</b>
                    </div>
                </div>
                
                <div style="margin-top: 2rem; background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: 1rem; font-size: 0.75rem; color: var(--text-dim);">
                    <h4 style="color: #fff; margin-bottom: 0.5rem;">Comment ça marche ?</h4>
                    <p>En tant qu'influenceur, vous participez à des pools de récompenses. Une fois que la pool atteint son jalon global et que vous avez atteint votre jalon personnel, vous devenez éligible au partage du montant total.</p>
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
            alert("Bienvenue dans le programme !");
            loadInfluencerDashboard();
        }
    } catch (err) {
        alert("Erreur lors de l'inscription.");
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function claimReward() {
    try {
        const res = await secureFetch('/influencer/claim-reward', { method: 'POST' });
        const data = await res.json();
        if (res.ok) {
            alert(`Succès ! Vous avez réclamé ${data.amount} AVAX.`);
            loadInfluencerDashboard();
        } else {
            alert(data.error || "Erreur lors du claim.");
        }
    } catch (err) {
        alert("Erreur réseau.");
    }
}
