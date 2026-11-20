# SESSION_SUMMARY.md — Résumé d'état pour reprendre le projet

> À lire **en premier** dans une nouvelle conversation. État consolidé à la fin de la
> session du 2026-10-23. Complète `BP_SESSION_STATE.md` (mémoire longue) et
> `docs/COLLAB_STATE.md` (protocole LOCAL↔CLOUD).

---

## 0. Où on en est (les 10 lignes)

- Projet **BattlePool** : jeu PvP RPS on-chain, L1 **Pingala** (chainId **99999**, natif **TST**),
  RPC `https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc`.
- **Prod** : `https://battlepool.31.187.72.98.sslip.io` — conteneurs `battlepool-*` healthy.
- **SHA déployé en prod** : app **`5982272`**, bridge/batch-processor/reverb **`6628503`**, queue-workers/scheduler **`fe5153a`** ; tip git **`8c67be7`** — branche `feat/avaxe-bridge` (voir §7/§8).
- **⚠️ Incident 2026-10-27 — `GAME_CURRENCY` perdu** : suite au deploy `6628503`, `GAME_CURRENCY` avait disparu de `.env.staging` → le bridge retombait sur le défaut `tst` → **toute l'app en V1/TST** (`/api/artefacts` renvoyait `game_currency:"tst"`, contrat V1 `0x0790…`), d'où les claims impossibles. **Corrigé** : `GAME_CURRENCY=avaxe` remis dans `.env.staging`, défaut `config.js` passé à `avaxe` (cloud `8c67be7` + fichier monté `/opt/battlepool/smart_contracts/config.js`). `/api/artefacts` renvoie de nouveau `avaxe` / V2 `0xb628…` / AVAXe `0x9d8A…`. Détail §8.
- **Migration TST → AVAXe FAITE** : devise de jeu = **AVAXe** (ERC-20 `0x9d8A…37F7`), contrat
  **BattlepoolV2** `0xb628C8dE…385E` (dépôt/payout/claim ERC-20). TST = gas natif uniquement.
- **71 bots** (réduit de 240 pour alléger la charge CPU ; `simulation_accounts.json`), pools de **5**.
- **Config matchmaking** : serveur `run_batch_processor.js` intervalle **1,57 s** (env `BATCH_PROCESSOR_INTERVAL`),
  **6 workers** Swoole, lock `metrics_collect_tick` TTL **10 s**, polling client **200 ms**.
- **Anti-deadlock** : le lock `metrics_collect_tick` est partagé serveur+client — `PoolAutoMatchController`
  (processBatch/processAllBetTiers/processInternalPools) l'acquiert comme `GameMetricsController` → un seul
  sémaphore global pour tout le matchmaking (corrige le `SQLSTATE 40001` deadlock). + `skipLocked` sur le
  `staleRunningBatches`.
- **Optimisations DB** (migrations) : index composites `f_hists`/`users`/`pools`/`pools(status,created_at)`,
  buffer pool **2 G**, `innodb_flush_log_at_trx_commit=2` (dans `docker-compose.prod.yml`, pas dans git),
  `recoverFinishedPools` en JOIN inversée, purge des pools finies >3 min (commande `battle:cleanup`).
- **Scheduler** : conteneur `battlepool-scheduler` (`php artisan schedule:work`) → `battle:cleanup` chaque
  minute (pools finies purgées). Service présent dans `docker-compose.yml` (git) ET `docker-compose.prod.yml` (VPS).
- **Simulateur** (`simulator/index.html`, Monte Carlo) : **Algo 5 « New Engine »** (pool par rounds : R1 ×2,
  R2 prend toute la battle balance ×4, R3+ ±base, nul/bye = bruit, éliminé si bal≤0) et **Algo 6** (R1 base,
  R2/R3 prennent le bénéfice en laissant la base, R4+ base, target ×2). `poolSize` défaut **13** (max **16**),
  `maxRounds` défaut 5. Estimations (stack 0.4/baseBet 0.0004) : Algo 5 → ~44 % succès, 0 % ruine, ~12-14k
  fights ; Algo 6 (martingale) → ~45 % succès mais ~44 % ruine ; Algo 6 (fixe) → target ×2 trop loin (~0,5 %).
  Constat : la **distance du target** (ruine du joueur) est le facteur clé — ×1.2 ≫ ×2.
- **Simulateur Algo 6 autonome** : `public/simulator-algo6.html` (HTML/CSS/JS, Monte Carlo, testable seul,
  sans dépendance).

---

## 1. Branches & SHA (à la fin de la session)

| Branche | SHA tip | Rôle |
|---|---|---|
| `feat/app1-deeplink-app2` | `9712b03` | **Ligne de travail principale** (toutes les features UI/game) |
| `feat/app1-claim-pingala-fix` | `3653ef0` | **Version corrigée du claim** (force Pingala) — mise de côté à la demande de l'opérateur |
| `feat/avaxe-bridge` | `9f78e9d` | **Branche active = prod** (migration AVAXe + 71 bots + index DB + purge pools + scheduler + fix claim §7) |
| `docs/app1-collab-protocol` | `8c14341` | **Protocole LOCAL↔CLOUD** (COLLAB_STATE + handoffs + onboarding) — PR → master |
| `docs/cloud-faucet-2026-10-18` | — | Passations/COLLAB_STATE (docs du protocole) |
| `master` | ancien | Intégration (PR uniquement, pas de push direct) |

**Historique clé de `feat/app1-deeplink-app2`** (ancien → récent) :
`4caab5f` (base) → `d9ef144` (auth) → `358137c` → `94e8104` → `33d5868` (personal_sign direct
window.ethereum) → `e63d70e` (objectif réel frais) → `dde3660` (graphique/sons/fond) →
`00a75c3` → `4b26ced` (PWA SW v4 + audio iOS) → `6901bf9` (120 bots) → `28d932c` (claim force
Pingala) → `3653ef0` (pool size 5) → `aadf825` (bouton claim dédié) → `8d1ad73` (revert aadf825) →
`0498cdd` (PoC) → `9712b03` (PoC WalletConnect).

**Prod = `e90529d`** (= `8d1ad73` en contenu, c'est-à-dire **sans** le bouton claim dédié,
**avec** le bug de réseau du claim de retour, **avec** pool size 5 et 120 bots).

---

## 2. Ce qui a été livré cette session (résumé)

### Jeu / backend
- **Airdrop faucet** : `POST /api/user/faucet-claim` (50 TST, one-shot, flag `has_received_airdrop`
  posé après succès on-chain). Faucet = owner `0xD629…` (~651 TST restants).
- **120 bots** : wallets générés (PK dans `/opt/battlepool/.secrets/bots_keys.json`, 600, HORS git),
  enrôlés (auth + IPFS premoves + `/user/pre-moves` + `deposit` 0.5 TST). Adresses publiques dans
  `smart_contracts/simulation_accounts.json` (124 entrées) pour l'auto-payout (`SessionManager::isBotUser`).
- **Pool size 5** : `config/pool.php` `size [5]` (Battle Royale ; le moteur boucle `pool_size` rounds,
  gère le « bye » des joueurs impairs).
- **PRANA** : 10 000 PRANA distribués à `0x1F140e5a3Cd2Fcd52862084397c2FAe172dFF4ed` (tx `0x27b35056…`).

### Front / UX
- **Auth MetaMask/Core** : `personal_sign` **direct** sur `window.ethereum` (méthode App2), résout
  « The account shown … is not part of the active wallet ».
- **Temps réel** : la clé Reverb était scrubée (`***REMOVED***`) → ajout `GET /api/broadcasting-key`
  (clé publique) + `echo.js` async. **Cause de l'overlay « waiting » figé.**
- **UX dashboard** : graphique solde→objectif (canvas, lignes départ/objectif, % progression),
  sons d'animation (Web Audio) + bouton mute, background `daima_bg.png`, bouton stratégie
  verrouillé pendant une session (`session_started`).
- **PWA** : SW v4 (network-first sur `/build/assets/*` + `/css/style.css`), auto-reload hors combat,
  audio iOS (`navigator.audioSession='playback'` + buffer silencieux).
- **Header** : badges soldes **TST** (natif) + **PRANA** (ERC-20) via RPC read-only.
- **Bouton « Ajouter PRANA au wallet »** (`wallet_watchAsset`).

### Sécurité / décisions
- **Bug claim réseau (AVAX vs Pingala)** : corrigé (`28d932c` : `ensurePingalaNetwork` avant
  `claimAndExit`) puis **reverté à la demande de l'opérateur** (prod sur `e90529d`). La version
  corrigée est conservée sur `feat/app1-claim-pingala-fix` (`3653ef0`).
- **PoC Core fee-sponsorship** : `poc/core-fee-sponsorship/` (ClaimPoc.sol + index.html single-shot
  injecté/WalletConnect + README). Contrat cible déployé sur Pingala : `0x21996AB1511Ddc4Cede2876D9CB27370997790Ff`.

### Chantier AVAX.e (nouveau, branche `feat/avaxe-bridge`)
- `battlepool/contracts/AVAXe.sol` : ERC-20 « Wrapped AVAX » (18 déc., mint/burn opérateur).
- `battlepool/contracts/BattlepoolV2.sol` : **port ERC-20 complet** de Battlepool (dépôt/payout/claim
  en `transfer`, `updateUserBalance` borné par `token.balanceOf`, `setToken` timelock).
- `docs/avaxe-bridge-plan.md` : plan P0→P5.
- **Choix validés** : bridge **custodial** d'abord (relayer opéré), contrat **BattlepoolV2 séparé**
  (on ne touche pas `Battlepool.sol`), **coexistence** TST + AVAX.e, **gas = TST natif**.
- **NOTE Fuji** : Pingala est une L1 créée **autour du Fuji testnet** → l'« AVAX » bridgé est de
  l'AVAX de testnet **Fuji** (pas du mainnet). Faucet = TST (gas) + **bridge AVAX.e réel (Fuji)** +
  **faucet AVAX.e test**.

---

## 3. Secrets / portefeuilles (jamais en clair dans git)

| Rôle | Adresse | Solde approx | Où est la clé |
|---|---|---|---|
| Owner/faucet/deployer | `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` | ~651 TST + 849k PRANA | `.secrets/faucet_wallet_pk` |
| Premine (test) | `0x3fA1bAF8083B4CaE1E5EF8126E06409337D221B0` | ~999k TST | transmise en clair (considérée exposée) |
| payoutOperator (bridge) | `0x9A60327c…` | ~0.4 TST + 100k PRANA | `.secrets/payout_operator_pk` |
| signer | `0xBe24F203…` | ~0.2 TST | `.secrets/signer_wallet_pk` |
| Bots ×120 | — | ~0.5 TST chacun | `/opt/battlepool/.secrets/bots_keys.json` |

- `/opt/battlepool` = clone git VPS, branche `master` épinglée au SHA déployé.
- **Déploiement** = LOCAL uniquement (`run_deploy.ps1 -GitSha <sha>` + `secrets-manager` provision/deprovision).
  Le CLOUD (root VPS) peut déployer par `docker build`/`compose` sur mandat explicite.

---

## 4. Commandes utiles pour reprendre

```bash
# Rituel AVANT de travailler
git fetch --all --prune
git switch <branche>   # ex. feat/app1-deeplink-app2 ou feat/avaxe-bridge
git pull --rebase

# Vérifier la prod
curl -s https://battlepool.31.187.72.98.sslip.io/api/health
docker ps --format '{{.Names}}\t{{.Image}}\t{{.Status}}' | grep battlepool

# Déployer (LOCAL)
node secrets-manager.mjs provision
powershell -ExecutionPolicy Bypass -File deploy\run_deploy.ps1 -GitSha <sha>
node secrets-manager.mjs deprovision
```

---

## 5. Points en suspens / prochaines étapes

1. **AVAX.e (chantier actif)** :
   - Compiler/déployer `AVAXe` + `BattlepoolV2` (Hardhat `battlepool/`).
   - P0 : bridge custodial (`AvaVault` C-Chain + relayer lock↔mint).
   - P2 : indexer ERC-20, payout AVAX.e (secret `avaxe_payout_operator_pk`), faucet AVAX.e.
   - P3 : frontend (approve + dépôt ERC-20, affichage AVAX.e, claim).
2. **Claim réseau** : prod sur `e90529d` (bug AVAX de retour, volontaire). Pour remettre le fix :
   redéployer `feat/app1-claim-pingala-fix` = `3653ef0`.
3. **PoC Core** : un tunnel Cloudflare tournait sur le VPS (`https://acre-rare-teens-node.trycloudflare.com`)
   — probablement mort ; à relancer si besoin (`npx cloudflared tunnel --url http://localhost:5173`).
4. **PHPUnit** : 117 tests, ~100 passent, 16 échecs pré-existants (Auth/Profile/Ipfs/SessionPayout/
   TransactionQueue…) — non bloquants, hors périmètre.
5. **`reverb`** retombe `unhealthy` périodiquement (runtime Docker) → `docker compose up -d --force-recreate reverb`.

---

## 6. Règles d'or (rappel)

- Ne pas toucher `Battlepool.sol`, `.env.staging`, `deploy/`, `security-entrypoint.sh`,
  `secrets-manager.mjs` sans accord (le chantier AVAX.e utilise un **contrat séparé** `BattlepoolV2`).
- **Réinitialisation DB = rembourser l'on-chain aussi** : `TRUNCATE` des tables ne vide PAS les
  soldes on-chain (V2 `userBalances`). Le `deposit()` fait `userBalances += amount` (il **ajoute**),
  donc après un truncate le nouveau dépôt s'ajoute au solde résiduel → balance ×2/×4 → objectif
  d'affichage (`session_start_balance × target_q`) faux. Avant/après un reset, rembourser les soldes
  V2 (`batchPayOut` via payoutOperator) et `FLUSHALL` Redis (sinon `batches.last_pool_id` obsolète → matchmaking bloqué).
- `chainId 99999` partout ; alias technique `snt`/`sntToken` immuable.
- Secrets jamais en clair dans git ; `scratch/`, `.secrets/`, `deploy/` jamais commités.
- Un commit = une chose ; vérifier le **bundle servi** après tout deploy front.

---

## 7. Dernières modifications (2026-10-25)

### Sécurité prod (`4caab5f`, inclus dans `feat/avaxe-bridge`)
- Suppression des endpoints dev/test **publics** : `public/test_env.php`, `test.php`,
  `test_integration.html`, `test_sanctum.html`, `debug-test.html`, `admin.html`.
- Retrait de la route `/api/debug-referral`.
- Page debug 🐞 **dev-only** (`?debug=1` ou localhost).
- `LOG_LEVEL=warning` (`.env.staging`).
- En-têtes Caddy : `Referrer-Policy`, `Permissions-Policy`, `CSP frame-ancestors …app2…`.

### Protocole LOCAL ↔ CLOUD
- Fichiers sur `docs/app1-collab-protocol` (`75b91ab` → `8c14341`) :
  `docs/COLLAB_STATE.md`, `docs/handoffs/2026-10-18-local*.md`, `AGENT_ONBOARDING_APP1.md`.
- Arbitrage : S3/S9 = tâches **App 2** (hors App 1) ; **déploiement = LOCAL uniquement**
  (le CLOUD root VPS n'a pas `run_deploy.ps1` / `secrets-manager` / clé deploy).
- **À merger sur `master` par PR** (LOCAL) pour que le protocole soit sur la branche d'intégration.

### Fix « bouton CLAIM invisible » (`5cd3eac`)
- **Cause** : bridge `/generate-signature` saturé (file `enqueueTx` FIFO, bots) → timeout
  Laravel → `payout_signature = NULL` → le front masquait la zone de claim.
- `SessionManager::ensureHumanPayoutSignature()` : rattrapage **humain** (statut `stopped`,
  solde > 0, session terminée, non-bot), throttle 10 s.
- `/api/user/status` (`PoolAutoMatchController::getPollingStatus`) : rattrapage automatique.
- Bridge `/generate-signature` : synchro on-chain bornée (`Promise.race` 8 s) → **la signature
  est toujours rendue** (la synchro continue en fond).
- Front (`resources/js/modules/game.js`) : état « **Génération de votre récompense…** » au lieu
  de masquer la zone.

### Prod (déploiement 2026-10-26)
- Avant : `d561e34`. **Déployé : `f5ec00b`** (branche `feat/avaxe-bridge`) — images taguées
  `:f5ec00b`, stack **healthy**, `GET /api/health` OK.
- **Incidents corrigés pendant ce deploy** (à connaître pour la suite) :
  1. `db-entrypoint.sh` était versionné **mode 644** → MySQL « permission denied » au démarrage.
     Corrigé en **755** dans git (`1d0c034`).
  2. **Dérive de migrations** : certains index existaient déjà en base alors que la migration
     n'était pas enregistrée → `init-db` exit 1 (`Duplicate key name`). Migrations rendues
     **idempotentes** : `idx_f_hists_user1/2`, `idx_users_matchmaking`, `idx_pools_matchmaking`,
     `idx_pools_status_created_at` (`802e13a`, `f5ec00b`).
  3. **Health gate** : le conteneur orphelin `battlepool-scheduler` (hors compose, **sans
     healthcheck**) était compté `unhealthy` → `BP-FATAL` alors que la stack était saine.
     Gate corrigé (`deploy/deploy_vps.sh` : ignore les conteneurs sans healthcheck).
- **Note** : `battlepool-scheduler` tourne une image **`c670807`** (orphelin, absent du
  `docker-compose.prod.yml`) ; le schedule (`battle:cleanup` everyMinute,
  `cards:sync-expired-limits`) fonctionne. **À intégrer proprement dans le compose prod** ensuite.

---

## 8. Incident 2026-10-27 — Claim impossible = `GAME_CURRENCY` perdu (V1/TST)

**Symptôme** : utilisateurs (mobile/desktop) ne peuvent plus réclamer leurs gains ; `/api/artefacts`
renvoie `game_currency:"tst"` (au lieu de `avaxe`) et le contrat V1 `0x0790…` ; log
`[Business] claim ÉCHEC: TypeError: Cannot read properties of undefined (reading 'then')`.

**Cause** : lors du deploy `6628503` (agent local), `GAME_CURRENCY` a disparu de
`/opt/battlepool/.env.staging`. `smart_contracts/config.js` retombe alors sur son défaut `"tst"`
→ `activeGame()` = V1 → front en TST/V1. Le `claimAndExit` V1 (contrat déprécié) échoue (le `.then`
venait du flux V1 ; le bundle V2 n'a pas de `.then`).

**Correctif appliqué (live)** :
- `GAME_CURRENCY=avaxe` remis dans `/opt/battlepool/.env.staging` ; bridge recréé **avec
  `GIT_SHA=6628503`** (⚠️ `--force-recreate` sans `GIT_SHA` prend `:latest`, image obsolète sans `gameV2`).
- `Cache::remember('game_config')` (5 min) purgé (`php artisan cache:forget game_config` + clés Redis `*game_config*`).
- Défaut durable : `config.js` `GAME_CURRENCY` défaut `tst`→`avaxe` (cloud **`8c67be7`** poussé +
  fichier monté `/opt/battlepool/smart_contracts/config.js`).
- Vérifié : `/api/artefacts` → `game_currency:"avaxe"`, `gameV2:0xb628…`, `avaxe:0x9d8A…`.
- Compte test `id 243` (`0x74F1…`) remis à zéro (balance 0, `payout_signature`/cooldown nul,
  `autoplay_active=0`, `status=available`) — son solde V1 0,4128 TST était orphelin (V1 abandonné).

**À faire** : re-tester le claim côté navigateur après **hard reload** (le SW est network-first sur
`/build/assets/`, donc pas de bundle périmé). Si `.then` persiste, inspecter le bundle servi.

**Leçon** : `.env.staging` n'est pas git-suivi → tout réglage critique (devise) doit avoir un défaut
de code sûr (`avaxe`) pour survivre aux redeploys.

---

## 9. Logique de rounds (transfert intra-pool) — 2026-10-27 (commit `1df7e3f`)

**Constat** : la martingale existante (`SessionManager::processFoughtUser`) ne s'applique qu'à la
**fin de pool**. Les règles voulues portent sur les **rounds** (c'est le round qui détermine l'issue).

**Règles implémentées** (`FightService::computeRoundTransfer`) :
- **R1** : le gagnant prend la mise de base → le perdant (qui n'a que sa base) tombe à 0 → éliminé.
- **R2/R3** : le gagnant prend le **bénéfice** du perdant → le perdant garde sa base (survit).
- **R4+** : le gagnant prend la mise de base **même si c'est tout ce qui reste** (perdant éliminé
  s'il n'a que sa base ; s'il lui restait plus, il conserve le surplus).

**Fichiers modifiés** :
- `app/Services/MatchmakingEngine.php` : `$round = $iterationIndex + 1`, transmis via
  `Fight::create(['round' => …])` et `handlePoolAutoplayFight(…, $round)`.
- `app/Services/FightService.php` : signature `int $round = 1`, `computeRoundTransfer()`, lecture du
  stack du perdant **avant** transfert, `transferred_amount` persisté, `FightResult` delta réel + round.
- `app/Models/Fight.php` : `round` + `transferred_amount` dans `$fillable`, signature.
- `app/Events/FightResult.php` : `round` ajouté au payload.
- `app/Models/FHist.php` + `app/Services/HistoricalFightService.php` : `round` archivé.

**Migrations** :
- `2026_10_27_000000_fix_monetary_precision.php` : `fights.base_bet_amount/max_bet_amount`,
  `users.initial_base_bet`, `user_settings.*`, `queue_table.base_bet`, `challenges.*`,
  `transactions.amount` → `decimal(18,8)`. Le contrat Solidity est déjà en 18 décimales
  (AVAXe ERC-20, montants `uint256` wei) : **aucune modif on-chain nécessaire**.
- `2026_10_27_000001_add_round_to_fights_and_hist.php` : `fights.round` + `fights.transferred_amount`
  + `f_hist.round`.

**Compat** : les tests existants (`FightServiceLogicTest`) restent valides (R1 par défaut =
`min(baseBet, loserStack)`, identique à l'ancien comportement pour les cas testés). Règle validée par
script autonome (R1/R2/R3/R4/R4-edge). PHPUnit absent de l'image prod → suite non exécutée ici.

**À faire** : lancer `php artisan migrate` au prochain deploy (sinon `fights.round` /
`transferred_amount` absents → erreur à l'enregistrement du combat).

---

## 10. Pools 13 joueurs / 5 rounds + comparaison A/B (2026-10-28)

**Contrat** (`BattlepoolV2.sol`, commit `4ec0440`, compile OK) :
- `MAX_POOL_SIZE = 16` (plafond dur) ; `defaultPoolMaxSize = 13`.
- `setDefaultPoolMaxSize` / `setPoolMaxSize` / `createPool` : `require(2 <= size <= 16)`.
- **Pas de redéploiement** (le contrat déployé acceptait déjà ≥ 2, donc 16). Appliqué **on-chain**
  via le setter : `setDefaultPoolMaxSize(13)` — owner `0xD629…763Ee`, clé `faucet_wallet_pk`
  (`0xD629…`, 445 TST gas), tx `0xe016a741…50d9`. `defaultPoolMaxSize()` = **13** confirmé.
- Le déploiement du bytecode avec le cap 16 reste à faire **uniquement si on redéploie** (non nécessaire).

**App / config** (commit `4ec0440`) :
- `config/pool.php` : `'size' => [13]`, nouveau `'rounds' => 5`.
- `MatchmakingEngine` : `$maxIterations = config('pool.rounds', 5)` (fixe, plus `pool_size`).
- Fallbacks `InternalPoolService` / `CleanupSystemCommand` : `13`.
- DB : nouvelles pools `pool_size=13` ; `batches.pool_size=13` ; rien d'autre à migrer.
- Images : `battlepool-app:round2` (nouvel algo 13/5, **live**) ; `battlepool-app:old135`
  (ancien algo + 13/5, pour le test). Branches : `feat/round-rules` (`4ec0440`) et
  `feat/old-algo-13-5` (`2eaad5f`).

**A/B 13 joueurs / 5 rounds — 74 bots, 30 min chacun, reset identique** :

| Indicateur | Ancien algo 13/5 | Nouvel algo 13/5 |
|---|---|---|
| Succès (cible +20 %) | **4 / 74 (5,4 %)** | **0 / 74** |
| Ruine | 0 | 0 |
| Balance moy. (départ 0.4) | **0.3797** (−5,1 %) | **0.3992** (−0,2 %) |
| Balance max | 0.4860 | 0.4848 |
| Mises 1×/2×/4×/8×/16× | 12 / 20 / 23 / 11 / 8 | 28 / 22 / 2 / 16 / 6 |

**Lecture** : le nouvel algo **préserve mieux le capital** (moy. quasi break-even, moins de bots
poussés à 4×) mais **atteint moins souvent la cible** : en R2/R23 le gagnant ne prend que le
bénéfice, donc il accumule plus lentement, tandis que l'ancien prend toute la base et élimine
les perdants (plus de destruction : moy. −5,1 %, min 0). Échantillon faible (0–4 succès) →
non concluant statistiquement. Un run plus long (ou cible 1.05) est nécessaire pour trancher.

**État live** : `round2` (nouvel algo 13/5) sur `app`, `queue-workers`, `scheduler` ; bridge
inchangé (`6628503`, `GAME_CURRENCY=avaxe`).

---

## 11. Test Q=2 + accélération « turbo » (2026-10-31 → 11-01)

**Test Q=2** : `target_q=2.0` (cible ≈ 0.82), 13 joueurs / 5 rounds, 737 bots. Résultat : premières
cibles après ~44 h ; puis accélération (~14 vraies cibles + quelques micro-gagnants, 3–5 ruines).

**Correctif `round4`** (`743ee3e`) : après un auto-stop `InternalPoolService` (fonds insuffisants pour
le palier), le bot est remis **`available`** si `balance >= base_bet` (au lieu de rester `stopped`).
Les 9 bots bloqués ont été réactivés.

**Mode TURBO** (le vrai accélérateur) :
- `InternalPoolService::processInternalPools($baseBet, ?float $forcedTier)` : `round5`/`f16a615` —
  traite **tous les paliers** de création par cycle (le contrôleur boucle sur les paliers).
- `BatchProcessingService::processAllBetTiers` : `round6`/`aafbfe7` — traite **tous les paliers** de
  pools par cycle (c'était le goulot : round-robin 1 palier/cycle).
- `config/pool.php` : `batch_max_size=50`, `batch_max_iterations=100` (`round7`/`c33e2ab`).
- `BATCH_PROCESSOR_INTERVAL=1000` (1 s).
- Mesures : débit passé de ~48 pools/min / 907 fights/min → **~76–89 pools/min / ~1500–1660 fights/min**
  (×1,8). ⚠️ Load moyenne monté à ~4 (2 cœurs) : proche saturation.

**Ops / espace** (crise disque résolue) :
- Cause : **binlogs MySQL** (~1 Go × 30 j = 32 Go). Correctif : `--skip-log-bin` dans le compose
  (recréation `db`), `log_bin=OFF` ; DB 32 Go → 2,1 Go ; disque 100 % → ~56 %.
- Purges : logs app **toutes les 15 min**, garde-fou disque **toutes les 2 min** (si ≥85 %).
- Réapprovisionnement : boucle `replenish_stopped.sh` — **+30 bots par 30 `stopped`** (vérif 10 min),
  via `add_bots.sh` (wallets `ethers` + users/premoves). Scripts dans `/opt/battlepool/`.
- Bilan horaire conservé (`bilan_q2.log`) ; `max_gain.svg/csv` (courbe du gain max).

**État live** : `battlepool-app:round7` sur `app`, `queue-workers`, `scheduler` ; bridge inchangé
(`6628503`, `GAME_CURRENCY=avaxe`) ; DB avec `--skip-log-bin`. Branche `feat/round-rules` (`c33e2ab`).

---

## 12. P2 — Engine mémoire + Redis Stream + events batch (2026-11-05)

**Branche** : `feat/session-recovery` (from `feat/round-rules`/`383ef41`, puis P1 `e3fc6eb`).

**Objectif** : supprimer les écritures DB par combat pour que le live atteigne le débit du
simulateur, tout en gardant l'historique des fights (audit/équitabilité).

**Décisions actées** :
- Humains et bots **dans les mêmes pools**.
- Events UI **regroupés** : flush toutes les **20 pools** (`GAME_ENGINE_BATCH_POOLS=20`), le front
  rejoue le lot séquentiellement (léger décalage acceptable).
- Audit via **Redis Stream** consommé par un **job Laravel** (pas de service Node).
- `Pool::match()` route vers le moteur **uniquement si `GAME_ENGINE_ENABLED=true`** (défaut false :
  prod inchangée tant que non validé).

**Nouveaux fichiers** :
- `app/Services/GameEngineService.php` : `processPoolInMemory()` — 13 joueurs, 5 rounds, PFC,
  appariement **déterministe salé** (identique au `MatchmakingEngine`), transferts par round en
  mémoire, **1 bulk `Fight::insert`**, **1 save/joueur**, save des `pre_moves`, puis
  `SessionManager::evaluatePoolEnd()`. `tick()` traite un lot de pools et flush les events.
- `app/Services/FightStreamWriter.php` : `XADD battlepool:fights` par combat (audit brut).
- `app/Services/GameEventBuffer.php` : accumule les events **par utilisateur (humains seulement)**.
- `app/Events/GameEventsBatch.php` : event `ShouldBroadcast` (canal privé user) avec le lot ordonné.
- `app/Jobs/ArchiveFightStream.php` : consumer group `battlepool-archive` (XREADGROUP → archive
  JSONL `storage/app/fights-archive/YYYYMMDD.jsonl` → XACK).
- `app/Console/Commands/RunGameEngine.php` : boucle `game:engine` (`--once`, `--sleep`), lock
  `game_engine_tick`.
- `config/game_engine.php` : `enabled`, `batch_pools`, `tick_seconds`, stream/consumer, archive.

**Gains attendus par pool** : avant ~120 écritures (Fight + 2 users + f_hist + queue + notifs) ;
après : 1 XADD/combat + 1 bulk insert fights + 13 saves + saves premoves + fin de pool.

**À valider sur VPS** :
1. `GAME_ENGINE_ENABLED=true` (ou lancer `php artisan game:engine` avec le batch processor stoppé).
2. Le front doit gérer l'event **`GameEventsBatch`** (rejouer `events[]` séquentiellement).
3. Émettre aussi `MatchFound`/`BATTLE_STARTED` pour les humains (non couvert par le prototype).
4. Lancer le consumer d'audit (job planifié ou `queue-worker`).

---

## 13. Déploiement engine sur VPS + simulation 300 bots (2026-11-05)

**Image déployée** : `battlepool-app:engine3` (commit `d03f75f`, branche `feat/session-recovery`)
sur `app`, `queue-worker`, `queue-worker-limits`, `scheduler` (`GIT_SHA=engine3 docker compose
-f docker-compose.prod.yml up -d --no-deps --force-recreate ...`). `bridge`/`reverb` inchangés.

**Activation** : `.env.staging` → `GAME_ENGINE_ENABLED=true`, `GAME_ENGINE_BATCH_POOLS=20`,
`GAME_ENGINE_TICK=1.5`. Vérifié : `config('game_engine.enabled')=true`, commande `game:engine`
présente. `Pool::match()` route désormais vers `GameEngineService` (engine mémoire).

**Bugs corrigés pendant le déploiement** :
- `nextMove()` passait un tableau par référence (`$cursor[$id] ?? 0`) → `Error` runtime.
  Fix : paramètre `array &$cursor` + `int $userId` (`c1903bb`).
- Consumer d'audit : phpredis renvoie la clé de stream **préfixée** (`laravel_database_...`),
  donc `$messages[$stream]` ne matchait jamais (aucun ack/archive). Fix : `reset($messages)`
  + `XTRIM ~100000` (`d03f75f`).

**Simulation lancée** :
- Reset cohorte : users `1..300` → `balance=0.4`, `bet_amount=initial_base_bet=0.0004`,
  `target_q=2.0`, `available`, session/claim/cooldown remis à zéro ; bots réappro `id>300`
  désactivés (`autoplay_active=0`). Pools supprimées.
- `run_batch_processor.js` démarré dans `bridge` avec `BATCH_PROCESSOR_INTERVAL=1500`.
- `replenish_10.sh` (host, `setsid`) : **+10 bots / 10 stopped** (check 10 min).
- `monitor_initial_300.sh` (host) : bilan horaire cohorte `id<=300`.
- Consumer d'audit : boucle `ArchiveFightStream` dans `app` (tinker, 1 s) ; Redis Stream
  `laravel_database_battlepool:fights` consommé, `pending=0`, archive JSONL
  `storage/app/fights-archive/YYYYMMDD.jsonl`.

**Mesures initiales (~15 min)** : ~35 pools/min, ~510 fights/min (cohérent avec 300 bots ;
le débit est borné par le nombre de pools/cohorte et l'intervalle 1,5 s, pas par l'engine).
0 stopped à ce stade (target 2.0 → premiers succès/ruines attendus plus tard). Load ~3 sur 2 cœurs.
Aucune erreur `GameEngine` dans les logs après correctifs.

**Limites connues / à faire** :
- Le consumer d'audit tourne via un `tinker` détaché (meurt au restart du conteneur) et écrit
  dans le storage éphémère du conteneur → à planifier en commande/`scheduler` pour la prod.
- Les events UI groupés (`GameEventsBatch`) ne concernent que les humains ; le front doit
  gérer cet event + `MatchFound`/`BATTLE_STARTED` (non émis par l'engine).
- La logique de **récupération de session** (X=(1+T+fee)/T, 1 seule recovery) reste côté
  simulateur ; pas encore implémentée dans le backend.

---

## 14. P3 — Bankroll + martingale de session (modèle A, 2026-11-05)

**Décision** : P3 en parallèle ; P2.1 (events humains + f_hists/IPFS + consumer schedulé) = dette
avant prod réelle.

**Modèle validé (A)** :
- Budget de session **fixe** `C = baseBet × security_coefficient`.
- Target de **session** `tq_s` = 1.05 / 1.2 → `t = tq_s − 1`. **Sessions calculées SANS frais.**
- `X = (1 + t) / t` (sans frais). Ex. 1.05 → 21.00 ; 1.2 → 6.00.
- **Dépôt** : le joueur paie `B + frais` ; les frais sont **prélevés au dépôt**, seul `B` est crédité.
  `frais = fee_rate × B`.
- **Target de bankroll** : `T = B × final_multiplier + frais` (les frais sont ajoutés à la target).
  Au succès, le joueur retire `T` ⇒ **net = (final_multiplier − 1) × B** (×2 → net = B, doublé).
- **Objectif final** : atteindre `T`.

**Déroulé** :
- Session gagnante : `+t·C` au bankroll, `C` inchangé, nouvelle session.
- Session ruinée : `−C`, puis **une** récupération `C·X` puisée dans `B` (gagnée → `+t·C·X` ;
  perdue → **perte finale**). **Une seule récupération par ruine** (jamais `X²`).

**Socle** : `config/bankroll.php`, `app/Services/BankrollService.php` (`recoveryFactor`,
`feeAmount`, `depositAmount`, `finalTarget`, `payoutOnSuccess`, `playerNet`,
`recommendedBankroll`, `isSuccess`, `canFundRecovery`, `isRuin`, `breakdown`), migrations
`2026_11_05_000000_*` + `2026_11_05_000001_*`, `User::$fillable`.

**Chiffres (C = 0.4)** :
| tq_s | X | Récup C·X | B = C+C·X | frais (0.5 % B) | Dépôt B+frais | Target T=2B+frais | Net succès |
|---|---|---|---|---|---|---|---|
| 1.05 | 21.00 | 8.400 | 8.800 | 0.0440 | 8.844 | 17.644 | **8.800** |
| 1.20 | 6.00 | 2.400 | 2.800 | 0.0140 | 2.814 | 5.614 | **2.800** |

Vérifié : `payout − dépôt = B` (doublement exact).

**Prochaine étape** : state machine `SessionManager` (session→bankroll, rattrapage par ruine,
succès à `B ≥ T`), puis dépôt unique V2 (`B + frais`) + endpoint preview + UI.

---

## 15. Fonds de recouvrement + file FIFO — module codé & testé (2026-11-05)

**Modèle retenu** : (b) frais par session (`F_C` tous, `F_R` seulement si rattrapage) ;
gagnants nets `+C·t`. File FIFO des joueurs à rembourser, fonds alimenté par les frais,
paiement par lots quand le fonds atteint 10 % de la dette totale (batch 5 %), jamais de
solde négatif.

**Fichiers** :
- `database/migrations/2026_11_05_000002_create_refund_queue_tables.php` :
  `refund_queue` (FIFO waiting/paid, montant, snapshot), `refund_ledger` (crédits/débits),
  `refund_fund_state` (solde).
- `app/Services/RefundQueueService.php` : `planPayouts()` (pur, testable), `credit()`,
  `enqueue()`, `processBatch()` (transaction, FIFO strict).
- `app/Console/Commands/ProcessRefundQueue.php` : `refund:process {--once} {--sleep=30}`.
- `config/refund.php` : `trigger_pct=0.10`, `batch_pct=0.05`, `safety_margin=0.90`.
- `tests/refund_queue_simulation.php` : tests unitaires + simulation de cohortes.

**Tests (23/23 OK)** :
- unitaires : file vide, sous-seuil, batch=ceil(5 %), FIFO, pas de saut de tête, solde jamais négatif ;
- cohortes (1000/j, 365 j) :
  - T=1.2 / φ=20 % / remb 100 % (couverture 100.3 %) → file **bornée** (≈1767), fonds +6151 ;
  - T=1.2 / φ=2.5 % / remb 100 % (couverture 25.8 %) → file **grossit** (5047 → 10414) ;
  - T=1.2 / φ=2.5 % / remb 26 % → file **stable** (≈646), fonds +408 ;
  - T=1.05 / φ=2.5 % / remb 58 % → file **stable** (≈83), fonds +537.

**Règle validée** : `remboursement % ≤ couverture` (frais / (q·B)) ⇒ file stable ; au-delà
⇒ file qui grossit. La file est une **liquidité**, pas une source de financement.

**Multi-remboursements & rejeu (ajouté)** :
- Un joueur peut avoir **plusieurs remboursements distincts** (une ligne `refund_queue` par échec,
  ordre FIFO global indépendant). `pendingForUser()` / `pendingTotalForUser()` pour l'UI.
- La file est une **dette indépendante** : le joueur **peut rejouer** pendant l'attente
  (re-dépôt / nouveau bankroll) sans être bloqué.
- Au paiement, `credit_balance_on_payout=true` → le remboursement est **crédité au solde**
  du joueur (`users.balance`) et `max_balance` mis à jour.
- Tests : **27/27 OK** (multi-entrées même joueur, FIFO global, paiement unique par entrée).

**Reste à faire** : brancher `credit()` au prélèvement des frais et `enqueue()` à la détection
des 2 échecs (`SessionManager`) en laissant l'utilisateur `available`, planifier
`refund:process`, puis déployer (migration).

---

## 16. Fonds de recouvrement — branchement + déploiement + test E2E VPS (2026-11-08)

**Branchement (derrière `REFUND_ENABLED`, défaut false)** :
- `PoolReconstructionService` : chaque frais d'entrée (`InfluencerFee`) crédite le fonds (`credit`).
- `SessionManager` (vraie ruine) : `enqueue` d'une dette distincte (montant = `refund_pct × B`),
  sans bloquer le joueur (il peut rejouer ; remboursement crédité plus tard).
- `bootstrap/app.php` : `refund:process --once` planifié chaque minute **si activé**.
- `config/refund.php` : `enabled`, `refund_pct` (0.25), `trigger_pct` (0.10), `batch_pct` (0.05).

**Déploiement** : image `battlepool-app:engine9` sur app/queue/scheduler. Migrations P3 appliquées :
`2026_11_05_000000` (bankroll), `000001` (bankroll_target), `000002` (refund_queue/ledger/fund_state).
Tables créées ; commande `refund:process` présente.

**Test E2E réel (transactionnel, rollback)** :
`credit(1.0)` + `enqueue(uid, 0.01)` + `processBatch()` →
`status=paid`, solde utilisateur `0.824 → 0.834` (+0.01 crédité), fonds `1.00 → 0.99`.
Après rollback : `refund_queue=0`, `refund_ledger=0`, `fund=0` → **aucun résidu**.

**État live (engine9)** : flag refund **off** → `refund_queue`/`ledger` restent vides.
Simulation 300 bots (~3 jours) : cohorte **290 stopped** / 9 in_pool / 1 available ;
réappro **620 bots** ajoutés (id>300). App healthy.

**Pour activer** : finir la state machine P3 (C/R/T/X, 1 récupération) et calibrer
`refund_pct ≤ couverture` avant de passer `REFUND_ENABLED=true`.

---

## 17. State machine P3 — implémentée, testée, branchée (2026-11-08)

**Service** : `app/Services/BankrollSessionService.php`
- `computeParams()` (pur) : C, T, t, φ, φ_used, `X=(1+t+φ_used)/t`, `R=C·X`, `B=C+R`,
  `F_C`, `F_R`, `refund_amount`, `threshold_1=C·T·(1+φ)`, `threshold_2=R·T·(1+φ)`.
- `canActivate()` : activation **gardée** — le joueur doit avoir déposé `B = C+R`.
- `nextAction()` (pur) : `win` / `recovery` / `double_failure` / `continue`.
- `initialize()` : découpe le dépôt (`C` en solde de jeu, `R` en réserve) + champs bankroll.
- `startRecovery()` : libère `R` comme solde de jeu, `session_attempt=1`.
- `enqueueRefund()` : dette distincte en file FIFO (si `REFUND_ENABLED`).

**Branchement (derrière `BANKROLL_ENABLED`, défaut false)** :
- `PoolReconstructionService` : à la 1ʳᵉ session, si `canActivate(balance, params)` → `initialize`
  (sinon modèle classique inchangé).
- `SessionManager` : succès → restitue la réserve non utilisée au solde puis payout ;
  vraie ruine → `startRecovery` (1 rattrapage) ; rattrapage perdu → `enqueueRefund` + stop.
- Détection de succès réutilise `target_q = T` et `fee_percent = φ×100` ⇒ `multiplier = T(1+φ)`.

**Tests** : `tests/bankroll_state_machine_test.php` → **24/24 OK** (formules, activation gardée,
transitions, Monte Carlo : q≈(1−P)², gain gagnant +C·t, remboursement = refund%·B).

**Déploiement** : image `battlepool-app:engine10` ; flags `BANKROLL_ENABLED=false` et
`REFUND_ENABLED=false` → comportement live **inchangé** (les bots n'ont pas déposé B, donc
`canActivate=false`).

**Pour activer en live** : (1) dépôt `B = C+R` côté front/contrat, (2)
`BANKROLL_ENABLED=true`, (3) `REFUND_ENABLED=true` + `REFUND_PCT ≤ couverture`.

---

## 18. Dépôt B=C+R + test contrôlé state machine (2026-11-08)

**Constat** : le contrat V2 expose déjà `deposit(uint256 amount)` → **aucun changement de contrat**.
Il suffit de déposer `B=C+R` en une fois ; le backend découpe (`initialize`).

**Front** (`resources/js/modules/game.js`, gardé par `bankroll_enabled`) :
- `updateFeeDisplay` : `C=bet×coeff`, `R=C·X`, dépôt affiché = `B=C+R` + frais.
- `startSession` : `stakeWei = C + C·X` (BigInt, X mis à l'échelle 1e6), `fee` sur B, `deposit(B+fee)`.
**API** `/api/artefacts` expose : `bankroll_enabled`, `bankroll_session_target_q`,
`bankroll_fee_rate`, `bankroll_recovery_factor`, `bankroll_refund_pct`.

**Test contrôlé VPS (transactionnel, rollback)** — user id 1, dépôt simulé `B` :
```
params: C=0.4 R=8.442 B=8.842 X=21.105 (T=1.05, φ=0.5%)
canActivate(dépôt=B) = OUI
INIT: balance=0.4  reserve=8.442  B=8.842  start=0.4  attempt=0   ✅ découpe C/R
RUINE S1 -> balance=8.442 (R), attempt=1, start=8.442               ✅ rattrapage
DOUBLE ÉCHEC -> file: pending=1, amount=2.2105 (=25%×B)            ✅ dette distincte
rollback -> refund_queue=0, refund_ledger=0                        ✅ aucun résidu
```

**Déploiement** : image `battlepool-app:engine11`. Flags `bankroll_enabled=false`,
`refund_enabled=false` → live inchangé.

**Activation** : `BANKROLL_ENABLED=true`, `BANKROLL_SESSION_TARGET_Q` (1.05/1.2),
`REFUND_ENABLED=true`, `REFUND_PCT ≤ couverture`. Le front déposera alors `B=C+R`.

---

## 19. Activation P3 en live (2026-11-08) — T=1.2 / frais 10 % / remboursement 70 %

**L1 rétablie** (cotisation P-Chain payée par un autre agent) → RPC 200, chain 99999.

**Contrainte découverte** : `setFeeBasisPoints(2050)` **revert** (`Fee cannot exceed 10%`).
Plafond contrat = **10 %**. Options calculées : T=1.02→100 %, T=1.05→99 %, **T=1.2→70 %** (choisi).

**Activation effectuée** :
- Contrat V2 : `feeBasisPoints` **250 → 1000** (tx `0x0af904ef…`, bloc 6862).
- `GameSetting smart_contract_fee_percentage = 10`.
- `.env.staging` : `BANKROLL_ENABLED=true`, `BANKROLL_SESSION_TARGET_Q=1.2`,
  `BANKROLL_FEE_RATE=0.10`, `REFUND_ENABLED=true`, `REFUND_PCT=0.70`.
- Image `battlepool-app:engine12` (fix X **avec frais** exposé au front : `bankroll_recovery_factor=6.6`).
- **Sim 300 bots arrêtée** (batch processor + boucles host).

**Test contrôlé (transactionnel, rollback)** avec la config active :
```
C=0.4  R=2.64  B=3.04  X=6.60  seuil1=0.528  seuil2=3.485
canActivate = OUI
INIT: balance=0.4 (C), reserve=2.64 (R), B=3.04, attempt=0
RUINE S1 -> balance=2.64 (R), attempt=1
DOUBLE ÉCHEC -> file: pending=1, amount=2.128 (=70 %×B)
rollback -> refund_queue=0, ledger=0, fund=0   ✅
```

**⚠️ Couverture** : à T=1.2/10 %, couverture ≈ **69.9 %** ; un remboursement à **70 %** est
**très légèrement déficitaire** (~−0.0003/joueur). Recommandation : passer à **68 %** pour
une marge de sûreté, ou accepter 70 % (quasi break-even).

**Reste** : test avec un **vrai compte déposant B** (on-chain), puis redémarrer l'engine pour
un run P3. La sim actuelle est arrêtée (aucune pool ne tourne).

---

## 20. P3 opérationnel — crédit frais bots + cohorte 130 (2026-11-10)

- **Activation live** : contrat `feeBasisPoints=1000` (10 %), `GameSetting=10`,
  `BANKROLL_ENABLED=true`, `T=1.2`, `FEE=0.10`, `REFUND_ENABLED=true`, `REFUND_PCT=0.70` (engine15).
- **Hook P3 flux interne** ajouté (`InternalPoolService`) : `initialize` quand `balance ≥ B`.
- **Fonds crédité par le flux interne** : `F_C = φ_used·C` à l'init, `F_R = φ_used·R` au rattrapage.
- **Fallback legacy** (ruines hors-P3) désactivé.
- **Cohorte P3** : 130 bots, `balance=B=3.04` ; 143 comptes P3 initialisés, `C=0.4`, `X=6.6`,
  `target_q=1.2`. Fonds crédité **6.24** (F_C), pools en formation sur plusieurs paliers.
- **À surveiller** : rattrapages/refund_queue au fil des sessions ; le `bet_amount` (martingale)
  scinde les paliers → garder assez de bots/tier (130 OK). Payouts bots : solde off-chain `B`
  non adossé on-chain → préférer une simulation sans payout pour un run long.

---

## 21. Perf engine — worker persistant + sim no-payout + audit (2026-11-10)

**Implémenté (validé 1/2/4/5)** :
- **Worker persistant** : `php artisan game:engine` tourne en boucle (dans le conteneur app,
  `DB_PASSWORD`/`APP_KEY` passés). `Pool::match()` = **no-op** quand `GAME_ENGINE_WORKER=true`
  (pas de double traitement). Le worker consomme `from_server_waitting` **et** `batched`
  (`GameEngineService::tick`).
- **Mode simulation bots** : `GAME_ENGINE_SIM_NO_PAYOUT=true` → `SessionManager` saute
  payouts on-chain, signatures, cooldown, `syncLimits`, notifs, `SessionFinished` pour les bots.
  La state machine P3 (rattrapage/refund) reste active.
- **Audit** : le worker lit **4 batches de 5000/tick** + `XTRIM` → Stream rattrapé
  (**932 000 → ~2 900** entrées), borné.

**Mesures** :
- Charge VPS : **5,2 → 2,17** (moins de travail on-chain/notifs).
- Worker : **6-7 pools/tick** (~1,1-2,4 s/tick), capacité 20/tick.
- Débit : **~1 136 fights/min** avec 130 bots (**limité par la taille du cohorte/paliers**, pas
  par l'engine).
- Stream : ~2 900 (bounded), aucune erreur payout/on-chain.

**À noter** : le worker est un process `docker exec` (meurt si le conteneur app redémarre).
Pour la prod : en faire un **service compose dédié** (ou supervisor).

**P3 cohorte** : 127 init, 67 available / 47 in_pool / 16 stopped ; 0 rattrapage encore ;
fonds **6.24**, file vide.

---

## 22. Engine en compose + cohorte 500 + réappro P3 (2026-11-10)

- **Service compose `engine`** ajouté (`docker-compose.prod.yml`, non git) :
  `battlepool-engine` (image app, `php artisan game:engine`, restart unless-stopped, healthcheck).
- **`batch-processor`** relancé en **service compose** (`battlepool-batch-processor`, image bridge
  6628503) ; les workers `docker exec` manuels supprimés (pas de doublon).
- **Cohorte P3 = 500 bots** (`balance=B=3.05`, `target_q=1.2`), 493 comptes P3 initialisés.
- **`replenish_p3.sh`** : +10 bots / 10 stopped, avec dépôt `B` (nouveaux ids > max → P3).

**Mesures (worker persistant, 500 bots, sim no-payout)** :
- **Débit : ~5 080 fights/min** (vs ~1 100 à 130 bots, ~1 500-1 700 ancien turbo) → **~3×**.
- Worker **à capacité 20 pools/tick** (tick 5–9 s) → **le pool-end devient le goulot**.
- Load ~4,8 / 2 cœurs ; Stream audit borné (~20 k).
- P3 : 493 init, 0 rattrapage encore ; **fonds 29.57**, file vide.
- Aucune erreur.

**Pour aller plus loin** : optimiser le pool-end (batch des writes `SessionManager`), ou
augmenter `GAME_ENGINE_BATCH_POOLS` (attention charge).

---

## 23. Perf pool-end + batch 40 (engine18, 2026-11-10)

- **Fast-path bots sim** dans `SessionManager::processFoughtUser` : pour les bots en mode
  simulation, on saute cartes (`UserCard`), `GameSetting` DB, logs `UserTracker`, broadcasts
  `MartingaleUpdated`, payouts/on-chain. Conserve martingale simple + state machine P3.
- `evaluateUserSession` : sim → fee depuis `config('bankroll.fee_rate')` (zéro requête GameSetting)
  et pas de cartes de plafond.
- `GAME_ENGINE_BATCH_POOLS=40`.

**Mesures (engine18, 500 bots, sim)** :
- Worker : **~35 pools/tick** (capacité 40) en ~5 s/tick (contre 20/tick avant).
- Débit mesuré : **~4 000 fights/min** ; load ~4,4 / 2 cœurs.
- Stream audit borné (~100k, XTRIM) ; aucune erreur.
- P3 : 493 init, 0 rattrapage ; **fonds 29.57**, file vide.

**Constat** : le pool-end n'est plus le goulot principal ; c'est désormais le **coût DB par pool**
(bulk fights + 13 saves + requêtes evaluatePoolEnd) et la charge CPU. Pour aller nettement plus
vite : **supprimer la persistance DB par pool en mode simulation** (garder tout en mémoire et ne
persister que les fins de session), ou augmenter le CPU.

---

## 24. Full in-memory + crédit frais + FIFO (engine20, 2026-11-10)

- **`InMemorySimulationService`** : cohorte P3 en mémoire, pools jouées sans écriture DB,
  audit Redis Stream, persistance uniquement aux fins de session + snapshot.
- **Crédit frais** : `F_C = φ_used·C` à chaque nouvelle session, `F_R = φ_used·R` à chaque
  rattrapage — **agrégés par cycle** (1 écriture/cycle) pour rester rapide.
- Commandes : `sim:memory {--once --pools --limit --sleep}`.
- Worker DB (`battlepool-engine`) et batch **stoppés** pendant le run mémoire.

**Mesures (500 bots)** :
- **~19 800 fights/min** soutenu (pics 1 200–1 900 fights/s), load **2,2**.
- vs ~4 000/min (engine DB) et ~1 500–1 700 (turbo) → **~6× / ~12×**.
- Fonds crédité (F_C 145.92 + F_R 505.61 = **651.53**).
- FIFO : `refund:process --once` → **48 payés (102.14)**, puis **99 payés (210.67)** au total.
- File : ~905 en attente (1 925) car **remboursement 70 % > couverture 69.9 %** → croissance lente.
  Recommandation : `REFUND_PCT=0.68` pour stabiliser.

**Reste** : le run mémoire tourne via `docker exec` (pas un service compose). Pour la prod,
l'ajouter au compose (comme `engine`).

---

## 25. Innovation — Outbox des effets de bord humains (2026-11-10)

**Problème** : les humains ont des effets lents (payout/signature on-chain, notifications,
cooldown, syncLimits, broadcasts). Exécutés dans la boucle, ils ralentissent le jeu.

**Solution** : la boucle calcule le résultat à la vitesse des bots (humains inclus) et
**écrit 1 ligne** dans `side_effect_outbox` ; un consumer asynchrone exécute le vrai effet.

- Migration `2026_11_05_000003_create_side_effect_outbox` (pending/processing/done/failed,
  attempts, available_at).
- `App\Services\SideEffectOutbox` : `push()`, `processBatch()` (retries/backoff 30→300 s).
- Handlers : `payout`, `session_finished`, `fight_result`, `notification`, `sync_limits`, `cooldown`.
- Commande `outbox:process {--once --limit --sleep}` + schedule **chaque minute**
  (si `GAME_ENGINE_DEFER_SIDE_EFFECTS=true`).
- Wiring `SessionManager` (gated `defer_side_effects`) : payouts, cooldown, syncLimits,
  `SessionFinished` délégués → la boucle ne les attend plus.
- **Test E2E** (transactionnel) : push notification → `processBatch` → `status=done`,
  `done=1`, rollback sans résidu.

**Doc** : `ENGINE_ARCHITECTURE.md` (schéma, moteurs DB/mémoire, outbox, P3, audit, vitesses).

**Effet** : bots et humains partagent la même boucle rapide ; les payouts/notifs humains
arrivent avec un léger différé (asynchrone), sans compromettre la vitesse.

**Vitesse (option a, sans dette fonds en DB)** : pics **~5 000–11 000 fights/s par cycle**
(~300k–670k fights/min), load ~2,5.

---

## 26. Mesures — temps de session & scalabilité (2026-11-10)

Instrumentation `InMemorySimulationService` : `avgPoolsWin`, `avgWinTime`, `avgPoolsFail`.
Balayage de charge (VPS 2 cœurs, full in-memory, `--pools=400`) :

| Bots | Débit (fights/s) | Cycle | Load (1 min) | avgPoolsWin | avgPoolsFail |
|---|---|---|---|---|---|
| 500 | 3 800–5 800 | ~1–3 s | 2,5 | ~2,0 | ~4,0 |
| 1000 | 5 300–10 400 (pics) | ~1–2 s | 3,5 | ~2,3 | ~4,3 |
| 1400 | 1 300–3 400 (**chute**) | 3–8,7 s | **4,3** | ~2,0 | ~3,8 |

**Conclusions** :
- **Session gagnante ≈ 2,2 pools** (échec double ≈ 4,2 pools). En temps réel, à une cadence
  de 1 pool / 1,5–3 s par joueur : **session ≈ 3–7 s**.
- **Saturation ≈ 1000–1200 bots** sur 2 cœurs (à 1400 : throughput chute, cycle explose, load 4,3).
- Pour 2000–4000 bots : **plus de CPU** (~linéaire : 2000→~4 cœurs, 4000→~8 cœurs) ou
  optimiser (`persistDirty` en bulk upsert, réduire `XADD`, etc.).

---

## 27. CORRECTION mesure session (2026-11-10) — engine24

**Bug** : `InMemorySimulationService` utilisait le **solde total** comme mise de pool au lieu
du `battle_balance` du pool (`baseBet`). Les transferts étaient ~1000× trop gros → sessions
gagnées en ~2 pools (faux). **Corrigé** : mise de pool = `baseBet`, consolidation en fin de pool.

**Vraie mesure** : target effectif = `T×(1+fee)` = **1.32** ; gain à viser = `+0.128` = **320×baseBet**.
Marche aléatoire → ordre de grandeur `320² ≈ 102 000` combats nets ≈ **~7 900 pools / session**.
Le sim ne montre **aucune session terminée** (ni win ni fail) → confirmé : sessions **très longues**
(heures par bot), ce qui colle à l'observation live historique (~44 h).

**Cause** : `security_coefficient = 1000` → la mise (`0.0004`) est **1/1000** du capital de session
(`0.4`). Atteindre +32 % demande un drift énorme.

**Leviers pour des sessions raisonnables** :
- Baisser `security_coefficient` (mise plus grande vs C), ex. ×50–×100 → sessions ~100× plus courtes.
- Baisser la cible de session (1.02 → +2.2 % → ~480 pools).
- Ou définir une session = nombre fixe de pools (plutôt qu'un % de capital).
- NB : le sim ne modélise pas la martingale de pool (le moteur réel double la mise après une
  perte de pool, jusqu'à ×16) → sessions un peu plus courtes en réel, mais toujours longues.

---

## 28. Mesure du temps de session (T=1.05) — 2026-11-10

Test rapide à `security=100` (temps ∝ security²) puis extrapolation à `security=1000`.

**Mesuré (security=100, T=1.05, fee 10% ⇒ cible effective 1.155)** :
- Session GAGNÉE : **~90 pools**, **~50 s** (sim, 1000 bots), P(win)≈86.6%.
- Échec (double) : rare dans la fenêtre (0), même ordre de temps.

**Extrapolé (security=1000, config réelle)** : `U = (1.155−1)×1000 = 155` → pools ∝ U² ×100
⇒ **~9 000 pools**, **~80 min** par session gagnée (par bot, à ~1,9 pool/s/bot).
L'échec est plus rare (~13 %) mais prend un temps comparable.

**Note** : le frais 10 % gonfle la cible effective (1.05→1.155), ce qui **baisse P(win) à ~86.6 %**
(au lieu de ~95 % sans frais) et **allonge** les sessions. `security_coefficient` est le levier clé
(temps ∝ security²).

---

## 29. Distribution du temps de session (T=1.05) — 2026-11-10

Test rapide `security=100` (13 min, 1 227 sessions gagnées loggées en CSV), puis scaling ×100
sur les pools (temps/pools ∝ U², U ∝ security) vers `security=1000`.

**Mesuré (security=100)** — pools par session gagnée :
moy=257 · p10=42 · **p50=151** · p90=653 · max=1489.
Aucun double-échec capturé (rare, <2 %).

**Extrapolé (security=1000, config réelle), à ~2 pools/s/bot** :
| Percentile | Pools | Temps |
|---|---|---|
| p10 | 4 200 | **~35 min** |
| **p50 (médiane)** | 15 100 | **~2 h** |
| p90 | 65 300 | **~9 h** |

→ Distribution **très étalée** (queue lourde) : sessions **beaucoup trop longues** pour de vrais joueurs.

**Insight clé** : `P(win) = 1/cible_effective` est **indépendant de `security_coefficient`** ; ce
coefficient ne fait que **scaler la durée** (∝ security²). Donc **baisser security rend les sessions
plus courtes sans changer P(win)**. Ex. security=100 → médiane ~1,4 min (mesuré) au lieu de ~2 h.

---

## 30. Test RÉEL security=300 (T=1.05) — temps de session (2026-11-10)

Mesure réelle en mémoire (aucune extrapolation pour les wins), ~45 min, `security=300`.

**WIN (730 sessions réelles)** :
- **Moyenne : 11,5 min** · médiane **8,1 min** · p90 **27,1 min**.
- (la moyenne est montée de 8,3 → 11,5 min à mesure que les sessions longues se terminent : censure).

**LOSS (double-échec) : 0 observée.**
Raison : la session de rattrapage utilise `R = C·X`, avec `X=(1+t+φ_used)/t = 23,1` à T=1.05
⇒ `X² ≈ 534`. Le temps de résolution ∝ capital² ⇒ le rattrapage dure **~534× la session 1**,
soit **~100 heures** (dérivé du temps réel mesuré). Donc les double-échecs sont **inobservables**
en temps réel.

**Implication majeure** : la « 1 session de rattrapage » n'est **pas viable** à T=1.05
(X=23). À T=1.2, X=6.6 ⇒ rattrapage ~44× la session (encore plusieurs heures). Le fonds de
remboursement (pour les ~13,4 % d'échecs) est le mécanisme pratique ; le rattrapage doit être
redimensionné ou abandonné.

---

## 31. État consolidé & HANDOVER (2026-11-10)

**→ Voir `MEMORY_BACKUP.md`** (document de reprise complet, à lire en priorité).

Résumé : branche `feat/session-recovery` @ `946eee8`, tout en **engine28** (app/queue/scheduler/
sim-memory). Moteur DB + batch **arrêtés** ; **sim-memory actif** (1000 bots, T=1.05, security=300).
Contrat V2 frais 10 % ; `REFUND_PCT=0.68` ; `DEFER_SIDE_EFFECTS=true` ; `SIM_NO_PAYOUT=true`.
Réel : session gagnée ~8,7 min ; pertes rares/lentes (~90 min, non observées). **Prochaine étape : UI.**

---

## 32. UI — cartographie + étape 1 (Setup P3) 2026-11-10

**SPA** : `public/index.html` (route `/` = `file_get_contents`) + bundle `/build/assets/app.js`
(`resources/js/modules/game.js`). Écrans : landing, setup, dashboard (balance/status/claim/feed), combat.

**Étape 1 faite (engine29)** : dans le setup, affichage `C` / `R=C·X` / `B=C+R` + règle de
remboursement + `TOTAL DEPOSIT (une fois)`. `game.js` lit `bankroll_*` via `/api/artefacts`.

**Écart restant** : dashboard sans panneau P3 (session/rattrapage/remboursement), pas d'endpoint
`/api/refund/pending`, claim mono-session. → étapes 2-4 (voir MEMORY_BACKUP §11).

---

## 33. Headless E2E — 22/22 OK (2026-11-10)

Script `tests/headless_e2e.sh` (sur le VPS) — vérifie sans navigateur :
1. `/api/health` OK.
2. `/api/artefacts` : `bankroll_enabled=true`, T=1.05, fee=0.1, refund=0.68, contract_fee=10, X≈23.1.
3. Front : `index.html` contient `p3-breakdown` + `Budget session (C)` ; le bundle `app.js` contient
   la logique P3 et lit `bankroll_recovery_factor`.
4. Tables DB : `side_effect_outbox`, `refund_queue`, `refund_ledger`, `refund_fund_state`.
5. Commandes : `outbox:process`, `refund:process`, `sim:memory`, `game:engine`.
6. Outbox E2E (transactionnel) : push → processBatch → `status=done`, rollback propre.
7. Tests unitaires dans l'image : `bankroll_state_machine_test` + `refund_queue_simulation` → OK.

**Résultat : 22 OK / 0 échec.** (Pas de navigateur headless type Playwright installé ; couverture
via HTTP + DOM servi + bundle + DB + commandes + outbox + tests unitaires.)

---

## 34. Headless navigateur (Playwright/Chromium) — 16/16 OK (2026-11-10)

**Fix d'alignement front/back** : le front utilisait `security_coefficient` (GameSetting=1000) alors
que le P3 backend utilise `BANKROLL_SECURITY_COEFFICIENT=300` → C faux. Corrigé : `/api/artefacts`
expose **`bankroll_security_coefficient`**, et `game.js` l'utilise pour C (setup + startSession).

**Test navigateur** (`tests/browser/headless_ui.cjs`, image `mcr.microsoft.com/playwright`) :
- charge la SPA (HTTP 200), titre présent, sélecteurs `#p3-breakdown/#base-bet-input/#target-q-input/
  #start-session-btn/#claim-section` présents ;
- API `/api/artefacts` : `bankroll_enabled=true`, T=1.05, X≈23.1 ;
- après recalcul (`change`), valeurs **rendues** : **C=0.1200**, **R=2.7720**, **B=2.8920** AVAXe,
  **remboursement 68 %** — ✅
- screenshot (`tests/browser/screenshot.png`), aucune erreur JS sévère.
- Astuce : `localStorage.user_locale='en'` avant chargement pour éviter le modal de langue (sinon
  `initGame` n'est pas appelé).

**Résultat : 16/16.** + suite headless HTTP/DB/outbox **22/22** (commit précédent).

---

## 35. UI — étapes 2+3 (Dashboard P3 + remboursements) 2026-11-10

- **Backend** `/user/status` (PoolAutoMatchController::getPollingStatus) expose désormais `p3` :
  `bankroll_total`, `bankroll_remaining`, `recovery_reserve`, `session_capital`, `session_attempt`,
  `recovery_factor`, `recovery_used`, `refund_pending_count`, `refund_pending_total`, `refund_items`.
- **Front** : panneau `#p3-panel` dans le dashboard (B, C, R, état Session 1 / Rattrapage /
  Session terminée / remboursement en attente, liste des dettes FIFO + note « vous pouvez rejouer »).
  `fetchUserStatus` stocke `userState.p3` ; `updateUI` remplit le panneau.
- **Tests** : headless 22/22 ; navigateur Playwright 17/17 (dont `#p3-panel` + C/R/B rendus).

**Reste étape 4** : claim — distinguer payout de session vs remboursement crédité.

---

## 36. UI — étape 4 (Claim : payout vs remboursement) 2026-11-10 — UI 1→4 terminée

- **Backend** `/user/status` p3 : ajout `refund_paid_count`, `refund_paid_total` (lignes `refund_queue`
  status=paid du joueur).
- **Front** :
  - panneau P3 : ligne « Remboursements reçus (count + total) » (`#p3-refund-paid`) ;
  - section Claim : ligne « dont remboursement crédité : X » (`#claim-refund-line` / `#claim-refund-amount`),
    affichée si `refund_paid_total > 0` → distingue **gains de session** vs **remboursement**.
- **Tests** : headless **22/22** ; navigateur **19/19** (nouveaux sélecteurs + C/R/B rendus).

**UI P3 (1→4) terminée** : Setup (C/R/B + frais + remboursement), Dashboard (état session/rattrapage +
fonds/file), Claim (payout vs remboursement).

---

## 37. P2.1 (slice) — events humains + f_hists via outbox (2026-11-10)

- `GameEngineService` : pour les combats **humains**, émet `fight_result` via **outbox async**
  (`emitHumanFight`) au lieu du buffer mémoire (durable, non bloquant). Repli buffer si outbox off.
- Une pool contenant des humains pousse un event **`archive_pool`** → `SideEffectOutbox` exécute
  `HistoricalFightService::archivePoolFights($poolId)` en asynchrone (**f_hists** hors boucle).
- **Tests** : headless 22/22 ; navigateur 19/19 ; test connecté 9/9 (fait avant).
- Engine33 déployé (app/scheduler). Note : ce chemin concerne le **moteur DB** (humains) ; le
  sim-memory reste bots-only.

**P2.1 restant** : hook IPFS (`UploadSessionHistoryJob`) pour humains ; events live dans le moteur
in-memory si humains inclus ; test navigateur d'une **vraie session** (wallet requis) ; choix
`game:engine` (DB, humains) vs `sim:memory` (bots) en prod.

---

## 38. P2.1 — IPFS + events humains déportés (engine34) 2026-11-10

- `SessionManager` : `archiveSessionHistory` (f_hists + `UploadSessionHistoryJob` → **IPFS** →
  CID on-chain) est **déporté dans l'outbox** (`session_history`) pour les humains (succès et
  ruine), quand `GAME_ENGINE_DEFER_SIDE_EFFECTS=true`.
- `SideEffectOutbox` : handlers `fight_result`, `archive_pool` (f_hists), `session_history`
  (IPFS), `payout`, `notification`, `sync_limits`, `cooldown`.
- **Boucle de jeu humaine non bloquée** : tout l'I/O lourd passe par l'outbox (`outbox:process`
  schedulé chaque minute + queue workers).
- Tests : headless 22/22 ; navigateur 19/19.

**Choix moteur (prod)** :
- **Humains / vrais joueurs** → moteur **DB** : `battlepool-engine` (`game:engine`) + `batch-processor`
  (écrit pools/fights, SessionManager + outbox). `GAME_ENGINE_WORKER=true` ⇒ `Pool::match()` no-op.
- **Simulations de bots** → `sim:memory` (full in-memory, 0 écriture DB/pool). **Ne pas lancer les deux**.

---

## 39. P2.1 (A) — humains dans les pools internes (engine36) 2026-11-10

- `InternalPoolService` : config `pool.internal_include_humans` (env `INTERNAL_INCLUDE_HUMANS`) →
  inclut les humains (`autoplay_active=false`, `initial_base_bet`/`target_q` définis, email **non** `bot_%`).
- **Vérifié** : humain de test id 1404 (balance=B=2.892, autoplay=0) **matché** dans un pool interne,
  **P3 initialisé** (`bankroll_total=2.892`, `session_capital=0.12`, balance → 0.12), puis rejoué.
- Le moteur DB (`battlepool-engine` engine36) + `batch-processor` traitent le pool (bots + humain).

**Blocages restants pour une VRAIE session wallet (test navigateur)** :
1. **Wallet headless** : injecter un provider EIP-1193 signé (ethers + clé de test) — faisable (faucet owner
   AVAXe `mint` + TST natif), mais non trivial.
2. **Sync dépôt on-chain → balance DB** : l'engine interne lit `users.balance` (DB). Le dépôt `B` crédite
   le **contrat**, pas la DB. Il manque la synchro (bridge/indexer ou `/update-balance`) pour que
   `canActivate`/P3 s'active après un vrai dépôt. **C'est le point critique restant.**

---

## 40. P2.1(A) — E2E humain validé : dépôt on-chain -> sync DB -> match -> P3 (2026-11-10)

**Fix** : `/update-balance` (BlockchainController) réactive désormais **aussi les humains**
(status stopped/awaiting_onchain, balance>=bet, sans claim ni cooldown), pas seulement les autoplay.

**Test E2E réel (server-side, wallet de test financé par le faucet)** :
1. Wallet de test généré + financé (0.5 TST gas + **5 AVAXe mint** par l'owner/faucet).
2. Humain créé en DB avec ce wallet (autoplay=0, email non-bot).
3. **Dépôt on-chain** 4 AVAXe (approve+deposit) → tx `0x8eeb4f98…` bloc 7124.
4. **Indexeur bridge** `DepositReceived` → `/internal/update-balance` → **balance DB = 3.636364** ✅
5. Humain `available` avec balance ≥ B → **matché dans un pool interne** (13 joueurs, bots+humain)
   → **P3 initialisé** : `bankroll_total=2.892`, `session_capital=0.12`, balance → 0.1196, pool joué.

⇒ **La chaîne complète fonctionne** : dépôt on-chain → sync DB → humain matché → session P3.
Il ne reste que l'**injection wallet headless** (harness navigateur) pour rejouer la même chose
depuis l'UI (même appel `approve`+`deposit`).

---

## 41. Test navigateur WALLET (injection EIP-1193) — 6/6 OK (2026-11-10)

`tests/browser/wallet_ui.cjs` : wallet de test financé (faucet), injecté comme `window.ethereum`
(ethers UMD + clé), IPFS court-circuité (route mock), session restaurée (token).
Flux UI réel : dashboard → setup → 10 premoves → **Start Session** → `approve` AVAXe + `deposit`
on-chain (3.1812 AVAXe).
- **Résultat** : dépôt on-chain accepté → **indexeur** → `/internal/update-balance` →
  **balance DB = 2.8920** → humain **matché** → **P3 initialisé** (`bankroll_total=2.892`,
  `session_capital=0.12`). Aucune erreur JS sévère. Screenshot `tests/browser/screenshot-wallet.png`.

⇒ **Chaîne complète validée depuis l'UI navigateur avec wallet** : dépôt `B=C+R` on-chain →
sync DB → match humain → session P3. **P2.1(A) terminé.**

---

## 42. Test contrat 0.5 % / security=100 / T=1.2 — temps de session (2026-11-11)

**Objectif** : mesurer le temps moyen de conclusion d'une session (win **et** loss) avec
frais contrat **0.5 %**, `security_coefficient=100`, `target=1.2` (au lieu de 1.05).

**Changements appliqués** :
- Contrat `BattlepoolV2` : `setFeeBasisPoints(50)` → `feeBasisPoints=50` (tx `0x644d9229…a60bc0`).
- `GameSetting.smart_contract_fee_percentage = 0.5`.
- `.env.staging` : `BANKROLL_SESSION_TARGET_Q=1.2`, `BANKROLL_SECURITY_COEFFICIENT=100`,
  `BANKROLL_FEE_RATE=0.005`, `REFUND_PCT=0.15` (calibré ≤ couverture ≈ 20.6 %).
- Bots : `target_q=1.2`, cohorte remise à plat (`bet_amount=initial_base_bet=0.0004`).
- Paramètres P3 : `C=0.04`, `X=6.03`, `R=0.2412`, `B=0.2812`, seuil `1.206`.
- **Bonus** : queue-workers/limits recréés en **engine37** (plus de mismatch d'image).

**Mesure** (full in-memory `sim:memory`, 1000 bots, ~66 min, **6421 sessions**) :

| | n | Pools médian | Temps médian | Temps moyen | p90 |
|---|---|---|---|---|---|
| **Gagnée** | 6261 (97.5 %) | 338 | **3.52 min** | 7.40 min* | **20.2 min** |
| **Perdue (double échec)** | 160 (2.49 %) | 3119 | **17.74 min** | **20.8 min** | **37.0 min** |

\* moyenne censurée à droite (max observé 63.8 min ≈ durée du run ; vraie moyenne > 7.4 min).
Théorie : `P(win/tentative)=82.9 %`, `P(double échec)=2.92 %`.

**Insight majeur — écart moteur DB vs in-memory** : le moteur DB traite ~4–6 pools/s
pour 1000 bots (~0.05–0.08 pool/s/joueur) ⇒ une session gagnée médiane (338 pools)
y prendrait **~1.1–1.9 h**, contre ~3.5 min en in-memory. **Les humains doivent donc
passer sur le moteur in-memory + outbox (P2.1 live)** pour la parité bots/humains.

**Artefacts** : `tests/measurements/session_time_security100_T1.2_fee0.5.md` +
CSV brut `tests/measurements/sessions_security100_T1.2_fee0.5.csv`.
État live restauré : `engine37`, moteur DB + batch actifs, `sim-memory` arrêté,
cohorte 1000 bots réinitialisée avec les nouveaux paramètres.

---

## 43. UI — cibles P3 1.05 / 1.2 + cible par joueur (engine38, 2026-11-11)

**Demande** : le sélecteur « MULTIPLICATEUR CIBLE (Q) » n'exposait pas 1.05/1.2 de façon
cohérente avec le modèle P3.

**Modifs** :
- `public/index.html` : options `1.05x` et `1.2x` (remplace `1.001x`), `1.2` sélectionné par défaut.
- `config/economy.php` : `target_q_options = [1.05, 1.2, 1.3, 1.5, 2, 3, 4]`.
- `game.js` :
  - `fetchConfig` aligne le sélecteur sur `BANKROLL_SESSION_TARGET_Q` et lit `bankroll_fee_rate`.
  - `updateFeeDisplay` / `startSession` calculent **X, R, B** à partir de la **cible choisie** :
    `X = (1 + t + φ·T)/t`, `R=C·X`, `B=C+R`, frais `φ·B` (identique au backend).
  - sélectionner **1.05** affiche **R=0.8442 / B=0.8842** (C=0.04) ; **1.2** → **R=0.2412 / B=0.2812**.
- Backend `BankrollSessionService::params($baseBet, ?T)` / `initialize(..., ?T)` :
  la cible du joueur (`users.target_q`, posée par `/user/pre-moves`) pilote X/R/B.
  `InternalPoolService` + `PoolReconstructionService` passent la cible du joueur.
- `startRecovery` crédite le fonds avec le T réel du joueur (`bankroll_target`).

**Tests** :
- `bankroll_state_machine_test` : **24/24**.
- Navigateur `headless_ui.cjs` (engine38 live) : **23/23** — options 1.05/1.2 présentes,
  C/R/B corrects pour 1.2, bascule 1.05 → R/B recalculés, aucune erreur JS.
- API vérifiée : `target=1.2`, `fee_rate=0.005`, `refund=0.15`, `contract_fee=0.5`, `X=6.03`.
- Tinker : `params(0.0004, 1.05)` → `X=21.105, B=0.8842` ; `(…, 1.2)` → `X=6.03, B=0.2812`.

**Déploiement** : image **`battlepool-app:engine38`** (app/queue/queue-limits/scheduler/engine).
Commit `1017c44`.

---

## 44. Fix P3 — dépôt pendant une session active (engine39, 2026-11-11)

**Constat (wallet `0x70FE…fC2f`, user 1407)** : 2e dépôt `0.888621` AVAXe (total crédité
on-chain **4.2154**). L'indexeur `/internal/update-balance` a écrasé `users.balance` avec le
total cumulé **sans** réinitialiser P3 (`bankroll_total=2.892` déjà défini) ⇒ le joueur jouait
avec 4.21 alors que P3 croyait `C=0.12, R=2.772` ; au gain, `bankroll_remaining` aurait été
ajouté → sur-crédit / payout risqué.

**Fix** :
- Migration `2026_11_11_000000_add_pending_bankroll_total_to_users` (`pending_bankroll_total`).
- `BankrollSessionService::reinitializeFromTotal(User, total, ?T)` : recalcul **C/R/B** depuis
  le total on-chain cumulé avec la **cible choisie** : `C = baseBet×security`, `R = total−C`,
  `X = R/C` (même ratio capital/mise), `balance=C`, `session_started=false`, `bet=baseBet`.
- `applyPending(User)` : applique le top-up quand le joueur n'est plus en pool (et sans
  claim/cooldown actif).
- `BlockchainController::updateUserBalance` :
  - joueur P3 **en pool** (ou claim/cooldown) → mémorise `pending_bankroll_total`, **ne touche pas**
    à la balance de jeu ;
  - joueur P3 **hors pool** → réinitialise immédiatement C/R/B.
- `SessionManager` (sim + non-sim + no-fight) : applique le pending en fin de pool avant
  l'évaluation de session (q remis à 1.0).
- `InternalPoolService` : applique les pending en attente **avant** le choix des paliers
  (remet la mise de base, évite un mismatch de tier).

**Réparation live** : user 1407 réinitialisé au passage de pool (20:18:58) →
`balance=0.0396`, `bankroll_total=4.2154`, `bankroll_remaining=4.1754`, `C=0.04`,
`X=104.385`, `pending=null`, target 1.05. Plus de sur-crédit au gain.

**Surveillance** : service `watch-0x70fe` (`/root/watch_0x70FE.cjs`, log
`/root/logs/watch_0x70FE.log`) — dépôts/payouts/cooldowns/transferts AVAXe + état DB
(balance, P3, pool) toutes les 15 s.

**Déploiement** : image **`battlepool-app:engine39`** + migration. Tests : bankroll **24/24**,
`reinitializeFromTotal` vérifié (0.8842→C=0.04/R=0.8442/X=21.105 ; 4.2154→R=4.1754/X=104.385),
`applyPending` (bloqué en pool, appliqué hors pool). Commit `7a41518`.

## 45. P3 — `initialize` utilise le total on-chain réel (engine40, 2026-11-11)

Complément du fix top-up : à l'initialisation d'une **nouvelle** session P3, si le joueur a
déjà un solde on-chain **supérieur** au B recommandé (rejeu sans retrait, top-up antérieur,
gain non retiré), `initialize` utilise désormais ce total comme bankroll :
`B = total`, `R = total − C`, `X = R/C`, `balance = C`. Sinon (dépôt standard = B), comportement
inchangé.

Vérifié (tinker, transaction rollback) :
- `balance=4.2222` → `B=4.2222, R=4.1822, X=104.555, C=0.04, balance=0.04`
- `balance=0.8842` → `B=0.8842, R=0.8442, X=21.105, C=0.04, balance=0.04`

**Live (wallet 0x70FE…fC2f)** : après le fix top-up (20:18:58), la session a **gagné** à
20:20:45 → `balance=4.2222`, `bankroll_total=null`, `status=stopped` (pas de sur-crédit ;
le gain est ~+0.0068 vs total crédité 4.2154). Le watcher a capté l'événement.

**Déploiement** : **`battlepool-app:engine40`**. Commit `7ac3b71`.

## 46. Retrait refusé — opérateur bridge sans gas TST (2026-11-11)

**Symptôme** : `claim` du wallet `0x70FE…fC2f` → `execution reverted: "Amount exceeds user balance"`
(amount demandé 4.2222, `userBalances` on-chain 4.2154).

**Cause** : le wallet **hot** du bridge (`payout_operator` `0x9A60…6663`) n'avait plus de **TST**
(0.000835 TST). La synchro `updateUserBalance` faite avant `/generate-signature` échouait donc
(`insufficient funds for intrinsic transaction cost`), et le contrat restait à 4.2154 — le gain
off-chain de 0.0068 n'était pas encore crédité on-chain.

**Fix** :
- Remplissage de l'opérateur : **+20 TST** depuis le owner/faucet (tx `0xda6b…dfb56`).
- Synchro manuelle `updateUserBalance(user, 4.2222)` par l'opérateur (tx `0x7a18…3fc80`)
  → `userBalances(user)=4.2222` ; la signature existante (deadline future) est réutilisable.
- **Auto-refill gas** : `battlepool-gas-refill.timer` (toutes les 10 min) ; si opérateur < **5 TST**,
  envoie **20 TST** depuis le owner. Log `/root/logs/gas_refill.log`.

Le bridge peut désormais envoyer les `setUserLimits`/payouts (plus d'erreurs `INSUFFICIENT_FUNDS`).

## 47. Claim — pré-synchro en fin de session + retry front (engine42, 2026-11-11)

**Problème** : la synchro `updateUserBalance` est faite par le bridge au moment du
`/generate-signature` (clic claim). Si la tx de synchro est encore en file (queue bridge
chargée), le claim part avec l'ancien `userBalances` → `Amount exceeds user balance`.

**Fix (les deux)** :
1. **Pré-synchro en fin de session** : `SessionManager::closeSession` dispatche
   `SyncUserBalanceJob` pour tout **humain non-bot** avec solde > 0. Le job appelle
   `SessionManager::preSyncHumanClaim` → bridge `/generate-signature` (qui exécute
   `updateUserBalance` **et** pré-génère la signature). Le solde on-chain est donc aligné
   avant que le joueur ne clique.
   - `isBotUser()` mémoïsé (lecture unique de `simulation_accounts.json`).
2. **Retry front** : `sendClaimWithRetry()` dans `game.js` ; sur
   `Amount exceeds user balance`, attend 3s/6s et retente (3 tentatives) avant d'afficher
   l'erreur.

**Validé live** : le claim de **0.8882 AVAXe** du wallet `0x70FE…fC2f` a réussi à
**20:48:45** (tx `0x3a10…8793`), DB remise à 0. Déploiement **`battlepool-app:engine42`**.
Commit `378ff91`.

## 48. Claim — file de tx bridge bloquée + sync à la demande (engine43/44, 2026-11-11)

**Symptôme** : après un gain (balance DB 0.8866, `userBalances` on-chain 0.8842), le claim
échoue encore `Amount exceeds user balance`, même avec le retry 3×.

**Cause** : la **file de tx en mémoire** du bridge (`txQueue`/`manageNonce`) était restée
bloquée (reliquat de la panne de gas) : les `updateUserBalance` de `/generate-signature`
n'étaient plus minés, le endpoint rendait la signature après son timeout 8 s. `latest`/`pending`
nonces étaient pourtant égaux → blocage interne, pas un gap on-chain.

**Fix** :
- **Redémarrage du bridge** (file en mémoire vidée, nonce resynchronisé).
- Synchro manuelle `updateUserBalance(0x70FE…, 0.8866)` (tx `0x3902…8ff5f`).
- **`POST /api/claim/sync`** (auth token) : force `preSyncHumanClaim($user, true)` →
  `updateUserBalance` + nouvelle signature, retourne `{balance, payout_signature, payout_deadline}`.
- **Front** : `sendClaimWithRetry` appelle `/api/claim/sync` à chaque échec
  `Amount exceeds user balance`, met à jour `pendingClaim` et retente (3×).
- **Throttle** des `SyncUserBalanceJob` (cache 30 s par user) pour éviter de marteler le
  bridge ; `force=true` réservé au endpoint de claim.

**Déploiement** : `battlepool-app:engine43` puis `engine44`. Commits `e196ffd`, `375444c`.

---

## 42. P2.1 CLÔTURÉ (engine38) — 2026-11-10

- **MatchFound + BATTLE_STARTED humains** : émis par l'engine via **outbox** (`match_found`,
  `battle_started`), avec insert individuel du fight pour obtenir l'`id`. **Vérifié live** :
  humain matché → `match_found/battle_started/fight_result/sync_limits` en outbox → **`done`**.
- **Consumer d'audit dédié** : commande `fights:archive {--once --sleep}` + **schedule chaque
  minute**. (Le Stream est aussi consommé par les moteurs ; la table `fights` reste l'audit
  persistant, le JSONL est complémentaire.)
- **Singletons** : `GameEngineService`, `GameEventBuffer`, `FightStreamWriter` enregistrés
  (`AppServiceProvider`) → accumulation/flush fiables.
- **Tests** : headless **24/24** (test outbox rendu robuste) ; navigateur **23/23**.
- **Item 4 (issue complète win/ruin + session_history/IPFS + refund)** : composants validés
  (session/refund unit tests, outbox live, wallet E2E) ; l'observation d'une **issue réelle**
  reste un test long (minutes–heures) à laisser tourner (humain de test en cours).

⇒ **P2.1 fermé** (hors test d'issue longue optionnel). Tous les services en **engine38**.

## 49. UI — les joueurs qui rejoignent via l'UI sont non-autoplay (engine45, 2026-11-12)

**Demande** : tout joueur qui rejoint via l'UI doit être **non-autoplay** par défaut (pour recevoir
les events live et le flux claim humain), tout en gardant les **bots en autoplay**.

**Modifs** :
- `resources/js/modules/game.js` (`startSession`) : le POST `/user/pre-moves` envoie
  **`autoplay: false`**.
- `PoolAutoMatchController::storePreMoves` : validation `autoplay` (nullable|boolean).
- `PreMoveService` : flag explicite prioritaire ; **sans flag**, défaut = `false` si l'email n'est pas `bot_%` (UI/humain), `true` si `bot_%` (bots). Protège aussi les anciens fronts en cache.
- `UserDataService::registerForAutoplay(..., bool $autoplay = true)` : écrit `autoplay_active`
  selon le flag.
- Wallet `0x70FE…` (user 1407) repassé à `autoplay_active=0` (il était autoplay=1).

**Conséquences** :
- UI humain → `isBot()=false` → events `match_found` / `battle_started` / `fight_result` via outbox,
  signature/claim humain, cooldown/syncLimits.
- Scripts bots (`/user/pre-moves` sans flag) → `autoplay_active=1` → fast-path bot inchangé.
- Les humains non-autoplay restent éligibles aux pools internes
  (`INTERNAL_INCLUDE_HUMANS=true` + `initial_base_bet`/`target_q`).

**Vérifs** : `registerForAutoplay(false)` → autoplay=0 ; `(true)` → 1 ; bundle contient
`autoplay:!1` ; headless E2E **24/24**. Déploiement **`battlepool-app:engine46`**. Commits `7be922c` + `83c840e`.

## 50. Humains dans le moteur in-memory (engine48/49, 2026-11-12)

**Objectif** : jouer les sessions des joueurs UI **dans `sim:memory`** (vitesse bots), pas dans `game:engine`.

**Implémentation** :
- `InMemorySimulationService` : `SIM_INCLUDE_HUMANS=true` charge dynamiquement les humains
  (`autoplay=0`, email non `bot_%`, `initial_base_bet`/`target_q`, status `available`) toutes les
  `SIM_HUMAN_REFRESH_SECONDS` (5 s).
- P3 : `initialize` (C/R/B) à l'arrivée ; état persisté après chaque pool ; win → payout
  (balance + R en session 1), signature/claim via `SyncUserBalanceJob` ; double échec →
  `enqueueRefund` ; cooldown/sync_limits/session_finished/session_history via outbox.
- Combats humains : insertion `fights` (pool_id NULL → persiste hors cleanup), outbox
  `match_found`/`battle_started`/`fight_result` par combat.
- **Famine** : si le palier de mise de l'humain a <13 joueurs in-memory, retour au palier de base.
- **Double traitement** : `GAME_ENGINE_SIM_MEMORY=true` désactive le matchmaking DB
  (`/metrics/collect`, `InternalPoolService`, `BatchProcessingService`) quand `sim:memory` est actif.
- Basculé : `battlepool-engine` + `batch-processor` **arrêtés**, `battlepool-sim-memory`
  (engine49) actif, cohorte **3461** (3460 bots + humain 1407).
- Vérifié live : humain chargé, combats/outbox, updates DB par pool, `pools` DB ne grossit plus.

**Déploiement** : `battlepool-app:engine48` (sim) + `engine49` (app/queue/scheduler).
Commits `289df3a`, `33ab4c6`, `bfa3d73`.

## 51. Test in-memory humain — priorité pools + remplissage martingale (engine50, 2026-11-12)

**Résultat live (wallet 0x70FE…)** :
- Dépôt/P3 init **12:04:12**, win **12:04:39** → **27 secondes** de session (vs 25 min avant le fix, 8 min moteur DB).
- **49 combats** répartis sur les paliers **0.0004 (17), 0.0008 (18), 0.0016 (10), 0.0032 (4)** → la martingale est **réellement jouée** (paliers remplis par des bots).
- Payout **0.2904 AVAXe**, claim on-chain tx `0xc00a…9b85` à **12:05:34** ; wallet **10.704632 AVAXe** ; DB remise à 0.
- Cooldown 3 jours (jusqu'au 15/11 12:04:39) ; à effacer pour un nouveau test.

**Fix** : `InMemorySimulationService::buildHumanPool()` priorise un pool humain par cycle et,
si son palier a <13 joueurs, emprunte des bots (priorité base, sans casser un rattrapage) en
conservant la mise de martingale. Fallback au palier base seulement si le remplissage échoue.

## 52. Fonds de remboursement 100 % de B — frais contrat 4 % (2026-11-12)

**Décision produit** : rembourser **100 % de B** en cas de double échec, avec des frais relevés
pour financer le fonds.

**Math** : `q=(1−1/(T(1+φ)))²` ; frais fonds `=φ·T·B` ; couverture `=φ·T/q`.
À T=1.2, φ=0.04 : `q≈3.95 %`, couverture **≈121 %** (viable). À φ=0.5 %, couverture ≈20 %
(100 % impossible → d'où le changement).

**Changements** :
- Contrat V2 : `setFeeBasisPoints(400)` → **4 %** (tx `0xa25f…350f`).
- `GameSetting smart_contract_fee_percentage=4`.
- `.env.staging` : `BANKROLL_FEE_RATE=0.04`, `REFUND_PCT=1.0`.
- P3 recalculé : `C=0.04`, `X=6.24`, `R=0.2496`, `B=0.2896`, dépôt `B+frais = 0.301184`
  (pour base 0.0004 / T=1.2). Cible 1.05 → `X=21.84`, `R=0.8736`, `B=0.9136`.
- `/api/artefacts` vérifié : fee=0.04, refund=1, contract_fee=4, X=6.24.
- Tests : headless **24/24**, navigateur **23/23** (attendus mis à jour).

**Cas de ruine précédent** : remboursé à 15 % (0.04218) avant le changement ; le nouveau taux
100 % s'applique aux **prochains** double-échecs. Fonds actuel ≈ **1.9986 AVAXe** ; un
remboursement 100 % = 0.2896. Le fonds est alimenté par `F_C+F_R=φ_used·B≈0.0139` par cycle.

## 53. Frontend — toasts au-dessus des overlays + bouton AVAXe après claim (engine52)

- **Toast** : `toast.js` utilise désormais le **top-layer HTML** (`popover="manual"` +
  `showPopover()`, append sur `<html>`, z-index max) → visible au-dessus des modales/overlays.
- **Airdrop AVAXe** : `/api/user/status` expose `has_received_avaxe_airdrop` +
  `avaxe_airdrop_tx_hash` ; le bouton est masqué après le claim (flag local + flag serveur)
  et l'état localStorage est synchronisé.
- Backend faucet : secret `faucet_wallet_pk` monté dans le bridge (5 AVAXe envoyés,
  tx `0x4bae…`).
- Tests : headless **24/24**, navigateur **23/23**. Déploiement **engine52**.

## 54. Frontend — première connexion accélérée/robustifiée (engine53)

**Problèmes** : la première connexion était longue (modale de langue bloquante ~77 s dans
le log) et échouait souvent (doubles clics, nonce expiré, appels wallet redondants, pas de timeout).

**Correctifs** :
- `i18n` : **plus de modale bloquante** au premier chargement → auto-détection
  `navigator.language` (fallback `fr`), `?lang=` et réglages conservés.
- `auth.js` : verrou anti double-clic (`_authInProgress`) ; `eth_accounts` d'abord (pas de
  popup inutile), `eth_requestAccounts` seulement au premier login ; timeout 20 s sur
  challenge/verify ; **retry automatique sur nonce expiré** ; TTL nonce backend **900 s**.
- Boutons Connect : état **« CONNEXION… »** + désactivation pendant le flux.
- Mesure Playwright (wallet EIP-1193 injecté) : **auth réussie en 2,87 s** (vs ~77 s + auth).

Déploiement **engine53**. Headless **24/24**, navigateur **23/23**.

## 55. Connexion wallet — WalletConnect ProjectId + disque plein (engine54, 2026-11-13)

**Symptômes** : WalletConnect échouait (`AppKit indisponible — ProjectId manquant`,
puis `Fatal socket ... 3000 Project not found`) ; connexions/auth instables.

**Causes** :
1. Le **ProjectId WalletConnect/Reown** n'était **pas injecté au build** : `Dockerfile.prod`
   ne déclarait pas `ARG VITE_WALLETCONNECT_PROJECT_ID` (le compose le passait dans le vide).
2. **Disque VPS à 100 %** : le conteneur `sim-memory` avait **33 Go** de JSONL
   (`storage/app/fights-archive`), ce qui cassait les écritures/auth/DB.

**Correctifs** :
- `deploy/Dockerfile.prod` : `ARG/ENV VITE_WALLETCONNECT_PROJECT_ID` + `VITE_APP_NAME` avant
  `npm run build` ; build engine54 avec le ProjectId existant (`e46cab…`). `/opt/.env` contient
  aussi `VITE_WALLETCONNECT_PROJECT_ID` pour les futurs `docker compose build`.
- Nettoyage disque : suppression des **33 Go** de JSONL du sim + `docker builder prune -af`
  (~40 Go libérés) ; disque ~33 %.
- **Archive JSONL du sim désactivée** (`SIM_ARCHIVE_ENABLED=false`, `RunMemorySimulation`) :
  elle grossissait de plusieurs Go/heure. Le scheduler garde son consumer d'audit.
- Le bouton **Connect Wallet (injected)** reste le chemin desktop ; **WalletConnect/AppKit**
  fonctionne désormais avec le ProjectId (plus de `Project not found`).

**Déploiement** : engine54 (app/reverb/bridge/sim), tests headless **24/24**, navigateur **23/23**.

## 56. Suppression `ReferralRewards.sol` (legacy) + revalidation (engine39, 2026-11-15)

**Décision** : le parrainage effectif est **off-chain en PRANA `locked_balance`** (crédit shop) ;
le contrat on-chain `ReferralRewards.sol` (100 SNT/PRANA par référence) n'est **pas branché**.

**Vérification avant suppression** : aucune référence runtime (pas d'ABI/adresse dans
`config.js`/`app.js`/`full_deploy.js`, aucun appel backend/bridge, aucune entrée dans les
`*_deployments.json`). Mentions restantes = docs/graphify uniquement.

**Action** : `git rm smart_contracts/ReferralRewards.sol` (commit `38411dc`), push
`feat/session-recovery`. Rebuild `battlepool-app:engine39` depuis `/tmp/deploy-engine`
(`BUILD=0`), redéploiement `app/queue-worker/queue-worker-limits/scheduler/engine`, santé **200**.

**État live** : `sim-memory` (engine65, `sim:memory --pools=400 --limit=5000`, `SIM_NO_PAYOUT`)
tournait **en parallèle** du moteur → **arrêté** (règle : jamais engine + sim simultanément).
`.env.staging` avait `GAME_ENGINE_SIM_MEMORY=true` (mode exclusif sim) → repassé à **`false`**,
services recréés : `engine` **healthy**, traite **40 pools/tick**.

**Tests sans le contrat** : `tests/headless_e2e.sh` **24/24** ; `headless_ui.cjs` **23/23**
(image Playwright v1.47 + `npm i playwright@1.47.0`). `connected_ui.cjs`/`wallet_ui.cjs`
exigent un wallet financé (`PK/ADDRESS/TOKEN`) non fourni → non exécutés, sans lien avec le contrat.

**Aucun impact runtime** : parrainage inchangé (PRANA via `locked_balance`).

## 57. Correction d'état : rollback engine39/DB engine annulé (engine69, 2026-11-15)

Le §56 (engine39, `GAME_ENGINE_SIM_MEMORY=false`, moteur DB actif) était le
résultat d'un contexte **obsolète** : il ne connaissait pas les travaux
engine60→68 (PWA mobile, claim/refund, cible verrouillée, graphique/HUD/langues).
Les valeurs P3 du résumé précédent (10 %, security 300, target 1.05, refund 68 %)
étaient également **périmées**.

**État de référence restauré** :
- image **engine69** (build depuis le HEAD courant, incluant la suppression
  `ReferralRewards.sol`) sur app/queue-worker/queue-worker-limits/scheduler/
  reverb/sim-memory/bridge ;
- `engine` et `batch-processor` **arrêtés/Created** ; **sim-memory actif** ;
- `.env.staging` : `GAME_ENGINE_SIM_MEMORY=true`, `SIM_INCLUDE_HUMANS=true` ;
- config P3 active : **4 %**, **target 1.2**, **security 100**, **refund 100 %** ;
- `GIT_SHA=engine69` dans `.env` ;
- `ReferralRewards.sol` reste supprimé : aucune référence runtime, validation
  build + tests OK.

**Tests** : headless **24/24**, navigateur **23/23** (engine69, sim-memory).

## 58. On-ramp USDT → AVAXe — MVP manuel + bot Telegram (engine70/71, 2026-11-15)

**Périmètre livré** : MVP manuel allowlist, USDT **TRC20 / BEP20 / Polygon PoS**,
Telegram prioritaire (WhatsApp écarté pour crypto ; Discord plus tard après audit).
Aucune clé privée dans le bot, aucun crédit automatique.

**Backend**
- migration `onramp_link_codes`, `onramp_links`, `onramp_orders`, `onramp_events`
  + `users.onramp_allowed` ;
- `OnrampService` : code de liaison, liaison chat, acceptation des conditions,
  création commande, soumission hash USDT, approbation/refus admin, enregistrement
  du hash de crédit AVAXe (manuel), ledger/événements, limites journalières ;
- `TelegramService` + `OnrampTelegramBot` : `/start`, `/link`, `/accept`, `/buy`,
  `/tx`, `/status`, `/help`, admin `/orders`, `/approve`, `/reject`, `/credit`,
  `/allow`, `/disallow` ; boutons inline ;
- `TelegramWebhookController` (`POST /api/telegram/webhook`, secret header) ;
- routes user (`/api/onramp/link-code`, `/onramp/orders`) et admin
  (`/api/admin/onramp/...`) ;
- commande `onramp:telegram-set-webhook`.

**Frontend** : bouton « Lier Telegram / acheter des AVAXe » (dashboard), génère
un code de liaison et un deep link Telegram.

**Sécurité / conformité** : conditions affichées, AVAXe de test non remboursables,
allowlist obligatoire, validation admin, hash unique, adresses de dépôt par réseau,
aucun transfert automatique. WhatsApp non utilisé (politique crypto/jeu argent réel).

**Config** (`.env.staging`, désactivé par défaut) : `ONRAMP_ENABLED=false`,
`ONRAMP_TERMS_*`, `ONRAMP_USDT_{TRC20,BEP20,POLYGON}_ADDRESS`,
`TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_BOT_USERNAME`,
`TELEGRAM_ADMIN_IDS`, limites. Migration exécutée en prod.

**Tests** : smoke service lien→commande→review→crédit (10 USDT → 100 AVAXe,
4 événements, rollback) ; webhook désactivé sans token ; bot `/start`/`/buy`
simulé avec Http fake ; headless **24/24**, navigateur **23/23**.

**À fournir pour activer** : token/username Telegram, secret webhook, IDs admin,
adresses USDT par réseau, conditions/URL, allowlist users, puis
`ONRAMP_ENABLED=true`, recreate app et `onramp:telegram-set-webhook`.

## 59. On-ramp : packages AVAXe + page admin web (engine80, 2026-11-17)

- Packages désormais définis en **AVAXe** (1 AVAXe = 1 AVAX) :
  `mini` 0.35 AVAXe (dépôt min session 0.301184 + marge), `starter` 1 AVAXe,
  `plus` 5 AVAXe. Le montant USDT est calculé avec
  `ONRAMP_AVAX_USDT_RATE=7.13` (mini 2.50 USDT, starter 7.13, plus 35.65).
- Ancienne commande `BP-20261117-9Q7F8Q` (10 USDT→100 AVAXe) **refusée/annulée**.
- **Page admin web** : `/admin/onramp.html` (static, même origine, `noindex`).
  - auth via `auth_token` + `/api/user` (`is_admin`) ;
  - filtres commandes, détail wallet/réseau/montants/hash, approve/reject,
    enregistrement du hash de crédit AVAXe, copie, explorer links ;
  - allowlist : recherche user, autoriser/retirer ;
  - auto-refresh 30 s, responsive mobile.
- API admin ajoutée : `GET /api/admin/onramp/users` (recherche).
- Lien header **🛡 ADMIN** visible uniquement pour `is_admin` via `/api/user`
  (user 322 `0x1F14…` : `is_admin=1`, `onramp_allowed=1`).
- Test Playwright admin : pending_review → approved → credited, lien ADMIN
  visible, aucune erreur. SW v15.

## 60. KYC manuel + pages légales (engine84, 2026-11-18)

- Migration `kyc_profiles` + `users.kyc_status/submitted_at/reviewed_at`.
- `KycService` / `KycController` : soumission manuelle, statut, liste admin,
  approbation/rejet ; `id_number` chiffré ; dernière 4 exposée seulement.
- Onramp : `ONRAMP_REQUIRE_KYC=true` ; une commande exige KYC **approuvé**
  + allowlist.
- Page user `/kyc.html` : statut, formulaire, consentement, resoumission après rejet.
- Admin page : section **KYC à vérifier**, approuver/refuser/autoriser on-ramp.
- Lien **🪪 KYC** dans le header (connecté) + footer légal.
- Pages légales statiques : `/legal/terms.html`, `privacy.html`, `kyc-aml.html`,
  `risk.html` (modèles à compléter/valider par un avocat ; champs `[...]`).
- `ONRAMP_TERMS_URL` pointe vers `/legal/terms.html`.
- Testé : KYC unverified → pending via page, approbation API admin → approved,
  création commande onramp OK ; pages légales 200 ; SW v16.

## 61. On-ramp semi-auto + alignement frais contrat (engine85/86, 2026-11-18)

- Nouveau worker `smart_contracts/onramp_worker.js` + service compose
  `onramp-worker` (image bridge, Tailscale/internal, secret
  `onramp_treasury_pk`).
- Flux : admin approuve → worker claim la commande `approved` → envoi AVAXe
  depuis le hot wallet `0x8B21...` → receipt → statut `credited` + hash +
  événement + notification Telegram.
- Garde-fous : claim transactionnel, `credit_attempts`, reprise des crédits
  en cours, timeout receipt, `manual_review` sans renvoi automatique, réserve
  hot wallet, gas TST, montant max worker, pause env.
- Statuts admin ajoutés : `crediting`, `manual_review`.
- Test réel : `BP-20261117-TUTN5B` (0.35 AVAXe, user #322) approuvée la veille
  a été créditée automatiquement tx
  `0x1e1651b23c8a2dd5dc4e8addb55668a293f103f7d4557415401a10a8d2c5782c` ;
  hot wallet 299.65 AVAXe après.
- Frais contrat : l’on-chain renvoie `feeBasisPoints=400` (4 %) ; le seeder
  et le défaut étaient encore à 2.5 %. Corrigé seeder/config/défaut + DB
  `smart_contract_fee_percentage=4`.
- Tests : headless **24/24**, navigateur **23/23**.

## 62. Adresses de dépôt uniques + watcher auto (engine88, 2026-11-18)

- Table `onramp_deposit_addresses` : pool HD (index, adresse, order_id, assigned_at).
- Seed dédié : `/opt/battlepool/.secrets/onramp_deposit_seed` (600). **À sauvegarder
  hors VPS** ; sans lui, les USDT des adresses dérivées sont inaccessibles.
- Pool initial : 2000 adresses, dérivation `m/44'/60'/0'/0/{index}`.
- `OnrampService::createOrder` alloue une adresse libre dans une transaction ;
  l'ancienne adresse statique n'est plus utilisée pour les nouvelles commandes.
- Nouveau service `onramp-deposit-watcher` (BSC/Polygon) :
  - scan `Transfer` USDT vers les adresses uniques, confirmations 15/30 ;
  - auto-approbation si montant exact, KYC/allowlist OK et ≤ 50 USDT ;
  - sinon `pending_review` + notification admin ;
  - tx hash stocké, événements `deposit_confirmed`/`order_auto_approved`.
- Le worker `onramp-worker` crédite ensuite en AVAXe (déjà en place).
- RPC : BSC `bsc-rpc.publicnode.com`, Polygon `polygon-bor-rpc.publicnode.com`.
- Contrats USDT : BSC `0x55d398...7955` (18 dec), Polygon `0xc2132D...8e8F` (6 dec).
- Ancienne commande pending statique `BP-20261117-9RWGNE` expirée.
- Test création commande : adresse unique `0x265F...671c`, index 0, pool 2000.
- ⚠️ Sweep USDT vers trésorerie **non automatisé** : les USDT restent sur les
  adresses dérivées ; il faudra du gas BNB/POL et un sweeper séparé.
- ⚠️ Détection d'un vrai dépôt USDT non testée faute de fonds USDT ; à valider
  avec un petit dépôt de 2.50 USDT.
- Tests engine88 : headless **24/24**, navigateur **23/23**.
- `onramp-deposit-watcher` actif, RPC USDT OK ; premier scan sans erreur.

## 63. Seed HD dépôts accessible via l’éditeur Tailscale (2026-11-18)

- L’éditeur secrets `http://100.120.217.57:4098/` (Basic auth, Tailscale-only)
  affiche une carte **Seed HD des adresses de dépôt**.
- La seed n’est **pas** dans la source de la page ; elle est chargée après clic sur
  `POST /seed/reveal` avec CSRF, puis affichée dans un textarea + bouton Copier.
- Réponse `Cache-Control: no-store`, `X-Frame-Options: DENY`, audit
  `seed_reveal` (IP/action, jamais la seed).
- Fichier : `/opt/battlepool/.secrets/onramp_deposit_seed` (600) ;
  env systemd `ONRAMP_DEPOSIT_SEED_FILE`.
- Testé : page 200, seed absente du HTML, reveal authentifié 200 et identique
  au fichier (SHA-256), sans auth 401.
- Lecture seule : ne pas remplacer la seed sans sauvegarde + régénération du
  pool d’adresses.

## 64. Sweeper USDT BNB/POL (engine89, 2026-11-18)

- Migration `onramp_orders` : `sweep_status`, `sweep_tx_hash`, `sweep_amount`,
  `sweep_attempts`, `sweep_started_at`, `sweep_last_error`, `swept_at`.
- Nouveau service `onramp-sweeper` (image bridge, seed HD monté en secret).
- Pour chaque commande `approved/crediting/credited` avec dépôt USDT :
  1. dérive la clé de l’adresse de dépôt ;
  2. vérifie le solde USDT ;
  3. envoie un topup gas natif depuis la treasury si nécessaire ;
  4. transfère tout l’USDT vers la treasury ;
  5. statut `swept` + hash + événement + notification admin.
- Treasury/gas dérivée du seed à l’index **1 000 000** :
  `0xE4b74c895eA6B5c599AE578859Cce74E9317a40B`
  (même adresse sur BSC et Polygon ; USDT rapatriés ici).
- Seuils gas : BSC topup 0.0003 BNB / réserve 0.001 ; Polygon topup 0.02 POL /
  réserve 0.05. **Trésorerie actuellement à 0 BNB / 0 POL : sweeper en attente
  de gas.**
- Admin page : statut/hash de sweep dans les commandes.
- Garde-fous : `sweeping` transactionnel, `sweep_tx_hash` unique, `manual_review`
  sans renvoi automatique, reprise des sweeps en cours, notification gas bas.
- Tests : headless **24/24**, navigateur **23/23**, admin page sans erreur.
- ⚠️ Sweep réel non testé (pas de gas ni d’USDT de test). Alimenter la treasury
  en BNB (BSC) et POL (Polygon) avant le premier dépôt.
- Trésorerie sweep Polygon alimentée : **8.0 POL** (2026-11-18) ; largement
  suffisant (0.02 POL/topup). Test réel Polygon en attente du KYC/commande.

## 65. Rotation wallet admin 322 vers HD index 1 (2026-11-18)

- User admin **322** : ancien wallet `0x1f140e5a3cd2fcd52862084397c2fae172dff4ed`
  → nouveau wallet HD index 1
  **`0xc6ba478F12630f66337a0176308a5EA15369cd2A`**.
- Dérivation : `m/44'/60'/0'/0/1` depuis `onramp_deposit_seed`.
- Index 1 supprimé du pool d’adresses de dépôt (évite qu’une commande
  l’utilise) ; pool libre = 1999.
- 13 tokens API du user 322 révoqués : reconnexion avec le wallet index 1.
- KYC admin toujours `unverified` → `/buy` bloqué jusqu’à soumission + approbation.
- Audit : `/root/.onramp-admin-wallet-changes.log`.

## 66. Diagnostic KYC bot : comptes dupliqués après rotation wallet

- Rotation 322 vers index 1 a laissé l’ancien wallet `0x1F14...` créer un
  **nouveau user 3898** (KYC soumis + approuvé, profil KYC #2).
- Le chat Telegram `7386044730` était encore lié au **user 322** (KYC
  `unverified`) → `/buy` demandait le KYC.
- Correction : lien Telegram déplacé de 322 vers **user 3898**
  (`0x1F14...`, KYC approved, onramp_allowed=1, terms acceptés).
- Admin bot `/orders` `/approve` restent OK via `TELEGRAM_ADMIN_IDS`
  (indépendant du user lié).
- Admin web 322 / index 1 reste admin ; son KYC reste `unverified`.
- Journal : `/root/.onramp-link-changes.log`.
- **Sweep réel Polygon validé** sur `BP-20261119-S3BZZD` (user 3898) :
  - topup gas `0x18bc498cc674daff85f64588e4bf4548a27d8198fead7f0e4e16a4b1ae182a09` (0.1 POL)
  - sweep tx `0x36b40106bc595d375a8ee9d647b3e20c6f8896e9c490a9455a559a0971a3df3c`
  - 2.5 USDT déposés → treasury ; dépôt USDT = 0 ; receipt status 1.
- Premier essai échoué avec topup 0.02 POL : `insufficient funds for intrinsic
  transaction cost` (gas estimé 80 192 × ~273 gwei ≈ 0.0324 POL requis).
  Corrigé : Polygon gas min 0.05, topup 0.1, reserve 0.2. Trésorerie 7.868 POL,
  reliquat 0.1015 POL sur l’adresse de dépôt.

## 67. Message /buy plus court + bouton copier l’adresse (engine90)

- Message de commande Telegram raccourci : référence, montant, réseau, adresse.
- Adresse affichée seule dans un bloc `<pre>` (copie par tap).
- Bouton inline **📋 Copier l’adresse** avec `copy_text` (Bot API 7.11).
- Fallback : si le client/API refuse `copy_text`, le message texte avec `<pre>`
  est renvoyé sans bouton.
- Test : headless 24/24, navigateur 23/23.

## 68. Off-ramp AVAXe → USDT Polygon (engine91, 2026-11-19)

- Nouveau module off-ramp :
  - `offramp_orders`, `offramp_deposit_addresses`, `offramp_events`.
  - Pool HD 2000 adresses à partir de l’index **2 000 000**.
  - Taux **1 AVAXe = 7.13 USDT**, frais **0 %**.
  - Min **0.1 AVAXe**, max **10 AVAXe**, plafond **50 AVAXe/jour/user**.
  - KYC + allowlist, Polygon uniquement pour le payout.
  - Auto-payout si USDT ≤ **50** ; au-dessus/ambigu → revue admin.
- Bot Telegram : `/sell 0.1` (alias `/offramp`), message court avec adresse
  AVAXe + bouton copier. Admin page `/admin/offramp.html`.
- Services :
  - `offramp-deposit-watcher` (Pingala, 1 confirmation),
  - `offramp-payout` (USDT Polygon, treasury `0xE4b...`, lock MySQL partagé),
  - `offramp-avaxe-sweeper` (AVAXe → hot wallet, TST gas).
- TST gas wallet dédié index **1 000 002** :
  `0x4a4Bea1764C80c13F5662cb1810809784DB5a475`, financé 1 TST.
- **Test réel complet** commande `OFF-20261119-JPB49Q` (user 3898) :
  - 0.1 AVAXe déposés tx `0x63f9aafca0b912ea40e8ab5ff241b40d1d1e407da4277b7568aecd56380ccda0`
  - payout **0.713 USDT** tx `0x2f775e99a65a93c920fba345db7beca979a1e0574518f632628e1c6e31531d3e`
  - sweep AVAXe tx `0xcbcb9c99dc60520c6268823e63998a4d845812a3ef793c1d24a5225e05f6d1a4`
  - statut final `paid` + `swept`.
- Après test : trésorerie USDT **1.787 USDT**, TST gas **0.994 TST**,
  hot AVAXe ~299.3, utilisateur `0x1F14...` +0.713 USDT.
- Tests headless **24/24**, navigateur **23/23**, admin off-ramp sans erreur.
- Limites : BSC pas encore actif ; USDT treasury à financer ; l’utilisateur a
  besoin de TST pour envoyer ses AVAXe.

## 69. Wave Côte d’Ivoire manuel XOF ↔ USDT (engine93, 2026-11-19)

- MVP **manuel** sans API Wave ; vérification admin.
- Achat USDT via Wave (A) : `/wavebuy 300` → paiement XOF au
  **07XXXXXXXX** (Wave CI), preuve `/wavepaid REF ID`, puis USDT Polygon
  envoyés manuellement par l’admin.
- Vente USDT contre Wave (B) : `/wavesell 1 07XXXXXXXX` → dépôt USDT Polygon
  sur `0xE4b...`, tx `/wavetx REF HASH`, puis paiement XOF manuel par l’admin.
- Paramètres : **1 USDT = 600 XOF**, frais **0.5 % par sens**, min **0.5 USDT /
  300 XOF**, max **100 USDT / 60 000 XOF**, plafond **500 USDT/jour**.
- KYC + allowlist. USDT **Polygon uniquement** ; BSC plus tard.
- Bot : `/wave`, `/wavebuy`, `/wavesell`, `/wavepaid`, `/wavetx`, `/waveorders`.
- Admin : `/admin/wave.html` (marquer payé, hash USDT ou référence Wave, refuser).
- Tables `wave_orders` / `wave_events`, statuts pending_payment / pending_deposit /
  pending_review / paid / rejected.
- Testé : création A 300 XOF → **0.4975 USDT**, B 1 USDT → **597 XOF** ;
  page admin sans erreur ; headless 24/24, navigateur 23/23.
- ⚠️ Aucune transaction Wave réelle testée. Bénin/MTN/Moov non activés :
  il manque les numéros/comptes correspondants.
- Trésorerie USDT Polygon : **1.787 USDT** ; à financer pour les payouts A.

## 70. Séparation WaveBot / BattlePoolBot (engine94, 2026-11-19)

- Bot Wave indépendant **@MobileCryptoXBot** : `WaveTelegramBot`,
  `WaveTelegramService`, webhook dédié `/api/telegram/wave/webhook`.
- Commandes `/wave*` retirées de BattlePoolBot.
- Variables dédiées : `WAVE_TELEGRAM_BOT_TOKEN`,
  `WAVE_TELEGRAM_WEBHOOK_SECRET` (généré), `WAVE_TELEGRAM_BOT_USERNAME`,
  `WAVE_TELEGRAM_ADMIN_IDS`.
- ⚠️ Le token fourni pour MobileCrypto est invalide (401) et son préfixe
  correspond au même bot ID que BattlePoolBot : token du mauvais bot ou
  ancien token révoqué. Webhook Wave **non configuré**.
- En attente : token valide de **@MobileCryptoXBot** (BotFather), à remplacer
  dans `.env.staging`; puis restart + `setWebhook` + test `/start`.

## 71. WaveBot MobileCrypto actif (engine95, 2026-11-19)

- Token corrigé : bot réel **@MobileCrypoXBot** (orthographe BotFather, id 9199860506).
- Secret webhook dédié généré.
- Webhook configuré :
  `https://battlepool.31.187.72.98.sslip.io/api/telegram/wave/webhook`
  → `getWebhookInfo` OK, 0 pending, aucune erreur.
- Route sans secret : 403.
- BattlePoolBot intact : webhook principal inchangé, token principal non écrasé.
- Variables canoniques : `TELEGRAM_MOBILECRYPTO_BOT_TOKEN`,
  `_WEBHOOK_SECRET`, `_BOT_USERNAME`, `_ADMIN_IDS`.
- L’admin doit ouvrir **@MobileCrypoXBot**, faire `/start` pour recevoir les
  notifications, puis `/link CODE`, `/accept`, `/wave`.

## 72. MobileCrypto découplé de BattlePool (engine96, 2026-11-19)

- Suppression de `/link` BattlePool pour WaveBot. Comptes indépendants
  `mobile_crypto_users` : chat Telegram, nom, téléphone, pays, KYC, allowlist,
  wallet Polygon, conditions.
- Onboarding : `/register NOM | 07XXXXXXXX | CI`, `/setwallet 0x...`,
  `/accept`; validation KYC admin via `/admin/wave.html` (section comptes).
- `wave_orders` / `wave_events` recréés avec `mobile_user_id` (plus de FK
  `users`). Anciennes tables vides supprimées.
- Bot : plus aucune dépendance aux comptes BattlePool ; `@MobileCrypoXBot`
  reste indépendant. BattlePoolBot inchangé.
- Test : création compte KYC/allowlist/wallet, achat 300 XOF → 0.4975 USDT,
  vente 1 USDT → 597 XOF ; page admin avec 1 compte + 2 commandes ; données
  de test supprimées.
- KYC MobileCrypto manuel (pas de documents automatisés).
- Tests : headless 24/24, navigateur 23/23.
- KYC admin et comptes MobileCrypto visibles sur `/admin/wave.html`.

## 73. Wave marchand : lien de paiement, numéro personnel retiré

- Les paiements A (Wave → USDT) n’affichent plus de numéro personnel.
- Le bot génère un lien marchand Wave avec le montant exact :
  `https://pay.wave.com/m/M_ci_Orgg9myp4tUY/c/ci/?amount=<XOF>`
- `wave_orders.payment_url` ajouté ; bouton Telegram « Payer avec Wave » +
  « Copier le lien » ; admin page affiche le lien.
- Exemple testé : 300 XOF → lien `...?amount=300`, payout 0.4975 USDT.
- Le numéro personnel `07XXXXXXXX` a été retiré du code/config/docs courants.
- B (USDT → Wave) reste manuel : payout depuis le compte marchand, sans
  numéro plateforme exposé.
- Aucune API/webhook Wave : confirmation toujours manuelle par l’admin.
- ⚠️ Incident Orange Money signalé par l’utilisateur : surveillance, PIN,
  sessions et contact opérateur recommandés.
- Tests headless 24/24, navigateur 23/23.

## 74. MobileCrypto sans KYC + navigation boutons (engine100)

- KYC MobileCrypto retiré du bot/admin : plus de nom, téléphone de profil,
  pays ou validation KYC. Seuls Telegram (chat/username), wallet USDT optionnel
  et acceptation des conditions sont conservés. Le téléphone n’est saisi que
  pour un payout vente.
- Limites test réduites : max **10 USDT / 6 000 XOF** par commande,
  **20 USDT/jour** ; min inchangé (0.5 USDT / 300 XOF).
- Menu à boutons MobileCrypto : Acheter USDT, Vendre USDT, Wallet, Commandes,
  Aide. Montants en boutons inline ; partage du numéro via bouton contact.
- Menu à boutons BattlePoolBot : Acheter AVAXe, Vendre AVAXe, Commandes, Aide.
- Achat Wave : lien marchand exact (`payment_url`) ; vente : payout manuel.
- Tests : headless 24/24, navigateur 23/23 ; webhook Wave OK, 0 pending.
- Toujours manuel, sans API/webhook Wave.

## 75. Boutons MobileCrypto : wallet + preuves de paiement (engine101)

- Bouton **👛 Wallet** avec action **Ajouter/Modifier** ; l’adresse se saisit
  ensuite sans commande `/setwallet`.
- Commande A : boutons **Payer avec Wave**, **✅ J’ai payé**, **Copier le lien**.
- Commande B : bouton **✅ J’ai envoyé les USDT** après dépôt.
- Les boutons passent la commande en `pending_review` ; l’admin vérifie ensuite.
- Menus à boutons conservés pour MobileCrypto et BattlePoolBot.
- Pas de KYC ; limites test 10 USDT/6 000 XOF, 20 USDT/jour.
- Tests : headless 24/24, navigateur 23/23 ; webhook Wave OK.
- Automatisation complète non faite : il faut l’API/webhook Wave Business
  (confirmation paiement + payout) ; le lien marchand seul ne notifie pas le serveur.

## 76. Webhook portail Wave production (engine103)

- Endpoint public : `POST /api/wave/portal-webhook`.
- Vérifications : HMAC SHA-256, timestamp ±300 s, nonce anti-replay,
  `transaction_id` unique, payload minimal sans PII.
- Table `wave_portal_events`, admin `/api/admin/wave/portal-events` + section
  sur `/admin/wave.html` (matched/ambiguous/unmatched/outgoing).
- Matching entrée `in` : commande `buy_usdt` pending_payment, même montant,
  < 30 min. Si unique → `pending_review` + payment_ref ; sinon `ambiguous` /
  `unmatched` et notification admin. Aucun payout USDT automatique.
- Sortie `out` : seulement journalisée ; payout XOF reste manuel.
- Test HMAC production avec montant unique 321 : matched_pending_review,
  commande passée en pending_review, données de test nettoyées.
- Anciennes commandes test 300 en attente expirées pour éviter l’ambiguïté.
- Secret : `WAVE_PORTAL_WEBHOOK_SECRET` (Tailscale config editor 4099).
- Extension : passer endpoint production + secret ; auto-refresh 15 s.
- Tests : headless 24/24, navigateur 23/23.

## 77. Payout USDT automatique Wave (engine104/105)

- Webhook entrant matché unique → statut `approved` si auto activé
  (`WAVE_AUTO_PAYOUT_ENABLED=true`, plafond 10 USDT), sinon pending_review.
- Service `wave-payout` : envoie USDT Polygon depuis la trésorerie seed index
  1 000 000, lock MySQL partagé, idempotence, recovery, notifications.
- Statuts ajoutés : approved, paying, payout_deferred, manual_review.
- Admin events portail + filtres.
- Correctif parse USDT : arrondi à 6 décimales avant `parseUnits` (Wave
  0.53398333 ne peut pas être envoyé tel quel).
- Test réel : commande test 322 XOF → 0.53398333 USDT, tx
  `0x2b3c3da1dae417700970dd8630b05820183813846ddc67d3c6a3d911b9752a8d`,
  statut paid ; trésorerie 1.253017 USDT ; user 0x1F14 2.746983 USDT.
- B (USDT → Wave) reste payout XOF manuel.
- Matching par montant : si plusieurs commandes même montant → ambiguous,
  pas de payout automatique.
- Tests headless 24/24, navigateur 23/23.

## 78. Matching Wave par ID de transaction (engine106)

- Le portail stocke l’événement (`received`) sans matcher par montant.
- Le client clique **✅ J’ai payé**, puis envoie l’ID `T_...` ; le serveur
  vérifie : événement extension existant, direction `in`, montant = commande,
  ID non déjà utilisé.
- Si valide : commande `approved` (auto payout ≤10 USDT) ou `pending_review`;
  événement `matched_auto_payout` / `matched_manual_id`.
- Test ID réel synthétique 323 XOF : webhook `received` → ID → `approved` →
  payout tx `0xb67e06e5cc1a31c0c0ae98971f1093a55f6cd273481ecc4ed9a7fb56dd185c88`
  → `paid`.
- Le matching par montant seul ne déclenche plus de payout.
- Extension inchangée : endpoint production + secret + auto-refresh 15 s.
- Tests headless 24/24, navigateur 23/23.

## 79. Matching automatique par montant unique + ID (engine108)

- Problème : deux commandes même montant → l’ID seul ne sait pas laquelle.
- Solution : chaque achat Wave reçoit un **montant unique** de rapprochement
  (base + 0..99 XOF), lien Wave avec ce montant exact ; l’USDT reste basé sur
  le montant de base.
- Exemple : deux commandes 300 XOF → 301 et 302 XOF ; l’extension envoie le
  montant réel ; le client envoie l’ID ; le serveur retrouve la bonne commande
  par montant+ID, même si le bouton/commande sélectionné est incorrect.
- `base_fiat_amount` ajouté ; lock MySQL pour éviter deux montants identiques ;
  plafond 100 commandes actives par montant de base.
- Test : 301/302, ID fourni avec mauvais contexte de commande → bon match,
  statuts approved/payout_deferred (trésorerie 0.219875 insuffisante).
- Anciennes commandes test 300 expirées ; données de test nettoyées.
- Extension inchangée.
- Tests headless 24/24, navigateur 23/23.

## 80. Correction : matching par ID de transaction (pas montant unique)

- La 79 a été annulée : les montants restent **300/600/etc.**, sans suffixe.
- La clé est l’ID de transaction : l’extension stocke l’événement ; le client
  clique **✅ J’ai payé sur la commande concernée** et envoie l’ID.
- Le serveur rattache l’ID à cette commande, vérifie que l’événement existe et
  que le montant correspond, puis auto-payout.
- Plusieurs commandes de même montant sont possibles : c’est le bouton de la
  commande qui donne le contexte ; `/wavepaid ID` seul est refusé s’il y a
  plusieurs commandes en attente.
- Test : deux commandes 300, deux IDs différents → les deux IDs sont bien
  rattachés à la bonne commande (`T_DUP_ID_A`/`T_DUP_ID_B`), statuts approved ;
  payout différé car trésorerie 0.219875 USDT.
- Données de test nettoyées. Tests headless 24/24, navigateur 23/23.

## 81. Domaines battlepool.online + pingala.tech (engine110)

- DNS : battlepool.online, www, pingala.tech, www, rpc.pingala.tech → 31.187.72.98.
- Caddy : battlepool.online → app 8001 + /app/* Reverb 8008 ;
  www redirect ; pingala.tech/rpc.pingala.tech → 9650 (Host interne
  31.187.72.98.sslip.io accepté par le nœud) ; www redirect.
- TLS Let's Encrypt émis pour battlepool.online.
- Env : APP_URL=https://battlepool.online,
  ONRAMP_TERMS_URL=https://battlepool.online/legal/terms.html,
  PINGALA_RPC_URL/FUJI_RPC_URL=https://pingala.tech/ext/bc/.../rpc.
- Frontend : RPC/preconnect mis à jour ; APP2_URL reste l’ancien app2 sslip.
- Webhooks Telegram principal + Wave déplacés sur battlepool.online.
- Extension Wave v0.3.0 : endpoint production + permission battlepool.online ;
  zip mis à jour.
- Tests : battlepool 200, health 200, terms 200, admin 200, RPC chainId
  0x1869f, www redirects 301, webhooks 0 pending.

## 82. fighter.battlepool.online (engine111)

- DNS `fighter.battlepool.online` → 31.187.72.98.
- Caddy : `/app/*` → Reverb 8009, `/api/*` → API 8000, reste → front 8002 ;
  TLS Let's Encrypt actif.
- BattlePool `APP2_URL` remplacé par `https://fighter.battlepool.online/`.
- Ancien `app2.31...sslip.io` conservé en secours, sans redirection.
- SO Service non modifié.
