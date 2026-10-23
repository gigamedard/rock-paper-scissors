// resources/js/core/audio.js
// Petits effets sonores générés via Web Audio API (aucun asset requis).
// Un son différent par type d'animation. Mute persistant (localStorage).

let ctx = null;
let muted = localStorage.getItem('bp_sound_muted') === '1';

function ensureCtx() {
    if (!ctx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return null;
        try { ctx = new AC(); } catch (e) { return null; }
    }
    if (ctx.state === 'suspended') {
        try { ctx.resume(); } catch (e) { /* noop */ }
    }
    return ctx;
}

function tone(freq, when, dur, type, vol) {
    const c = ensureCtx();
    if (!c) return;
    const o = c.createOscillator();
    const g = c.createGain();
    o.type = type || 'sine';
    o.frequency.value = freq;
    const t0 = c.currentTime + (when || 0);
    const v = vol || 0.12;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(v, t0 + 0.012);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    o.connect(g);
    g.connect(c.destination);
    o.start(t0);
    o.stop(t0 + dur + 0.03);
}

export function playSound(name) {
    if (muted) return;
    switch (name) {
        case 'win':
            [523.25, 659.25, 783.99].forEach((f, i) => tone(f, i * 0.09, 0.22, 'triangle', 0.13));
            break;
        case 'loss':
            [392.0, 329.63, 261.63].forEach((f, i) => tone(f, i * 0.1, 0.24, 'sawtooth', 0.08));
            break;
        case 'draw':
            tone(440, 0, 0.14, 'sine', 0.1);
            tone(440, 0.13, 0.14, 'sine', 0.1);
            break;
        case 'pool':
            tone(880, 0, 0.12, 'sine', 0.12);
            tone(1174.66, 0.11, 0.16, 'sine', 0.12);
            break;
        case 'claim':
            tone(987.77, 0, 0.11, 'triangle', 0.13);
            tone(1318.51, 0.09, 0.2, 'triangle', 0.13);
            break;
        case 'error':
            tone(160, 0, 0.28, 'sawtooth', 0.07);
            break;
        case 'click':
        default:
            tone(660, 0, 0.06, 'square', 0.05);
            break;
    }
}

export function isSoundMuted() { return muted; }

export function toggleSound() {
    muted = !muted;
    try { localStorage.setItem('bp_sound_muted', muted ? '1' : '0'); } catch (e) { /* noop */ }
    if (!muted) playSound('click');
    return muted;
}

// À appeler sur le premier geste utilisateur (politique autoplay navigateur).
export function unlockAudio() { ensureCtx(); }
