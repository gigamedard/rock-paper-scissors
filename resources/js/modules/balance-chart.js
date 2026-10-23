// resources/js/modules/balance-chart.js
// Mini-graphique canvas : évolution du solde de jeu vers l'objectif (target).

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
    const h = canvas.clientHeight || 64;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = Math.max(1, Math.floor(w * dpr));
    canvas.height = Math.max(1, Math.floor(h * dpr));
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    draw();
}

export function resetBalanceChart(start) {
    history = [];
    startVal = Number.isFinite(Number(start)) ? Number(start) : null;
    if (startVal != null) history.push(startVal);
    draw();
}

export function pushBalance(value, targetValue) {
    const v = Number(value);
    if (!Number.isFinite(v)) return;
    if (targetValue !== undefined) {
        const t = Number(targetValue);
        target = Number.isFinite(t) && t > 0 ? t : null;
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
    if (vals.length < 2) vals.push(0);

    const pad = 6;
    let min = Math.min(...vals);
    let max = Math.max(...vals);
    if (!Number.isFinite(min) || !Number.isFinite(max)) { min = 0; max = 1; }
    if (max - min < 1e-9) { max = min + Math.max(1e-6, Math.abs(min) * 0.01); }
    const span = max - min || 1;
    const y = (v) => h - pad - ((v - min) / span) * (h - 2 * pad);
    const x = (i) => pad + (history.length <= 1 ? 0 : (i / (history.length - 1)) * (w - 2 * pad));

    // Ligne d'objectif (pointillés or)
    if (target != null) {
        ctx.save();
        ctx.strokeStyle = 'rgba(255,199,0,0.8)';
        ctx.setLineDash([4, 4]);
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(pad, y(target));
        ctx.lineTo(w - pad, y(target));
        ctx.stroke();
        ctx.restore();
    }

    // Courbe du solde
    if (history.length >= 2) {
        const rising = history[history.length - 1] >= history[0];
        const color = rising ? 'rgba(0,230,118,0.95)' : 'rgba(255,0,85,0.95)';
        ctx.save();
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.strokeStyle = color;
        ctx.beginPath();
        history.forEach((v, i) => { const px = x(i); const py = y(v); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); });
        ctx.stroke();
        const lx = x(history.length - 1);
        const ly = y(history[history.length - 1]);
        ctx.fillStyle = rising ? '#00E676' : '#FF0055';
        ctx.beginPath();
        ctx.arc(lx, ly, 2.5, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();
    }
}
