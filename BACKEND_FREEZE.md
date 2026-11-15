# BattlePool — Backend Freeze (2026-11-12)

> État figé avant le chantier frontend. Vérifié sur le VPS le 2026-11-12.

## 1. Git

- Branche : **`feat/session-recovery`**
- Commit : **`c296c55`** (`ops(engine): disable game:engine when sim-memory is active`)
- Tag : **`backend-freeze-2026-11-13`** (r1 `.1`, r2 `.2`, r3 `.3`, r4 engine54)
- Docs : `FINAL_REPORT.md`, `MEMORY_BACKUP.md`, `SESSION_SUMMARY.md` (§1→§52),
  `ENGINE_ARCHITECTURE.md`, `BACKEND_FREEZE.md`.

## 2. Images (VPS, buildées localement)

| Image | ID | Contenu |
|---|---|---|
| `battlepool-app:engine54` | `` | Laravel/Octane + frontend Vite + queue + engine + sim |
| `battlepool-reverb:engine54` | `` | Même image, commande `reverb:start` |
| `battlepool-bridge:engine54` | `43e6aa6c4175` | Node bridge/indexer + batch processor |
| `mysql:8.0` / `redis:alpine` | — | DB / cache-stream |

`/opt/battlepool/.env` : `GIT_SHA=engine54` ⇒ tout `docker compose up` réutilise ces images.

## 3. Conteneurs (état figé)

| Service | Image | État | Rôle |
|---|---|---|---|
| battlepool-app | app:engine54 | healthy | API/front |
| battlepool-queue-worker | app:engine54 | healthy | jobs |
| battlepool-queue-worker-limits-1 | app:engine54 | healthy | jobs limites |
| battlepool-scheduler | app:engine54 | up | outbox/refund/fights:archive |
| **battlepool-sim-memory** | app:engine54 | **healthy (moteur actif)** | **jeu in-memory (bots + humains)** |
| battlepool-bridge | bridge:engine54 | healthy | indexer / signatures / tx |
| battlepool-reverb | reverb:engine54 | healthy | WebSocket |
| battlepool-db / redis | mysql/redis | healthy | DB / Redis |
| battlepool-engine | app:engine54 | **Created/arrêté** (profil `db-engine`) | moteur DB |
| battlepool-batch-processor | bridge:engine54 | **Created/arrêté** (profil `db-engine`) | pools DB |

`/opt/battlepool/docker-compose.prod.yml` : services `engine` et `batch-processor` ont
`profiles: ["db-engine"]` ⇒ `docker compose up` **ne les démarre pas**. Mode exclusif sim.
Garde-fous : `GAME_ENGINE_SIM_MEMORY=true` désactive `/metrics/collect`,
`InternalPoolService`, `BatchProcessingService` ; `game:engine` s'arrête immédiatement si
`sim_memory_active`.

## 4. Configuration backend (`.env.staging`)

```
BANKROLL_ENABLED=true
BANKROLL_SESSION_TARGET_Q=1.2
BANKROLL_FEE_RATE=0.04
BANKROLL_SECURITY_COEFFICIENT=100
REFUND_ENABLED=true
REFUND_PCT=1.0
SIM_INCLUDE_HUMANS=true
SIM_HUMAN_REFRESH_SECONDS=5
GAME_ENGINE_SIM_MEMORY=true
GAME_ENGINE_DEFER_SIDE_EFFECTS=true
GAME_ENGINE_SIM_NO_PAYOUT=true
```

Sauvegarde : `.env.staging.bak.fee4.20261112135244` (+ backups précédents).

## 5. Blockchain (Pingala L1, chainId 99999)

- Contrat **BattlepoolV2** : `0xb628C8dE0ed6c1192dEB4C4C336fe3595370385E`
- `feeBasisPoints` : **400 (4 %)** — tx `0xa25fef37…a3350f`
- Owner : `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` (faucet/owner)
- AVAXe : `0x9d8AA35058493Aa826be143B568BCfA8E2F537F7`
- `GameSetting.smart_contract_fee_percentage = 4`
- Synchros test wallet : `updateUserBalance` (0xaf30… / 0x88f2… / etc.)

## 6. Modèle P3 figé (T=1.2, base 0.0004, sécurité 100, frais 4 %)

```
C = 0.04
t = 0.2
φ = 0.04
X = (1+t+φ·T)/t = 6.24
R = C·X = 0.2496
B = C+R = 0.2896
Dépôt = B·(1+φ) = 0.301184
Seuil session 1 = C·T·(1+φ) = 0.04992
Seuil rattrapage = R·T·(1+φ) = 0.3115008
Couverture fonds = φ·T/q ≈ 121 %
```

Fonds de remboursement : **2.00055680 AVAXe** ; remboursement **100 % de B = 0.2896** ;
file FIFO sans dette en attente (`pending_refunds=0`).

## 7. DB / migrations

- Migration `2026_11_11_000000_add_pending_bankroll_total_to_users` appliquée.
- Tables P3/outbox/refund présentes : `bankroll_*` sur `users`, `refund_queue`,
  `refund_ledger`, `refund_fund_state`, `side_effect_outbox`.
- `fights` conservés pour les combats humains (insert `pool_id=null`) ; `f_hists`/IPFS
  restent à corriger (audit incomplet, voir §9).

## 8. Tests (sur engine54)

- Headless E2E : **24/24**.
- Navigateur Playwright (SPA/Setup P3) : **23/23**.
- Unitaires : `bankroll_state_machine_test` 24/24, `refund_queue_simulation` OK.

## 9. Limites connues (non bloquantes pour le frontend)

- **4807 failed_jobs** historiques (panne gas) dont **568 ProcessPayoutJob** à auditer/requeue.
- Audit `f_hists`/IPFS incomplet (le JSONL `fights:archive` est dans le conteneur scheduler,
  non partagé ; la table `fights` est purgée via les pools).
- File de tx bridge parfois chargée (synchro `updateUserBalance` en retard) ; le front
  `/api/claim/sync` + retry compense.
- Front en cache : **hard refresh** requis après changement de frais/config.

## 10. Opérations

```sh
# logs
docker logs -f battlepool-sim-memory
docker logs -f battlepool-engine
docker logs -f battlepool-bridge
tail -f /root/logs/watch_0x70FE.log
tail -f /root/logs/gas_refill.log

# basculer sur le moteur DB (exclusif)
docker stop battlepool-sim-memory
cd /opt/battlepool && docker compose -f docker-compose.prod.yml --profile db-engine up -d engine batch-processor

# revenir au in-memory
docker stop battlepool-engine battlepool-batch-processor
cd /opt/battlepool && GIT_SHA=engine54 docker compose -f docker-compose.prod.yml up -d --no-deps sim-memory

# tests
sh tests/headless_e2e.sh
# navigateur : image Playwright + NODE_PATH=$(npm root -g) node tests/browser/headless_ui.cjs
```

## 11. Frontend — prochain chantier

Le backend est figé. Prochains sujets frontend : cohérence frais 4 %/remboursement 100 %,
affichage P3 (C/R/B/dépôt), events live, claim/sync, cache/hard-refresh, UX mobile.

## 12. Faucet AVAXe/TST — correctif post-freeze (2026-11-12)

**Symptôme** : les airdrops UI échouaient (`ProcessAvaxeFaucetJob` → HTTP 503
« Faucet wallet not configured (FAUCET_WALLET_PK missing) »).

**Cause** : le conteneur **bridge** n'avait pas le secret `faucet_wallet_pk`.

**Correctif** :
- `docker-compose.prod.yml` : secret `faucet_wallet_pk` ajouté au service **bridge** (et au bloc
  `secrets:` global) ; bridge recréé en `battlepool-bridge:engine54`.
- Bridge configure `faucetWallet = 0xD629…63Ee` (owner) ; AVAXe/TST disponibles.
- Job `ProcessAvaxeFaucetJob(1407, 5)` rejoué avec succès :
  **5 AVAXe** envoyés, tx `0x4baeec16…b5fb316` ; `has_received_avaxe_airdrop=1`.
- Le faucet TST (`/faucet`) est également débloqué par le même secret.

Sauvegarde compose : `docker-compose.prod.yml.bak.faucet.*`.


## 13. Frontend fixes (engine54, 2026-11-12)

- **Toasts au-dessus des overlays** : conteneur en **Popup/Popover top-layer**
  (`popover="manual"`, `showPopover()`, append sur `<html>`, z-index max). Vérifié
  Playwright : toast visible au-dessus de la modale marketplace.
- **Bouton « Réclamer 5 AVAXe »** : masqué après claim (`window.__bpAvaxeFaucetClaimed`)
  et via `/api/user/status.has_received_avaxe_airdrop` (nouveau champ backend + tx hash).
  DB : user 1407 `has_received_avaxe_airdrop=1`, tx `0x4bae…`.
- Tests : headless **24/24**, navigateur **23/23**.


## 14. Incident disque + WalletConnect (engine54, 2026-11-13)

- **Disque 100 %** : `battlepool-sim-memory` avait 33 Go de JSONL (`fights-archive`).
  Nettoyé + `docker builder prune` (~40 Go) → disque ~33 %.
- **Archive JSONL du sim désactivée** (`SIM_ARCHIVE_ENABLED=false`) ; consumer d'audit
  conservé dans le scheduler.
- **WalletConnect/Reown** : `ARG/ENV VITE_WALLETCONNECT_PROJECT_ID` ajouté à
  `deploy/Dockerfile.prod` + `/opt/battlepool/.env` ; build engine54 avec le ProjectId.
  Plus de `Project not found` ; AppKit/legacy opérationnels.
- Tests : headless 24/24, navigateur 23/23.


## 15. UI feedback user — engine55 (2026-11-13)

**Symptômes signalés** : (a) l'overlay de la modale marketplace cache les messages
type toast ; (b) le bouton « Réclamer 5 AVAXe » reste visible après claim.

**Constats** :
- Les captures/logs du user datent du **12/11 16:54** et du **13/11 12:35**, donc
  **avant** engine54/engine55 et pendant l'incident disque + ProjectId manquant.
- Le « toast » masqué était la notification **marketplace** (`#mp-notification` via
  `_showMpNotification`), pas le toast global : elle restait dans la page, sous
  `.mp-modal-overlay` (z-index 1100).
- Le bouton AVAXe pouvait être réaffiché par `auth:success`/`checkAuthSession`
  quand `has_received_avaxe_airdrop` était **absent** (`!undefined === true`).

**Correctifs (engine55, commit `f6aa84d`)** :
- `_showMpNotification` appelle désormais aussi `window.showToast(...)` (top-layer,
  popover, z-index max) → message visible au-dessus de la modale.
- `#avaxe-faucet-btn` est **masqué par défaut** ; affiché uniquement si le serveur dit
  explicitement `has_received_avaxe_airdrop === false` (auth, session locale, polling).
- Build/deploy engine55 : app/queue/scheduler/sim/bridge/reverb ; `.env`
  `GIT_SHA=engine55` ; engine/batch restent Created (profil `db-engine`).

**Vérifications** :
- Marketplace + modale ouverte + clic « Approuver » sans wallet : toast
  `bp-toast-1` en `:popover-open`, z-index `2147483647`, `topEl=bp-toast-1`.
- User test `has_received_avaxe_airdrop=1` : après auth injectée,
  `#avaxe-faucet-btn` → `display:none` (user test supprimé).
- Tests : headless **24/24**, navigateur **23/23**.

**Action restante côté user** : **hard refresh / désinscrire le service worker**
pour charger engine55, puis retester ; le log WalletConnect de 12:35 ne doit plus
afficher `Project not found`.


## 16. Marketplace « Confirm in your wallet… » bloqué — engine56 (2026-11-13)

**Symptôme** : après confirmation dans le wallet, la modale « Create Offer » reste
sur le bouton désactivé « CONFIRM IN YOUR WALLET… » et « CREATE OFFER » grisé.

**Diagnostic on-chain (user 1407)** :
- nonce Pingala **latest = pending = 184** → **aucune tx approve n'a été broadcastée** ;
- allowance PRANA→marketplace = **1.7** (< 5 demandés), balance PRANA = 139.7.
- Le wallet a probablement confirmé le switch réseau / une autre demande, ou la
  réponse WalletConnect/Core s'est perdue. L'ancien code attendait `tx.wait()`
  jusqu'à **120 s** sans changer l'UI.

**Correctif (engine56, commit `ba14daf`)** :
- `ensurePingalaNetwork` **re-vérifie `eth_chainId` après le switch** ; si le wallet
  n'est pas sur Pingala 99999, erreur immédiate au lieu d'une tx sur le mauvais réseau.
- `handleApprove` vérifie la chaîne live avant l'envoi, puis envoie l'approve avec
  `waitForReceipt:false` : hash loggé, label « approbation en cours », puis
  **polling de l'allowance** (45 s). Si l'allowance atteint le montant, l'étape est
  validée même si le receipt est perdu (WC/Core).
- Timeout d'envoi ramené à **60 s** avec reset du bouton ; un retry est sûr : si la
  tx a été minée, l'allowance est détectée au début sans renvoyer de transaction.

**Vérification** : wallet test approvisionné 0.2 TST, approbation 5 PRANA
(tx `0x5ceed1c361d2ec14a2c38047b480aa08a08c9c9629df37517dea9794c6878fac`) →
allowance confirmée, bouton `CREATE OFFER` activé, toast « Approval successful ».
User test supprimé. Navigateur **23/23**.

**Côté user** : hard refresh, vérifier que Core est bien sur **Pingala Chain
(99999)**, puis réessayer. Si ça dépasse 60 s, le bouton se réinitialise : cliquer
à nouveau sur Approuver ; si la tx est passée, l'allowance sera détectée sans
nouvelle transaction.


## 17. Frontend mobile / PWA — engine60 (2026-11-14)

**Objectif** : réduire les bugs de connexion sur réseau mobile faible, accélérer
le lancement PWA, rendre l'ouverture du wallet plus fiable et afficher les
montants AVAXe/TST/PRANA avec **6 décimales**.

**PWA / démarrage**
- Service Worker **v6** : app shell et bundle Vite en **cache-first + revalidation
  en arrière-plan** (HTML, `app.js`, CSS, locales) ; pré-cache des assets locaux
  et des locales `?v=6`. Le cache est invalidé par bump de `CACHE_NAME` à chaque
  déploiement (cycle install → skipWaiting → reload pwa.js).
- Installation SW non bloquante : les assets externes Google Fonts sont
  best-effort, un échec CDN ne bloque plus le SW.
- i18n : cache `localStorage` (`bp_i18n_v6_<locale>`), URL stable (plus de
  `?v=Date.now()`), timeout 6 s au premier chargement, refresh arrière-plan.
- Polices Google non bloquantes + `preconnect` RPC Pingala, relais WalletConnect
  et API Reown. Warm-up des chunks AppKit/WC dès 1,8 s (plus seulement en idle).
- Mesure Playwright mobile (390 px, réseau bridé 50 kb/s après 1er chargement) :
  reload PWA **~313 ms**, aucun débordement horizontal.

**Connexion wallet mobile**
- Attente de l'URI WalletConnect portée de **8 s → 20 s** (réseau faible).
- `authFetch` : timeout **30 s**, retry réseau/5xx avec backoff.
- Ouverture auto du wallet tentée (deeplink natif) puis **bandeau d'action
  « Ouvrir Core Wallet »** si Safari/iOS ignore le scheme (le geste utilisateur
  est alors réel) ; même mécanisme pour la signature `personal_sign`.
- WARM-UP WalletConnect/AppKit renforcé au démarrage et au premier geste.

**Montants**
- Utilitaire unique `resources/js/core/format.js` (`formatAmount`, 6 décimales).
- Header (TST/PRANA/AVAXe), arène, claim, P3, marketplace, stats et graphique
  passent à **6 décimales** ; placeholders HTML alignés.

**Tests**
- Playwright mobile : auth injectée OK, header `TST 0.198818` / `PRANA 0.000000` /
  `AVAXe 0.000000`, solde `0.000000 AVAXe`, SW v6 + locales en cache.
- PWA lente : reload **313 ms** avec cache ; SW controller actif.
- Headless **24/24**, navigateur **23/23**.
- Note : un backlog `side_effect_outbox` de ~11 k événements provenait d'un
  utilisateur humain simulé (user 322, session longue) ; nettoyé pour le test.
  À surveiller si un utilisateur simulé génère à nouveau un gros volume.


## 18. PWA mobile — session restaurée sans wallet / claim en boucle — engine61 (2026-11-15)

**Log terrain (iPhone PWA, user 322)** : `Session restaurée: 0x1f14…`, puis
`WC {"hasSession":false,"provider":null}`, et chaque tap sur Claim :
`switch Pingala échoué Aucun provider Web3 détecté` + toast répété.
Cause : l'`auth_token` backend est restauré depuis localStorage, mais le
provider WalletConnect n'existe plus en mémoire après relance de la PWA.
Sans provider, aucune action on-chain (claim, session, marketplace) ne peut
aboutir.

**Correctifs**
- `restoreWalletConnectionSilently()` : au boot (et avant `ensurePingalaNetwork`),
  ré-adoption **sans modal ni deeplink** d'une session WC persistée
  (`wc@2`/UniversalProvider) si elle existe.
- Si aucune session WC persistée : **bandeau “Wallet déconnecté — session
  restaurée sans wallet” + bouton Reconnecter** (ouvre le flux WalletConnect
  mobile ou injected selon l'appareil). Retiré sur `auth:success`.
- Claim : si le provider manque, restauration silencieuse puis **reconnexion
  automatique** (`connectWallet('walletconnect')` sur mobile) avant de
  poursuivre le claim. Garde `claimInProgress` + cooldown 3 s : plus de rafale
  de tentatives/toasts sur double-tap.
- Toasts : déduplication des messages identiques sur 1,5 s (anti-spam mobile).
- SW **v7** pour invalider le bundle cache-first (engine61).

**Vérification Playwright mobile**
- Auth normale OK, puis nouvelle PWA avec `auth_token` restauré et **aucun
  provider** : bannière `Wallet déconnecté — session restaurée sans wallet. /
  Reconnecter / ✕` présente, aucun `Aucun provider Web3` au boot, pas d'erreur
  JS.
- Navigateur **23/23** ; headless backend **24/24** (hors backlog sim, cf. §17).

**Action user** : recharger la PWA (SW v7), puis appuyer sur **Reconnecter**
(ou Claim, qui déclenche la reconnexion) ; le wallet se rouvre, la signature
`claimAndExit` peut alors être envoyée.


## 19. Coefficient de sécurité — log trompeur (engine62, 2026-11-15)

**Retour user 322** : le log `[Business] 2/4 tx : coeff= 1000` laissait croire
que le coefficient dépassait la valeur demandée (100).

**Réalité** : il y a deux champs :
- `security_coefficient = 1000` : ancien champ legacy (GameSetting / artefacts),
  **non utilisé** par le modèle bankroll P3 ;
- `bankroll_security_coefficient = 100` : valeur effective
  (`BANKROLL_SECURITY_COEFFICIENT=100`), affichée dans l'UI `display-coeff`.

Calcul réel du dépôt (baseBet 0.0004, T=1.2, φ=4 %) :
- C = 0.0004 × 100 = **0.04**
- X = 6.24 ; R = 0.04 × 6.24 = **0.2496**
- B = C + R = **0.2896**
- frais 4 % = **0.011584**
- total = **0.301184**
Avec un coefficient 1000, le total serait 3.01184 (10× plus) : ce n'est donc
pas 1000 qui est appliqué.

**Correctif** : le log affiche désormais `coeff effectif= 100 legacy= 1000`.
SW **v8** pour invalider le bundle. Si une autre valeur est réellement voulue,
il faut changer `BANKROLL_SECURITY_COEFFICIENT` dans `.env.staging` puis
recréer app/sim ; le total B/fee suit la formule ci-dessus.
