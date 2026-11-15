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
        const nextTarget = Number.isFinite(t) && t > 0 ? t : null;
        if (nextTarget !== target) {
            target = nextTarget;
            // Nouvelle cible / nouveau bankroll : ne pas garder l'historique
            // d'une session précédente (source des lignes collées/outliers).
            if (startVal != null) history = [];
        }
    }
    if (startValue !== undefined) {
        const s = Number(startValue);
        const nextStart = Number.isFinite(s) && s > 0 ? s : null;
        if (nextStart !== startVal) {
            startVal = nextStart;
            // Démarrage/restauration d'une session : on repart de zéro pour
            // éviter les points à 0/anciens qui écrasent l'échelle du graphe.
            if (nextStart != null) history = [];
        }
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

    // Graphique en PROGRESSION signée quand start + target sont connus :
    // départ = 0 %, objectif = 100 %, négatif sous le départ. Les deux lignes
    // ne peuvent plus être confondues, même si les montants sont proches.
    const useProgress = target != null && startVal != null && target > startVal;
    const series = useProgress
        ? history.map((v) => ((v - startVal) / (target - startVal)) * 100)
        : history.slice();
    const startLine = useProgress ? 0 : startVal;
    const targetLine = useProgress ? 100 : target;

    const vals = series.slice();
    if (targetLine != null) vals.push(targetLine);
    if (startLine != null) vals.push(startLine);
    if (!vals.length) return;

    const padL = 6;
    const padR = useProgress ? 78 : 54;
    const padT = 8;
    const padB = 8;
    let min = Math.min(...vals), max = Math.max(...vals);
    if (!Number.isFinite(min) || !Number.isFinite(max)) { min = 0; max = 1; }
    if (useProgress) {
        // Échelle lisible : 0 % et 100 % restent séparés. On garde au moins
        // 50 points de marge sous le départ, et on borne les outliers
        // historiques (ancien solde 0 avant session) qui collaient les deux
        // lignes en haut du graphe.
        const lastProgress = series.length ? series[series.length - 1] : 0;
        const floor = Math.min(-50, lastProgress - 10);
        min = Math.min(0, floor);
        max = Math.min(200, Math.max(100, max));
    }
    if (max - min < 1e-9) { max = min + Math.max(1e-6, Math.abs(min) * 0.01); }
    const span = max - min || 1;
    const y = (v) => h - padB - ((v - min) / span) * (h - padT - padB);
    const x = (i) => padL + (series.length <= 1 ? 0 : (i / (series.length - 1)) * (w - padL - padR));

    ctx.font = '10px Rajdhani, sans-serif';
    ctx.textBaseline = 'middle';

    // Zone négative (sous le départ) : lecture immédiate du drawdown.
    if (useProgress) {
        const y0 = y(0);
        if (y0 < h - padB) {
            ctx.save();
            ctx.fillStyle = 'rgba(255,0,85,0.07)';
            ctx.fillRect(padL, y0, w - padL - padR, h - padB - y0);
            ctx.restore();
        }
    }

    // Ligne de DÉPART
    if (startLine != null) {
        ctx.save();
        ctx.strokeStyle = 'rgba(160,153,184,0.7)';
        ctx.setLineDash([3, 3]);
        ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(padL, y(startLine)); ctx.lineTo(w - padR, y(startLine)); ctx.stroke();
        ctx.fillStyle = 'rgba(160,153,184,0.95)';
        ctx.fillText(useProgress ? 'départ 0%' : 'départ', w - padR + 4, y(startLine));
        ctx.restore();
    }

    // Ligne OBJECTIF
    if (targetLine != null) {
        ctx.save();
        ctx.strokeStyle = 'rgba(255,199,0,0.85)';
        ctx.setLineDash([4, 3]);
        ctx.lineWidth = 1;
        ctx.beginPath(); ctx.moveTo(padL, y(targetLine)); ctx.lineTo(w - padR, y(targetLine)); ctx.stroke();
        ctx.fillStyle = 'rgba(255,199,0,0.98)';
        ctx.fillText(useProgress ? 'objectif 100%' : 'objectif', w - padR + 4, y(targetLine));
        ctx.restore();
    }

    // Courbe du solde/progression
    if (series.length >= 2) {
        const last = series[series.length - 1];
        const rising = useProgress ? last >= 0 : last >= (startLine != null ? startLine : series[0]);
        const color = rising ? 'rgba(0,230,118,0.95)' : 'rgba(255,0,85,0.95)';
        const fill = rising ? 'rgba(0,230,118,0.18)' : 'rgba(255,0,85,0.18)';
        ctx.save();
        ctx.beginPath();
        series.forEach((v, i) => { const px = x(i), py = y(v); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); });
        ctx.lineTo(x(series.length - 1), h - padB);
        ctx.lineTo(x(0), h - padB);
        ctx.closePath();
        ctx.fillStyle = fill;
        ctx.fill();
        ctx.beginPath();
        series.forEach((v, i) => { const px = x(i), py = y(v); i ? ctx.lineTo(px, py) : ctx.moveTo(px, py); });
        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.stroke();
        const lx = x(series.length - 1), ly = y(last);
        ctx.fillStyle = rising ? '#00E676' : '#FF0055';
        ctx.beginPath(); ctx.arc(lx, ly, 2.5, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = rising ? '#00E676' : '#FF0055';
        const label = useProgress
            ? ((last >= 0 ? '+' : '') + last.toFixed(1) + '%')
            : last.toFixed(6);
        ctx.fillText(label, Math.max(padL, Math.min(lx + 4, w - padR - 2)), Math.max(padT, ly - 8));
        ctx.restore();
    } else if (series.length === 1) {
        ctx.save();
        ctx.fillStyle = 'rgba(0,229,255,0.95)';
        ctx.beginPath(); ctx.arc(x(0), y(series[0]), 2.5, 0, Math.PI * 2); ctx.fill();
        const label = useProgress
            ? ((series[0] >= 0 ? '+' : '') + series[0].toFixed(1) + '%')
            : series[0].toFixed(6);
        ctx.fillText(label, Math.min(x(0) + 4, w - padR - 2), Math.max(padT, y(series[0]) - 8));
        ctx.restore();
    }
}
