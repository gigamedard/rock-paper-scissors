// js/ui/ui-manager.js

export class UIManager {
    static showPage(pageId) {
        document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
        const target = document.getElementById(pageId);
        if (target) {
            target.classList.add('active');
            window.scrollTo(0, 0);
        }
    }

    static updateBattleHud(data) {
        const hud = document.getElementById('battle-hud');
        if (!data || data.status === 'idle') {
            hud.style.display = 'none';
            return;
        }

        hud.style.display = 'block';
        const statusText = document.getElementById('hud-status-text');
        const progressBar = document.getElementById('hud-progress-bar');
        const balanceEl = document.getElementById('hud-battle-balance');

        statusText.innerText = data.statusText;
        progressBar.style.width = `${data.progress}%`;
        balanceEl.innerText = `${data.balance} AVAX`;

        if (data.isLoss) {
            progressBar.classList.add('loss');
        } else {
            progressBar.classList.remove('loss');
        }
    }

    static setSubmissionStep(stepId, state) {
        const el = document.getElementById(stepId);
        if (!el) return;
        el.className = `step-item ${state}`;
    }

    static updateStatus(text, type = 'info') {
        const statusText = document.getElementById('hud-status-text');
        if (statusText) {
            statusText.innerText = text;
            statusText.className = `status-text ${type}`;
        }
    }

    static showCombatAura(show = true) {
        const aura = document.querySelector('.aura-container');
        if (aura) {
            if (show) aura.classList.add('active');
            else aura.classList.remove('active');
        }
        
        // On peut aussi changer la couleur du HUD
        const hud = document.getElementById('battle-hud');
        if (hud) {
            if (show) hud.classList.add('combat-mode');
            else hud.classList.remove('combat-mode');
        }
    }
}
