# 🛡️ RAPPORT D'AUDIT QA — Migration Pingala Chain (chainId 99999)

**Auditeur** : bp-qa (Lead QA BattlePool) — auditeur indépendant
**Date** : 04/10/2026
**Périmètre** : Migration du stack depuis Hardhat local (chainId 31337) vers la L1 souveraine Pingala Chain (chainId 99999)
**Verdict** : **REJETÉ** — 2 blocants sécurité/économie (S1+E1, S3). **Aucune régression fonctionnelle** : toutes les couches de la migration sont conformes.

---

## 1. Checks par couche (preuves)

### 1.1 Contrats (`battlepool/`) — ✅ CONFORME

| Check | Résultat | Preuve |
|---|---|---|
| `PranaToken.sol` (nouveau) | ✅ | `ERC20("Prana", "PRANA")`, `Ownable(INITIAL_OWNER_ADDRESS)`, mint 1M à `0x8C3229EC…43D` codé en dur (l.28-38). Comportement Phase A conservé à l'identique, documenté en tête. |
| `WAVAX.sol` (nouveau) | ✅ | Pattern WETH9 complet (`deposit`/`withdraw`/`redeem`/`receive`), **CEI correct** (`_burn` AVANT `.call{value}`, l.50-55), **pas de Ownable** (wrap passif — décision respectée), events `Deposit`/`Withdrawal`. `require(success)` volontairement plus strict que WETH9 (justifié : pas de multisig en beta). |
| `Battlepool.sol` garde chainId | ✅ | `require(block.chainid == 99999, …)` sur `testEmitPoolEmitted`. Grep repo : **0 appelant** de cette fonction → zéro impact runtime. |
| Tests Hardhat | ✅ | `npx hardhat test` → **50 passing (5s)**, 0 failure, 0 error (Battlepool 14, PranaToken 7, Fees/Payout 3, Stagnant 11, UserLimits 12, WAVAX 7). |

### 1.2 Déploiement réel — ✅ CONFORME (modulo issue S1)

`battlepool/pingala_deployments.json` — **vérifié on-chain réellement** (RPC public, lecture seule, script scratch éphémère) :

| Élément | Attendu | Observé on-chain | Statut |
|---|---|---|---|
| `eth_chainId` | 0x1869f (99999) | **0x1869f** | ✅ |
| game | `0x0790C2e4…62EF` | code OK (23 440 bytes) | ✅ |
| Prana | `0x4D3f9aAC…90B` | code OK, `name()="Prana"`, `symbol()="PRANA"` | ✅ |
| marketplace | `0x4Da46c76…64De` | code OK (3 660 bytes), `owner()=deployer`, `feePercentage()=1` | ✅ |
| wavax | `0x0a324396…fD4` | code OK (2 578 bytes), `symbol()="WAVAX"`, `totalSupply()=0` | ✅ |
| `Battlepool.signer()` | `0xBe24F203…3De` | **identique** | ✅ |
| `Battlepool.payoutOperator()` | `0x9A60327c…663` | **identique** | ✅ |
| `Battlepool.devWallet()` | `0x153d0C40…66` | **identique** | ✅ |
| `feeBasisPoints` | 250 | **250** | ✅ |
| `securityCoefficient` | 1000 | **1000** | ✅ |
| `defaultMaxBaseBet` | 100 | **100.0** | ✅ |
| `defaultMaxQ` | 2.0 | **2.0** | ✅ |
| `defaultMinCooldown` | 86400 (défaut non surchargé) | **86400** | ✅ |
| Balances | deployer ~8.9 / signer 0.2 / payout 0.5 / dev 0.2 | **8.90 / 0.2 / 0.5 / 0.2** | ✅ |
| Supply PRANA | 1M chez `0x8C32…43D` | `balanceOf = 1 000 000.0`, `owner() = 0x8C32…43D`, **balance native de cette adresse = 0.0** | ⚠️ → issue S1 |

Ordre de déploiement réel (nonce des 4 `deployTxHash`, tous `status=1`, `from=0xD629…63Ee`) : battlepool(nonce 3, block 14) → prana(nonce 9, block 20) → marketplace(nonce 10, block 21) → **wavax(nonce 11, block 22) = DERNIER** ✅ — règle d'or « nouveau contrat en dernier » respectée in situ. `initializeRoles` à 4 args appelé (rôles on-chain = attendus). GasLimit dynamique plafonné au block gas limit réel (`full_deploy_pingala.js` l.219-224 : STOP si dépassement). PranaToken/Marketplace/WAVAX déployés avec marge gas +300k/+1.5M.

### 1.3 Sécurité secrets — ✅ CONFORME en git (2 réserves : S2, S3)

- `git status --short` : aucun fichier PK tracké. `git check-ignore -v battlepool/.env` → `battlepool/.gitignore:2:.env` ✅.
- `pingala_deployments.json` + `.rehearsal.json` : scan 64-hex → **4 `deployTxHash` uniquement, aucune PK** ✅ (vérifié par masquage contextuel).
- `.env.staging` (non tracké) : **0 hex-64 → aucune PK** ✅ (adresses, CHAIN_ID, RPC uniquement).
- Fichiers trackés contenant "PK" : `.env.staging.example` (valeurs vides) et `battlepool/.env.example` (placeholders) — aucune valeur réelle.
- Diff complet (460 lignes `+`) scanné : aucune PK ; seul placeholder `***REMOVED***` (phpunit.xml l.34, pré-existant, 13 chars).
- **Dérivation des secrets Docker** (workflow provision→deprovision respecté) :
  - `signer_wallet_pk` → **`0xBe24F203E4e35A79E242d77c3710cE608C16a3De`** ✅ attendu
  - `payout_operator_pk` → **`0x9A60327ce58A94a411987119047A75A8F1076663`** ✅ attendu
  - `marketplace_wallet_pk` → `0x70997970C51812dc3A010C7d01b50e0d17dc79C8` = **account Hardhat #1 PUBLIC** → issue S3
  - `game_wallet_pk` → `0x4B35f60D18F2Caf3534bEcE3Df38FBc02c818E63` (PK unique, hors bridge par design)
  - Vérification croisée : les PK des 4 wallets Pingala (deployer/signer/payout/dev) ne sont dans **aucun des 20 comptes canoniques du mnémonique Hardhat** (script de dérivation local, adresses seules affichées).
- **PK Pingala jamais présentes** dans les fichiers trackés ni untracked : grep des 4 préfixes (`0xc3e90d`, `0x8b3e58`, `0x072321`, `0x574e5b`) → 0 hit hors `battlepool/.env` (ignoré).
- **Restauration** : `node secrets-manager.mjs deprovision` exécuté après la série → `.secrets/` ne contient plus que 11 fichiers `.enc` ✅.

### 1.4 Bridge / Indexeur — ✅ CONFORME (réserves B1, B2)

- `smart_contracts/config.js` : 4 adresses = `pingala_deployments.json` (l.208/2017/2389/2795) ; `PINGALA_RPC_URL` défaut = RPC public Pingala ; `export const CHAIN_ID` = env ou **99999** (l.162) ; alias legacy `FUJI_RPC_URL`/`LOCAL_HARDHAT_URL` pointent Pingala (documenté, ~25 scripts) ; clé `snt` conservée comme alias (commentaire PranaToken l.19-23 + `.env.staging.example`) ; ABI Prana (`ERC20Invalid*` OZ v5) + section `wavax` (l.2793-2795, ABI deposit/withdraw/redeem/Deposit/Withdrawal).
- `smart_contracts/indexer/config.js` : `confirmationsRequired` défaut **1** (l.38) + commentaire reorg Pingala à jour (l.34-37).
- `smart_contracts/app.js` : diagnostic `getNetwork()` **dynamique** au boot (l.615-626), warn non-fatal si ≠ `CHAIN_ID` — aucun chainId dur dans le code.
- Logs bridge réels : `🔗 [CHAIN] … chainId (dynamique): 99999`, `✅ [BRIDGE] ABIs validées (game/marketplace/snt …)`, `[Indexer] game/marketplace/snt … reprend au bloc 0`, `[Indexer] game synced blocks 1 -> 21 | Events processed: 2` → **indexation fonctionnelle sur Pingala réelle**.
- Statut : `Up 48 min (healthy)`, `RestartCount=2` (crashs transitoires au boot : ECONNREFUSED DB puis Access denied `rps_app` avant création du user par init-db — race de démarrage connue, aggravée par le reset DB). Appels internes bridge→Laravel vérifiés fonctionnels (`curl http://app:8080/api/health` depuis le bridge → 200 OK).

### 1.5 Backend Laravel — ✅ CONFORME

- `.env.staging` (non tracké, scanné sans PK leakée) : `BATTLEPOOL_ADDRESS=0x0790…62EF`, `SNT_TOKEN_ADDRESS=0x4D3f…90B`, `MARKETPLACE_ESCROW_ADDRESS=0x4Da4…64De`, `WAVAX_ADDRESS=0x0a32…fD4`, `CHAIN_ID=99999`, `PINGALA_RPC_URL=` RPC public, `FUJI_RPC_URL` même valeur (alias documenté).
- `.env.staging.example` (tracké) : `CHAIN_ID=99999`, sections Pingala documentées, `DEMO_TOKEN_ADDRESS` retiré du diff ✅, aucune PK ✅.
- `GET http://127.0.0.1:8001/api/health` → `{"status":"OK","database":"OK","cache":"OK","bridge_last_ping":"NONE"}` ✅
- `GET http://127.0.0.1:8001/api/stats/public` → **`on_chain.degraded=false`** ✅ (Web3Helper→bridge OK), pools.active=0, players.total=1, fights.total=0.
- DB reset vérifié : **users=1 (admin), pools=0, fights=0, cards_actives=17** ✅. NB : le flag "en pool" du projet est `users.status='in_pool'` (pas de colonne `in_pool`) — DB reset ⇒ aucun user bloqué.
- Files d'attente : `queues:default`=0, `queues:limits`=0. 4 dispatches `SyncUserLimitsJob->onQueue('limits')` (ShopController l.123/244, VerifyCardPurchaseJob l.99, SessionManager l.320). **Zéro `public $queue` dans `app/`** (règle d'or §2 AGENTS.md).

### 1.6 Frontend (bundle servi) — ✅ CONFORME

Bundle servi `http://127.0.0.1:8001/build/assets/app.js` (911 337 octets, mtime container 17:22:24) :

| Check | Attendu | Observé |
|---|---|---|
| `0x1869f` | présent | **1 occurrence** ✅ |
| `Pingala` | présent | **12 occurrences** ✅ |
| `ensurePingalaNetwork` | présent | **1** ✅ |
| `0x539` | absent | **0** ✅ |
| `subnets-test.avax.network` | absent | **0** ✅ |
| Ancienne adresse PAYOUT | absente | **0** (seule `0x9A60…6663` actuelle apparaît 1×) ✅ |
| `FCFA` / mises en FCFA | absents | **0** ✅ |
| Mises AVAX | présentes | `public/index.html` l.130-131 : `0.0004 AVAX` / `0.15 AVAX` ✅ |
| `TST`/`PRANA` | présents | TST×2, PRANA×3 ✅ |
| `HARDHAT_RPC 127.0.0.1:8546` + mock wallet #0 | à arbitrer | toujours dans `public/js/config.js` (hors bundle) — **noté non bloquant (F1)** |

Fraîcheur : sources `web3-core.js` 17:18 / build servi 17:22 → le bundle servi contient bien les sources migrées (règle `npm run build` respectée). NB : `public/build/assets/app.js` local (17:19:37) ≠ servi (17:22:24) en octets, mais contenu fonctionnel identique aux checks clés → noté F3.

### 1.7 Docker compose — ✅ CONFORME

- Service `blockchain` **retiré** (diff : `-  blockchain:` + commentaire de remplacement l.84-92) ; `depends_on: blockchain` du bridge supprimé. `docker compose config --services` → `db redis bridge init-db app queue-worker queue-worker-limits reverb` : **aucun `blockchain`** ✅.
- Secrets bridge montés = `payout_operator_pk`, `marketplace_wallet_pk`, `signer_wallet_pk` → dérivations = nouveaux wallets Pingala ✅ (§1.3).
- `docker ps -a` : app, reverb, bridge, db, redis `healthy`/Up ; init-db `Exited (0)` (normal) ; queue-worker-1 + queue-worker-limits-1/2 `healthy` ; **aucun container `blockchain` ni `worker`** (retirés) ✅. Containers d'autres projets (veritas_*, web3_combat_*) hors périmètre.

### 1.8 Tests PHPUnit — ✅ AUCUNE RÉGRESSION

- Run courant : `C:\xampp\php\php.exe vendor\bin\phpunit --no-coverage` → **Tests: 117, Assertions: 404, Errors: 26, Failures: 5, Risky: 2**.
- Run de référence HEAD (worktree git temporaire `$TEMP\opencode\qa_head` + vendor copié, **working tree intact**, worktree supprimé après) : **strictement identique** — mêmes 26 erreurs (Auth×17, Example, Ipfs, Profile×5, SessionPayout), mêmes 5 failures (`EndToEndGameFlowTest`, `InfluencerStatsTest` 405≠200, `PoolAutoMatchControllerTest::testHandlePoolEmitedEvent` stopped≠available, `PoolEmittedFlowTest` pool vide, `TransactionQueueTest` 405≠202), mêmes 2 risky.
- → **100 % pré-existants à HEAD**, aucun dû à la migration Pingala. Les 5 failures sont des tests legacy (routes/comportements anciens) à re-scoper hors périmètre.

---

## 2. Issues (severity / fichier / ligne / message)

### 🔴 BLOCKERS (2)

**S1+E1 — `battlepool/contracts/PranaToken.sol:28,38` + état on-chain — Ownership & supply PRANA orphelins**
1 000 000 PRANA (`balanceOf` on-chain vérifié = 1 000 000.0) + `Ownable` détenus par `0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D` : **ni PK connue (aucune PK dérivable dans le repo ni l'historique — scanné), ni gas (balance native = 0.0 on-chain)**. Conséquences Economy :
- l'approvisionnement de l'écosystème (achats de cartes en PRANA via bridge `/verify-snt-transfer`, récepteur = `PAYOUT_OPERATOR 0x9A60…`, app.js:390) **ne peut pas être alimenté** : l'économie est bloquée ;
- aucun mint/transfer administratif possible sans cette adresse ;
- `pingala_deployments.json:50` documente le point, décision pendante.
**Arbitrage requis (au choix utilisateur, correction atomique)** :
(a) si la PK existe hors-repo : fund ~0.05 TST (gas) → `transferOwnership(0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee)` depuis cette adresse, puis distribuer les PRANA vers wallets opé ;
(b) si la PK est perdue : **redéployer PranaToken** avec `INITIAL_OWNER_ADDRESS = 0xD629…63Ee` (décision « renommage au redéploy » s'y prête), puis resync `config.js` ABIs + `.env.staging` + indexer (reset bloc 0) + frontend.

**S3 — `docker-compose.yml:239` + `.secrets/marketplace_wallet_pk` — PK publique Hardhat montée en secret**
Dérivation vérifiée : `marketplace_wallet_pk` → **`0x70997970C51812dc3A010C7d01b50e0d17dc79C8` = account #1 du mnémonique Hardhat public** (`test test … junk`). La clé est dans le repo (`smart_contracts/simulate.js:73` entre autres). Non utilisée au runtime du bridge (app.js l.16 l'importe sans l'employer) mais requise fail-fast par `config.js:190` et **fonds potentiellement manipulables par quiconque lit le repo** (scripts `server.js:59`/`listener3.js` l'utilisent).
**Correction** : générer une PK unique (`ethers.Wallet.createRandom()`), provisioner en `.secrets/marketplace_wallet_pk` (+`.enc`), rotate l'adresse si elle est référencée quelque part (funder scripts), deprovision après `docker compose up`.

### 🟡 MAJEURES (non bloquants pour le verdict)

**S2 — `battlepool/.env:16,19-21` — 4 PK Pingala en plaintext sur disque** (gitignored, jamais dans git — vérifié). Le standard du projet = secrets chiffrés AES-256-GCM (`secrets-manager.mjs`). Migrer les 4 `PINGALA_*_PK` vers `.secrets/*.enc` ; `hardhat.config.js:25-33` (`readSecretFile` → `/run/secrets/pingala_deployer_pk`) et `full_deploy_pingala.js:69-75` (`readSecret`) lisent déjà la mécanique — provision/deprovision suffisent.

**B1 — `smart_contracts/app.js:445` — `/admin/contract-stats` sans garde `x-internal-secret`**
Test réel depuis le container : `GET /admin/contract-stats` sans header → **200** `{"houseBalance":"0.0","contractBalance":"0.0"}`. Atténuant : port 3000 **non publié** au host (compose l.93-120, aucune section `ports:`) → exploitation requiert un accès au réseau Docker interne. Autres endpoints : `/verify-snt-transfer` → 403 sans secret ✅ (garde l.353-358), `/sendPayment`/`/setUserLimits`/`/generate-signature` → 400 param manquant (⚠️ pas de 403 : ils traitent le body AVANT l'auth — vérifier que le traitement sans auth n'a pas d'effet de bord ; 400 = requête rejetée, pas d'effet observé).
**Correction** : dupliquer le pattern l.353-358 (`x-internal-secret` → 403) en tête de `/admin/contract-stats`, et déplacer la garde AVANT le parsing dans les 3 POST listés.

**B2 — Bridge boot races (restarts=2)** : `ECONNREFUSED …:3306` puis `Access denied for 'rps_app'` au 1er boot (init-db crée le user `rps_app` après le 1er `initSchema` du bridge). Absorbé par `restart: unless-stopped`, état final healthy + indexer sync 1→21. Suggestion : retry/backoff sur `indexer/db.js:initSchema` (le backoff RPC existe déjà côté indexer). Tracker en follow-up.

**E2 — Wallets de rôle faiblement fundés** : signer 0.2 / payout 0.5 / dev 0.2 (on-chain vérifié). Le payout est le plus consommateur (txs claim/exchange) ; prévoir refill planifié depuis le deployer (balance 8.90). Conforme au brief mais à opérer.

### 🟢 NOTÉS (non bloquants)

- **F1** — `public/js/config.js` : `HARDHAT_RPC http://127.0.0.1:8546` + mock wallet #0 conservés (hors bundle Vite, servi en l'état) — arbitrage front requis, non bloquant.
- **F2** — `proxy/nginx.conf:100-101` + l.11 : route `/rpc/` → `host.docker.internal:8546` **morte** (Hardhat éteint) pour MetaMask Mobile via `bp-proxy` (container hors compose). À retirer en phase F.
- **F3** — `public/build/assets/app.js` local (17:19:37) ≠ bundle servi (17:22:24) : contenu métier identique (checks §1.6), minification différente. Resynchroniser `public/build` au prochain `npm run build` + copie container pour que l'artefact local reflète le servi.
- **F4** — `.env.staging:80` : `DEMO_TOKEN_ADDRESS` résiduel — grep `*.php` = 0 usage. Nettoyage cosmétique.
- **F5** — `phpunit.xml:34` : `GAME_WALLET_PK=***REMOVED***` placeholder littéral (pré-existant).
- **F6** — `smart_contracts/fund_snt.cjs` modifié (11 lignes) : relire en follow-up (non bloquant).
- **F7** — Repos tracking : `.env.staging.example`/`phpunit.xml` placeholders OK ; scan historique des commits contenant `0x8C32…43D` : co-localisations avec des PK **Hardhat de simulation** (`simulate.js`, `simulation_accounts.json`, `bridge_log.txt`, build-info chain-43113) — PK de test publiques par nature, hygiène à retailler hors migration (les PK réelles n'apparaissent jamais).
- **F8** — Les PK Pingala utilisées (deployer/signer/payout/dev) ne sont dans **aucun des 20 comptes canoniques Hardhat** (vérifié par dérivation) et aucun des 4 préfixes PK n'apparaît hors `battlepool/.env`.

---

## 3. corrections_required (atomiques)

1. **[S1+E1] Ownership/supply PranaToken** — `battlepool/contracts/PranaToken.sol` l.28 : soit (a) fund gas `0x8C32…43D` + `transferOwnership(0xD629…63Ee)` depuis cette adresse, soit (b) **redéployer PranaToken** avec `INITIAL_OWNER_ADDRESS = 0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` (si PK perdue) + resync `smart_contracts/config.js` (adresse snt + ABI) + `.env.staging` (`SNT_TOKEN_ADDRESS`) + indexer reset bloc 0 + rebuild bundle si adresse exposée front.
2. **[S3] `marketplace_wallet_pk`** — générer PK unique, `secrets-manager.mjs provision` → `.secrets/marketplace_wallet_pk` + `.enc`, `docker compose up -d bridge`, puis `deprovision`. Vérifier qu'aucune référence d'adresse ne pointe `0x7099…79C8`.
3. **[B1] `smart_contracts/app.js:445`** — ajouter la garde `x-internal-secret` (copie des l.353-358) sur `/admin/contract-stats` ; déplacer la garde avant parsing sur `/sendPayment`/`/setUserLimits`/`/generate-signature` (403 AVANT 400).
4. **[S2] `battlepool/.env`** — migrer les 4 `PINGALA_*_PK` vers `.secrets/*.enc` (provision/deprovision), supprimer le plaintext du disque.
5. **[E2] Refill wallets de rôle** — opéré par devops/blockchain : `deployer → signer/payout/dev` (propositions : payout +1.0 pour absorber les txs claim/exchange).
6. **[F2] `proxy/nginx.conf:95-105`** — retirer la route `/rpc/` (8546).
7. **[F3]** — au prochain `npm run build` : copier `public/build` dans le container (`docker cp` puis rebuild image selon règle §3 AGENTS.md) pour aligner local = servi.

*(aucune de ces corrections n'a été appliquée par QA — rôle audit only)*

---

## 4. Cas limites du protocole QA (statut)

| Cas | Statut |
|---|---|
| user `in_pool` bloqué | ✅ N/A — DB reset (`users=1` admin, status par défaut), mécanique de recovery `SessionManager` intacte (non touchée par ce diff) |
| fight sans pre-move | N/A (DB vide) — code de garde intact |
| claim avec solde on-chain désynchronisé | Couche signature vérifiée : `Battlepool.signer()` on-chain = `0xBe24…3De` = dérivation du secret `signer_wallet_pk` ✅. Flux claim complet non re-testé E2E (aucune pool active) — à retester après arbitrage S1 |
| queue `default` vs `limits` | ✅ LLEN 0/0 ; 4 dispatches `onQueue('limits')` ; zéro `public $queue` |
| reset blockchain/nonce MetaMask | chainId 99999 cohérent partout (RPC, bridge dynamique, bundle front). MetaMask : ajouter le réseau Pingala + clear cache avant toute tx (AGENTS.md §6/§9 transposés à la nouvelle chaîne) |

---

## 5. Commandes & mesures (preuves brutes)

- `git status --short` (1 deleted SNTToken, ~25 modified, ~20 untracked — aucun `.env` ni PK trackés) ; `git diff --stat` → 26 files, +937/−221
- `git check-ignore -v battlepool/.env` → `battlepool/.gitignore:2:.env battlepool/.env`
- Scans PK masqués (aucune affichée en clair dans ce rapport) : `0x[0-9a-fA-F]{64}` sur `.env.staging` (=0), `pingala_deployments(.rehearsal).json` (=4, tous `deployTxHash`), tracké (`git grep -E '0x[0-9a-fA-F]{64}'` → PKs Hardhat de test connues uniquement), 4 préfixes PK Pingala → 0 hit hors `battlepool/.env`
- `node secrets-manager.mjs provision` → dérivation adresses (node `ethers.Wallet`) → `node secrets-manager.mjs deprovision` ✅ (`.secrets/` final : 11 `.enc`)
- RPC lecture seule (script `scratch/qa_pingala_audit.mjs`, supprimé après) : chainId 0x1869f, 4 codes contrats, 10 appels `eth_call` (rôles/params/symbol/supply/balances), 5 balances natives
- Nonces des 4 deployTxHash : battlepool=3, prana=9, marketplace=10, wavax=11 (ordre respecté, wavax dernier)
- `npx hardhat test` (workdir `battlepool/`) → **50 passing (5s)**
- `C:\xampp\php\php.exe vendor\bin\phpunit --no-coverage` → 117 tests / 404 assertions / 26 E / 5 F / 2 R — **identique au run HEAD** (worktree temporaire, supprimé)
- `docker logs rock-paper-scissors-bridge-1` : chainId dynamique 99999, ABIs validées, indexer bloc 0, sync 1→21, restarts=2, health=healthy
- `docker compose config --services` / `docker compose ps -a` / `docker inspect` (mounts, health)
- HTTP : `/api/health` (OK), `/api/stats/public` (`degraded=false`), `/build/assets/app.js` (comptages de chaînes), endpoints bridge internes (fetch node depuis container)
- `redis-cli LLEN queues:default` → 0 ; `LLEN queues:limits` → 0

## 6. Verdict

**REJETÉ** — non pour des régressions (aucune couche ne régresse : Hardhat 50/50, PHPUnit strictement = HEAD, chainId 99999 cohérent bout-en-bout, compose propre, secrets dérivés aux adresses attendues, indexer indexe la vraie chaîne) mais pour **2 blocants sécurité/économie** empêchant la mise en service de l'économie PRANA :

1. **S1+E1** — supply 1M PRANA + Ownable sur `0x8C32…43D` sans PK connue ni gas : économie (achats PRANA, frais, TVL) inopérable sans arbitrage (transferOwnership+funding ou redeploy).
2. **S3** — `marketplace_wallet_pk` = PK Hardhat publique montée en secret Docker : surface d'attaque inacceptable même si inutilisée au runtime bridge.

Les points B1 (garde contract-stats), S2 (plaintext .env), B2 (boot races), E2 (refills) et F1-F8 sont majeurs/notés — non bloquants pour ce verdict parce que le port bridge n'est pas publié et que les workflows beta n'en dépendent pas immédiatement, mais à intégrer dans l'itération suivante.

**corrections_required** : §3 — 7 corrections atomiques (fichier + ligne + correction).

*Rapport préparé par bp-qa. Aucune modification de code applicatif ; scripts d'audit éphémères créés puis supprimés (scratch/ nettoyé) ; secrets restaurés en état chiffré (deprovision exécuté) ; worktree HEAD temporaire supprimé ; aucune tx envoyée on-chain.*
---

## ANNEXE — Itération 2 (re-évaluation bp-qa, lecture seule, 2026-10-04)

### Correctifs vérifiés (preuves)
- **S1+E1 / S3 / Frontend v2** : voir checklist ci-dessous — tous vérifiés ON-CHAIN et sur le service réel.
- Scripts d'audit : scratch/audit_pingala_it2.cjs + audit_bp_params.cjs + audit_pool_residue.cjs (supprimés après run ; aucune PK embarquée, lecture runtime /run/secrets uniquement).

### Checklist itération 2 (toutes mesures brutes)
| # | Vérification | Résultat | Preuve |
|---|---|---|---|
| 1 | eth_chainId (RPC public) | 99999 (0x1869f) ✅ | audit_pingala_it2.cjs |
| 1 | Balances natives deployer/payout/dev/signer | 8.8596 / 0.5 / 0.2 / 0.2 TST ✅ | audit_pingala_it2.cjs |
| 1 | PRANA v2 (0x38ef…903B) symbol/supply/owner | PRANA / 1 000 000 / deployer ✅ | idem |
| 1 | PRANA balances payout/dev/deployer | 100 000 / 50 000 / 850 000 ✅ | idem |
| 1 | marketplace v2 sntToken → PRANA v2 | 0x38ef…903B ✅ | idem |
| 1 | battlepool.owner = deployer, rôles payout/signer/dev | v2 & inchangés ✅ | audit_bp_params.cjs |
| 1 | fee=250, coeff=1000, maxBaseBet=100, maxQ=2, cooldown=86400, poolMax=5 | inchangés ✅ | idem |
| 1 | WAVAX invariant 1:1 (balanceOf(WAVAX)==totalSupply) | 0/0 ✅ (wrapper passif WETH9, pas de totalAssets — design conforme) | audit_pingala_it2.cjs |
| 1 | nextPoolId=1, contractBalance=0, aucun rôle inPool/dues | aucun fonds piégé ✅ | audit_pool_residue.cjs |
| 2 | PK en clair dans fichiers trackés | AUCUNE PK Pingala (reliques Hardhat 31337/locales qualifiées) ✅ | git grep 64-hex |
| 2 | marketplace_wallet_pk → adresse | 0xaa5F3C4B29599083245B3D7F9C90842dd8A12cc5 ≠ 0x7099…79C8 ✅ | dérivation ethers in-container |
| 2 | .env.staging non tracké, adresses v2, aucune PK | ✅ | git check-ignore, fichier |
| 2 | provision→ops→deprovision | ✅ 10 plaintext supprimés, .enc intacts | secrets-manager |
| 3 | Bridge healthy, chainId dynamique 99999, indexer v2 bloc 25 | ✅ | docker logs bridge |
| 3 | /get-game-config 200, sans ReferenceError, chain_id/wavax/snt/marketplace v2 | ✅ (abi 108) | in-container fetch |
| 4 | /api/health OK ; /api/stats/public degraded=false | ✅ | curl host |
| 4 | DB reset 1 user/0 fight/17 cards | ✅ | db_counts.sh |
| 5 | Bundle servi (912 010 o) : add-network-btn =1, Pingala =15, 0x1869f =1, 99999 =3, ZERO 0x539/31337/0x4D3f…/0x4Da4… | ✅ | HTTP réel container |
| 5 | HTML servi : add-network-btn =1, ZERO anciennes adresses | ✅ | HTTP réel |
| 6 | `npx hardhat test` | 50 passing / 0 failing ✅ | battlepool |
| 6 | PHPUnit courant (APP_KEY éphémère) vs HEAD (APP_KEY éphémère) | 117 t, 1 err + 13 fail IDENTIQUES (diff vide) ✅ | $TEMP/phpunit_*.txt |

### Issues itération 2
| ID | Sévérité | Fichier:ligne | Constat |
|---|---|---|---|
| F9 | **MAJEUR (bloquant beta UX, non sécurité)** | resources/js/modules/stats.js:210,212 | `fmtSnt()` appelée mais plus définie (renommée `fmtPrana2`) → ReferenceError au 1er fetch /api/stats/public réussi : compteurs `stats-mp-p2p-volume`/`stats-mp-avg-24h` figés à '--'. |
| F10 | MAJEUR (ticker mort) | resources/js/modules/stats.js:242 | `fmtAvax4()` appelée mais renommée `fmtTst4` → ReferenceError dès le 1er FightResult (try/catch ticker → ticker muet, console.warn). |
| N1 | mineure | resources/js/modules/game.js:13 | fallback hardhat `0x5FbDB…` toujours présent en SOURCE (écrasé par /api/artefacts au runtime ; bundle servi identique) — à retirer pour propreté. |
| N2 | mineure (qualifiée) | smart_contracts/fund_snt.cjs:13 + exploit_*.cjs trackés | reliques locales chainId 31337/PK hardhat+ad-hoc, AUCUNE dérive vers PK Pingala (dérivation ethers vérifiée) — hygiène git à faire au merge. |
| N3 | mineure | battlepool/pingala_deployments.json:6 | deployedAt affiché 18:28Z alors que l'image bridge rebuildée est 18:30Z ; sans objet (redeploy script ≠ image). |

### Corrections_required (atomiques)
1. stats.js:210 — `fmtSnt(` → `fmtPrana2(` ; stats.js:212 — idem ; stats.js:242 — `fmtAvax4(` → `fmtTst4(`. Puis `npm run build` + rebuild image app + recréation container + re-test bundle servi (grep fmtSnt/fmtAvax4 = 0 après minification).

### Verdict itération 2
**REJETÉ** (périmètre strict) : les 3 blocants de l'itération 1 sont CORRIGÉS et prouvés (S1+E1 : supply 1M PRANA/owner=deployer/funding on-chain vérifié ; S3 : marketplace_wallet_pk → 0xaa5F3C4B…, aucune PK dans git). Zéro régression backend/contrat (Hardhat 50/50 ; PHPUnit courant ≡ HEAD à APP_KEY égale, diff des listes vide ; chainId 99999 bout-en-bout ; indexer v2 bloc 25 ; WAVAX 1:1 ; aucun fonds piégé). Toutefois le livrable « Frontend v2 » est porteur d'un **impact réel découvert** (règle « non bloquant sauf impact réel ») :
- F9/F10 : `renderStatsBar()` lève `ReferenceError: fmtSnt is not defined` sur le premier fetch `/api/stats/public` réussi (stats-mp-p2p-volume et stats-mp-avg-24h restent '--') et `renderFightTicker()` meurt au premier évènement `game:fightResult` (`fmtAvax4 is not defined`) — dans le bundle servi mesuré (fmtSnt×2, fmtAvax4×1 appelés ; fmtPrana2/fmtTst4 = 0). Le correctif d'unités v2 a renommé les définitions sans renommer 3 appels.

**corrections_required** : 1 unique — stats.js:210 `fmtSnt(` → `fmtPrana2(`, :212 idem, :242 `fmtAvax4(` → `fmtTst4(` + rebuild (`npm run build`, rebuild image app, recréation container, vérif bundle servi sans fmtSnt/fmtAvax4). Itération 3 autorisée (limite de 3 non atteinte).
