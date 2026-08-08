// resources/js/core/toast.js
// ============================================================================
// BATTLEPOOL — NOTIFICATIONS TOAST GLOBALES (remplacement des alert())
// ============================================================================
// Fournit window.showToast(message, type) avec type ∈ { success, error, info, warn }.
// Le conteneur est créé à la demande (aucune dépendance au DOM de la vue).
// Les messages d'erreur Web3 passent par formatError() pour un affichage humain.
// ============================================================================

function ensureToastContainer() {
    let container = document.getElementById('bp-toast-container');
    if (container) return container;
    container = document.createElement('div');
    container.id = 'bp-toast-container';
    container.style.cssText = [
        'position:fixed',
        'top:16px',
        'left:50%',
        'transform:translateX(-50%)',
        'z-index:99999',
        'display:flex',
        'flex-direction:column',
        'align-items:center',
        'gap:8px',
        'pointer-events:none',
        'max-width:92vw'
    ].join(';');
    document.body.appendChild(container);
    return container;
}

const TOAST_STYLES = {
    success: { border: 'rgba(0,230,118,0.55)', bg: 'rgba(0,30,20,0.96)', color: '#a5f3c9' },
    error:   { border: 'rgba(255,23,68,0.55)', bg: 'rgba(40,8,16,0.96)', color: '#ffb3c0' },
    info:    { border: 'rgba(0,229,255,0.55)', bg: 'rgba(6,24,36,0.96)', color: '#aef2ff' },
    warn:    { border: 'rgba(255,199,0,0.55)', bg: 'rgba(40,30,4,0.96)', color: '#ffe9a8' }
};

let _toastSeq = 0;

function showToast(message, type = 'info', ms = 4000) {
    if (message === null || message === undefined || message === '') return;
    const container = ensureToastContainer();
    const style = TOAST_STYLES[type] || TOAST_STYLES.info;
    const id = 'bp-toast-' + (++_toastSeq);

    const el = document.createElement('div');
    el.id = id;
    el.textContent = String(message);
    el.style.cssText = [
        'background:' + style.bg,
        'border:1px solid ' + style.border,
        'color:' + style.color,
        'padding:10px 18px',
        'border-radius:10px',
        'font-family:var(--font-body, system-ui, sans-serif)',
        'font-size:0.9rem',
        'font-weight:600',
        'box-shadow:0 6px 24px rgba(0,0,0,0.5)',
        'opacity:0',
        'transform:translateY(-8px)',
        'transition:opacity .25s, transform .25s',
        'pointer-events:auto',
        'max-width:100%',
        'text-align:center'
    ].join(';');
    container.appendChild(el);

    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translateY(0)';
    });

    const remove = () => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(-8px)';
        setTimeout(() => el.remove(), 260);
    };
    el.addEventListener('click', remove);
    setTimeout(remove, ms);
}

// Formate une erreur RPC/JS en message humain (complète parseRpcError).
function humanizeError(err) {
    if (err === null || err === undefined) return 'Erreur inconnue.';
    if (typeof err === 'string') return err;
    const msg = err.shortMessage || err.reason || err.message || String(err);
    return String(msg);
}

window.showToast = showToast;
window.humanizeError = humanizeError;

export { showToast, humanizeError };
