// resources/js/modules/stats.js
/**
 * Statistiques publiques (bandeau "LIVE" + ticker de combats).
 *
 * Source : GET /api/stats/public (endpoint SANS authentification, côté Laravel).
 * - fetch brut RELATIF (même origine : desktop http et mobile Tailscale https
 *   via le proxy bp-proxy-tls qui route /api). `secureFetch` est écarté car il
 *   exige un Bearer token inutile ici.
 * - L'endpoint peut être momentanément indisponible (image pas encore rebuildée,
 *   bridge down...) : il peut répondre 200 avec du HTML de fallback SPA, du JSON
 *   partiel, ou des valeurs null (tvl/fees). L'UI ne casse JAMAIS : les valeurs
 *   indisponibles affichent '--', jamais null/NaN.
 *
 * Ticker : l'endpoint public n'expose PAS les combats individuels (données
 * personnelles). Le ticker est alimenté en temps réel par l'événement privé
 * `game:fightResult` (re-dispatché sur `window` par core/echo.js à partir du
 * canal privé Echo). S'il n'y a ni data endpoint ni event, le ticker reste
 * simplement vide.
 */

const STATS_ENDPOINT = '/api/stats/public';
const TICKER_MAX_ITEMS = 8;

// État module-level (pas de double-intervalpossible : un seul id)
let _statsPollTimerId = null;
let _lastStats = null;          // payload normalisé du dernier fetch réussi
let _recentFights = [];         // items du ticker (plus récent en tête)

// ─── HELPERS ─────────────────────────────────────────────────────────────────

/**
 * Tronque une adresse wallet : 0x1234...abCD (4 premiers / 4 derniers).
 * Même pattern que game.js (substring(0,6) + '...' + 4 derniers).
 */
export function truncateWallet(addr) {
    const w = String(addr || '').trim();
    if (!w) return '';
    if (w.length <= 12) return w;
    return w.substring(0, 6) + '...' + w.substring(w.length - 4);
}

/** Échappement HTML minimal (les wallets proviennent du serveur/localement). */
function escapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** Entier formaté FR (séparateur milliers), '' si indisponible. */
function fmtInt(n) {
    const v = Number(n);
    if (n === null || n === undefined || n === '' || !Number.isFinite(v)) return '';
    return Math.round(v).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
}

/** SNT avec 2 décimales (fr-FR), '' si indisponible/négatif incohérent. */
function fmtSnt(n) {
    const v = Number(n);
    if (n === null || n === undefined || n === '' || !Number.isFinite(v)) return '';
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** Mise AVAX avec 4 décimales (fr-FR), '' si indisponible. */
function fmtAvax4(n) {
    const v = Number(n);
    if (n === null || n === undefined || n === '' || !Number.isFinite(v)) return '';
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
}

/**
 * Adapter tolérant : accepte la shape "cible" de la spec
 * ({token_purchases:{d30,d7,d24h}, p2p:{...}, on_chain:{tvl, fees_collected}})
 * ET la shape réellement livrée côté backend
 * ({token_purchases:{'30d','7d','24h'}, marketplace:{...}, on_chain:{tvl_snt, fees_snt}}).
 * Sortie unique normalisée, valeurs manquantes => null.
 */
export function normalizePublicStats(raw) {
    const d = raw && typeof raw === 'object' ? raw : {};
    const tp = d.token_purchases && typeof d.token_purchases === 'object' ? d.token_purchases : {};
    const win = (key) => {
        const v = tp[key];
        if (!v || typeof v !== 'object') {
            return { total_snt: null, purchases: null, unique_players: null, avg_per_player: null };
        }
        return {
            total_snt: v.total_snt ?? null,
            purchases: v.purchases ?? v.count ?? null,
            unique_players: v.unique_players ?? null,
            avg_per_player: v.avg_per_player ?? null,
        };
    };
    // 'win()' renvoie TOUJOURS un objet (truthy) : on ne peut pas chaîner avec ??.
    // Prend la première clé présente dans le payload (ex: '24h' livré, 'd24h' spec).
    const firstWin = (keys) => {
        for (const k of keys) {
            if (tp[k] && typeof tp[k] === 'object') return win(k);
        }
        return win(null); // toutes absentes => null partout
    };
    const p2p = d.p2p ?? d.marketplace ?? {};
    const oc = d.on_chain && typeof d.on_chain === 'object' ? d.on_chain : {};
    return {
        fights: {
            total: d.fights?.total ?? null,
            last_24h: d.fights?.last_24h ?? null,
        },
        players: {
            total: d.players?.total ?? null,
            online: d.players?.online ?? null,
        },
        pools: {
            active: d.pools?.active ?? null,
        },
        token_purchases: {
            all: win('all'),
            d30: firstWin(['d30', '30d']),
            d7: firstWin(['d7', '7d']),
            d24h: firstWin(['d24h', '24h']),
        },
        p2p: {
            fulfilled_trades: p2p.fulfilled_trades ?? null,
            snt_volume: p2p.snt_volume ?? p2p.total_snt ?? null,
            avax_volume: p2p.avax_volume ?? p2p.total_avax ?? null,
        },
        on_chain: {
            tvl: oc.tvl ?? oc.tvl_snt ?? null,
            fees_collected: oc.fees_collected ?? oc.fees_snt ?? null,
            degraded: oc.degraded === true,
            collected_at: oc.collected_at ?? null,
        },
        generated_at: d.generated_at ?? null,
    };
}

// ─── DOM UTIL ────────────────────────────────────────────────────────────────

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

// ─── FETCH ───────────────────────────────────────────────────────────────────

/**
 * Récupère les stats publiques. Chemin RELATIF (même origine SPA, HTTPS mobile
 * inclus). Lève une exception si non-JSON (fallback HTML quand l'endpoint
 * n'est pas encore déployé) ou si HTTP KO — l'appelant décide du fallback UI.
 * @returns {Promise<object>} payload JSON brut du backend.
 */
export async function fetchPublicStats() {
    const res = await fetch(STATS_ENDPOINT, {
        headers: { 'Accept': 'application/json' },
        credentials: 'omit'
    });
    if (!res.ok) throw new Error(`[Stats] HTTP ${res.status} sur ${STATS_ENDPOINT}`);
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('json')) {
        throw new Error('[Stats] réponse non-JSON (endpoint indisponible ?) — ignorée');
    }
    return await res.json();
}

// ─── RENDER ──────────────────────────────────────────────────────────────────

/**
 * Remplit les compteurs du bandeau LIVE #stats-* (page Arène) et, si présents,
 * les compteurs du bloc Marketplace Stats #stats-mp-*.
 * Le préfixe optionnel permet de cibler un autre jeu d'éléments
 * (ex: renderStatsBar('mp-') sur des ids type #mp-stats-*).
 * Valeurs indisponibles (null / non-JSON / fetch KO) => '--', jamais null/NaN.
 * @param {string} [prefix=''] préfixe d'id optionnel.
 * @param {object} [data=null] payload déjà normalisé (sinon _lastStats).
 */
export function renderStatsBar(prefix = '', data = null) {
    const p = data || _lastStats;
    const id = (name) => `${prefix ? prefix : ''}stats-${name}`;
    const dash = (v) => (v === '' ? '--' : v);

    if (!p) {
        // Aucune data : garantir le fallback '--' sur tous les éléments connus
        for (const elId of ['stats-battles-total', 'stats-players-online', 'stats-pools-active',
            'stats-tvl', 'stats-fees', 'stats-snt-avg-all', 'stats-snt-avg-30d',
            'stats-snt-avg-7d', 'stats-snt-avg-24h', 'stats-mp-p2p-volume',
            'stats-mp-fulfilled-trades', 'stats-mp-avg-24h']) {
            setText(elId, '--');
        }
        return;
    }

    const tp = p.token_purchases || {};

    // Page Arène
    setText(id('battles-total'), dash(fmtInt(p.fights?.total)));
    setText(id('players-online'), dash(fmtInt(p.players?.online)));
    setText(id('pools-active'), dash(fmtInt(p.pools?.active)));
    // TVL / fees : peuvent être null (bridge down) => '--'
    setText(id('tvl'), dash(fmtSnt(p.on_chain?.tvl)));
    setText(id('fees'), dash(fmtSnt(p.on_chain?.fees_collected)));

    // Moyennes SNT par joueur (4 fenêtres)
    setText(id('snt-avg-all'), dash(fmtSnt(tp.all?.avg_per_player)));
    setText(id('snt-avg-30d'), dash(fmtSnt(tp.d30?.avg_per_player)));
    setText(id('snt-avg-7d'), dash(fmtSnt(tp.d7?.avg_per_player)));
    setText(id('snt-avg-24h'), dash(fmtSnt(tp.d24h?.avg_per_player)));

    // Bloc Marketplace Stats (si le conteneur existe)
    setText('stats-mp-p2p-volume', dash(fmtSnt(p.p2p?.snt_volume)));
    setText('stats-mp-fulfilled-trades', dash(fmtInt(p.p2p?.fulfilled_trades)));
    setText('stats-mp-avg-24h', dash(fmtSnt(tp.d24h?.avg_per_player)));
}

// ─── TICKER COMBATS RÉCENTS ──────────────────────────────────────────────────

/**
 * Construit une ligne de ticker à partir d'un payload d'événement fight.
 * Format : `Wallet 0xA12...F3 ⚔️ — résultat — mise 0.0004 AVAX`.
 * NB : le payload FightResult ne transporte PAS l'adresse de l'adversaire
 * (canal privé) — la ligne dégrade proprement sans inventer de wallet.
 */
function buildTickerItemText(detail) {
    const user = detail?.user || {};
    const wallet = user.wallet_address || user.wallet
        || window.userState?.walletAddress || '';
    const walletTxt = truncateWallet(wallet) || '???';

    const resultKey = String(detail?.result || 'draw');
    const resultLabel = window.t
        ? window.t(`stats.result_${resultKey}`) : resultKey;

    let bet = null;
    if (detail?.delta !== undefined && detail?.delta !== null) {
        const dv = parseFloat(detail.delta);
        bet = Number.isFinite(dv) ? Math.abs(dv) : null;
    }
    if (bet === null) {
        bet = parseFloat(window.userState?.bet_amount);
        bet = Number.isFinite(bet) ? bet : null;
    }
    const betTxt = bet === null ? '--' : fmtAvax4(bet);

    return `${walletTxt} ⚔️ ${resultLabel} — ${window.t && window.t('stats.ticker_bet') || 'mise'} ${betTxt} AVAX`;
}

/**
 * Rend le ticker dans #fight-ticker (s'il existe — sinon no-op, jamais crash).
 * @param {Array<object|string>} [fights] — liste optionnelle pour recharger le
 *        ticker en masse (sinon l'état interne est conservé).
 */
export function renderFightTicker(fights) {
    if (Array.isArray(fights)) {
        _recentFights = fights.slice(0, TICKER_MAX_ITEMS);
    }
    const el = document.getElementById('fight-ticker');
    if (!el) return; // pas d'élément => vide, sans crash

    if (!_recentFights.length) {
        el.innerHTML = '';
        el.style.display = 'none';
        return;
    }

    el.style.display = '';
    el.innerHTML = _recentFights
        .map((f) => `<span class="fight-ticker-item">${typeof f === 'string' ? escapeHtml(f) : f}</span>`)
        .join('');
}

/**
 * Push un combat (payload d'event ou texte prêt) en tête du ticker.
 * Les plus vieux items partent au-delà de 8.
 */
export function pushTickerFight(fight) {
    if (fight === null || fight === undefined) return;
    _recentFights.unshift(fight);
    if (_recentFights.length > TICKER_MAX_ITEMS) {
        _recentFights.length = TICKER_MAX_ITEMS;
    }
    renderFightTicker();
}

function onFightResultForTicker(e) {
    try {
        pushTickerFight(buildTickerItemText(e?.detail));
    } catch (err) {
        // Le ticker ne doit JAMAIS casser le reste de l'app
        console.warn('[Stats] ticker: payload FightResult inattendu', err);
    }
}

// ─── POLLING ─────────────────────────────────────────────────────────────────

/**
 * Démarre le polling léger des stats publiques (défaut : 30 000 ms).
 * Pas de double-interval : l'éventuel timer existant est d'abord nettoyé.
 * @param {number} [intervalMs=30000]
 */
export function startStatsPolling(intervalMs = 30000) {
    if (_statsPollTimerId) clearInterval(_statsPollTimerId);
    _statsPollTimerId = setInterval(() => {
        refreshStats().catch(() => { /* silencieux : on retentera au prochain tick */ });
    }, intervalMs);
}

/** Stoppe le polling des stats (idempotent). */
export function stopStatsPolling() {
    if (_statsPollTimerId) {
        clearInterval(_statsPollTimerId);
        _statsPollTimerId = null;
    }
}

/** Un fetch + un render. Erreurs silencieuses (valeur '--' conservée). */
async function refreshStats() {
    try {
        const raw = await fetchPublicStats();
        _lastStats = normalizePublicStats(raw);
        renderStatsBar();
    } catch (e) {
        // Endpoint indisponible (image pas rebuildée, bridge down...) :
        // on conserve l'affichage précédent (ou '--' initial), sans spam console.
    }
}

// ─── INIT ────────────────────────────────────────────────────────────────────

/**
 * Point d'entrée appelé par app.js au boot, APRÈS le routeur.
 * Ne démarre le polling/écouteur que si le DOM contient les éléments stats.
 * @param {number} [intervalMs=30000]
 */
export function initStats(intervalMs = 30000) {
    const hasStatsEls = document.getElementById('fight-ticker')
        || document.querySelector('[id^="stats-"]');
    if (!hasStatsEls) {
        console.info('[Stats] aucun élément #stats-* / #fight-ticker dans le DOM — module inactif');
        return;
    }

    // Valeurs initiales '--' avant le premier rendu
    renderStatsBar();

    // Premier rendu immédiat, puis polling
    refreshStats().then(() => startStatsPolling(intervalMs));

    // Ticker : alimenté par les FightResult temps réel (canal privé Echo,
    // re-dispatché 'game:fightResult' par core/echo.js)
    if (document.getElementById('fight-ticker')) {
        window.addEventListener('game:fightResult', onFightResultForTicker);
    }
}