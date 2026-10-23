// resources/js/modules/balance-chart.js
// Graphique canvas : évolution du solde de jeu vers l'objectif (target).
// Affiche : ligne de départ (gris pointillé), ligne d'objectif (or pointillé),
// courbe du solde, valeurs (départ / objectif / solde actuel).

let canvas = null;
let ctx = null;
let history = [];
let target = null;
let startVal = null;

export function initBalanceChart() {
    canvas = document.getElementById('balance-chart');
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    resize();
    window.addEventListener('resize', resize);
}

function resize() {
    if (!canvas || !ctx) return;
    const w = canvas.clientWidth || canvas.parentElement?.clientWidth || 300;
    const h = canvas.clientHeight || 90;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(w * dpr));
    canvas.height = Math.max(1, Math.floor(h * dpr));
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    draw();
}

export function resetBalanceChart(start) {
    history = [];
    startVal = Number.isFinite(Number(start)) && Number(start) > 0 ? Number(start) : null;
    if (startVal != null) history.push(startVal);
    draw();
}

export function pushBalance(value, targetValue, startValue) {
    const v = Number(value);
    if (!Number.isFinite(v)) return;
    if (targetValue !== undefined) {
        const t = Number(targetValue);
        target = Number.isFinite(t) && t > 0 ? t : null;
    }
    if (startValue !== undefined) {
        const s = Number(startValue);
        startVal = Number.isFinite(s) && s > 0 ? s : null;
    }
    const last = history.length ? history[history.length - 1] : null;
    if (last === null || Math.abs(last - v) > 1e-9) history.push(v);
    if (history.length > 90) history.shift();
    draw();
}

function draw() {
    if (!ctx || !canvas) return;
    const dpr = window.devicePixelRatio || 1;
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    if (w <= 1 || h <= 1) return;
    ctx.clearRect(0, 0, w, h);

    const vals = history.slice();
    if (target != null) vals.push(target);
    if (startVal != null) vals.push(startVal);
    if (!vals.length) return;

    const padL = 6, padR = 54, padT = 8, padB = 8;
    let min = Math.min(...vals), max = Math.max(...vals);
    if (!Number.isFinite(min) || !Number.isFinite(max)) { min = 0; max = 1; }
    if (max - min < 1e-9) { max = min + Math.max(1e-6, Math.abs(min) * 0.01); }
    const span = max - min || 1;
    const y = (v) => h - padB - ((v - min) / span) * (h - padT - padB);
    const x = (i) => padL + (history.length <= 1 ? 0 : (i / (history.length - 1)) * (w - padL - padR));

    ctx.font = '10px Rajdhani, sans-serif';
    ctx.textBaseline = 'middle';

    // Ligne de DÉPART
    if (startVal != null) {
        ctx.save();
        ctx.strokeStyle = 'rgba(160,153,184,0.7)';
        ctx.setLineDash([3, 3]);
        ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(padL, y(startVal)); ctx.lineTo(w - padR, y(startVal)); ctx.stroke();
        ctx.fillStyle = 'rgba(160,153,184,0.95)';
        ctx.fillText('départ', w - padR + 4, y(startVal));
        ctx.restore();
    }

    // Ligne OBJECTIF
    if (target != null) {
        ctx.save();
        ctx.strokeStyle = 'rgba(255,199,0,0.85)';
        ctx.setLineDash([4, 3]);
        ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(padL, y(target)); ctx.lineTo(w - padR, y(target)); ctx.stroke();
        ctx.fillStyle = 'rgba(255,199,0,0.98)';
        ctx.fillText('objectif', w - padR + 4, y(target));
        ctx.restore();
    }

    // Courbe du solde
    if (history.length >= 2) {
        const rising = history[history.length - 1] >= history[0];
        const color = rising ? 'rgba(0,230,118,0.95)' : 'rgba(255,0,85,0.95)';
        const fill = rising ? 'rgba(0,230,118,0.18)' : 'rgba(255,0,85,0.18)';
        ctx.save();
        // aire sous la courbe
        ctx.beginPath();
        history.forEach((v, i) => { const px = x(i), py = y(v); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); });
        ctx.lineTo(x(history.length - 1), h - padB);
        ctx.lineTo(x(0), h - padB);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();
        // courbe
        ctx.beginPath();
        history.forEach((v, i) => { const px = x(i), py = y(v); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); });
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.stroke();
        // point courant + valeur
        const lastVal = history[history.length - 1];
        const lx = x(history.length - 1), ly = y(lastVal);
        ctx.fillStyle = rising ? '#00E676' : '#FF0055';
        ctx.beginPath(); ctx.arc(lx, ly, 2.5, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = rising ? '#00E676' : '#FF0055';
        ctx.fillText(lastVal.toFixed(4), Math.max(padL, Math.min(lx + 4, w - padR - 2)), Math.max(padT, ly - 8));
        ctx.restore();
    } else if (history.length === 1) {
        // un seul point : affiche la valeur
        ctx.save();
        ctx.fillStyle = 'rgba(0,229,255,0.95)';
        ctx.beginPath(); ctx.arc(x(0), y(history[0]), 2.5, 0, Math.PI * 2); ctx.fill();
        ctx.fillText(history[0].toFixed(4), Math.min(x(0) + 4, w - padR - 2), Math.max(padT, y(history[0]) - 8));
        ctx.restore();
    }
}
