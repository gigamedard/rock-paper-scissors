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
