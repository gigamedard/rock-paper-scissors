# BP_SESSION_STATE.md — État de session partagé (HANDOFF AGENT)
> **À tout agent (cloud ou local) reprenant ce repo** : ce fichier est la mémoire
> opérationnelle du projet. Lisez-le AVANT toute modification. Mettez-le à jour
> à la fin de votre session. Les conventions de gouvernance vivent dans
> `.agents/` (local, gitignored) — ce fichier tient lieu de contrat partagé.
>
> Dernière mise à jour : 2026-10-06 (Orchestrateur, session wallet-mobile+bots).
---

## 1. QU'EST-CE QUE BATTLEPOOL (30 secondes)

Jeu de paris PvP pierre-feuille-ciseaux on-chain, chainId **99999** :
- **Laravel 11 + Octane/Swoole** (app), **Node bridge** (blockchain + indexer + batch processor), **Reverb** (WebSockets), **MySQL/Redis**.
- Combats résolus **off-chain** (commit-reveal des pre-moves), stakes/payouts **on-chain** (contrat `Battlepool.sol`).
- Frontend SPA **vanilla JS** (hash router, PAS Inertia pour les pages principales) bundle Vite → `public/build/assets/app.js`.
- Marché P2P PRANA↔TST via `MarketplaceEscrow.sol` avec escrow on-chain + indexeur → webhooks Laravel → DB `trades` → UI.
- Prod : **https://battlepool.31.187.72.98.sslip.io** (Caddy système → loopback 8001 HTTP / 8008 WS).

## 2. CHAÎNE D'INFRA PROD (le schéma mental à avoir)

```
iPhone/Safari ──HTTPS──> Caddy (VPS, SNI battlepool.31.187.72.98.sslip.io)
                            ├── /*       → 127.0.0.1:8001 → battlepool-app:8080 (Octane)
                            └── /app/*   → 127.0.0.1:8008 → battlepool-reverb:8008 (WS)
battlepool-app ──HTTP bridge:3000──> battlepool-bridge (app.js : API + indexer)
battlepool-batch-processor ──HTTP 5s──> app:8080/api/internal/* (matchmaking)
battlepool-bridge / batch-processor ──HTTPS──> RPC PUBLIC Pingala
                            https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc
                            (chainId 99999 = 0x1869f, gas natif TST, AUCUN explorer — JAMAIS de lien d'explorateur dans l'UI)
```
9 containers compose (deploy/docker-compose.prod.yml, gitignored, résumé ici car le fichier n'est pas partagé) :
db (mysql:8.0, vol. battlepool_db_data) · redis · init-db (one-shot migrate+seed) ·
bridge (app.js, port interne 3000) · **batch-processor** (run_batch_processor.js, port interne 3001 — service ajouté 2026-10-06, le compose écrasait le CMD du Dockerfile.worker AVANT : le matchmaking était mort) · app (Octane 8080→8001) · reverb (8008) · queue-worker · queue-worker-limits ×2.
Le validateur L1 Pingala tourne **en dehors** de compose (systemd avalanchego) — **JAMAIS le toucher** (ne pas systemctl/restart/toucher son datadir).

## 3. CONTRATS PINGALA (adresses RÔLÉES, chainId 99999)

| Contrat | Adresse | Rôle/notes |
|---|---|---|
| **Battlepool** | `0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF` | fee 2.5 % (250 bp), securityCoefficient 1000 (mise ×1000 exigée), K=1000, maxBaseBet 100, maxQ 2.0, guard `block.chainid == 99999` |
| **PranaToken (v2)** | `0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B` | ERC20 « PRANA » (label UI) — alias technique `snt` DANS le code : **JAMAIS renommer les clés techniques** (`snt`, `sntToken`, `snt_amount`) |
| **MarketplaceEscrow (v2)** | `0x6C12875a81356d6b3042737C8eb7AAE9B4a088ac` | lié à Prana v2 — `sntToken` IMMUTABLE (redéployer = tout relier) |
| **WAVAX** | `0x0a324396FB40C9cf10f665bFDb21FFceBe66CfD4` | WETH9 passif 1:1 (wrapper TST) |

Rôles Battlepool : **owner/deployer** `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` (clé `PINGALA_DEPLOYER_PK` dans `battlepool/.env` local gitignored — 5.01 TST) · **signer** `0xBe24F203…3De` (200 mTST) · **payoutOperator** `0x9A60327c…663` (~490 mTST — clé du BRIDGE, via `payout_operator_pk` secret docker) · **devWallet** `0x153d0C405…66`. PK **Jamais dans git, jamais dans le bridge pour l'owner** (découplage hot/cold key, timelock 2 jours sur opérations owner sensibles).

**Artefacts exposés au front** via `GET /api/artefacts` (bridge `get-game-config`) — le front lit les adresses de là, PAS de hardcode.

## 4. BOTS AUTOPLAY (users DB 4-7 — le jeu vit en continu)

| User | Wallet | Bet | Funded |
|---|---|---|---|
| 4 | `0x2f97a909233abad1bd04be899f42ccd0a7eebec5` | 0.0004 | 0.4878 TST |
| 5 | `0xdf78912015b2b7c22d8751dc8823d0a49e041221` | 0.0004 | 0.4878 TST |
| 6 | `0xf0e517f765112a525f01a7f33a83555e1a4a80d` | 0.0004 | 0.4878 TST |
| 7 | `0x37fda9082fa111b2832367bc2f568851842acb5f` | 0.0004 | 0.4878 TST |

- Enrolement : `scratch/enroll_bots.cjs` (auth → IPFS CID → `/user/pre-moves` → autoplay_active) ; funding : `scratch/fund_bots_prod.cjs` (deployer envoie 0.56 TST, le bot deposit() 0.5 sur Battlepool, l'indexer sync users.balance en ~10 s).
- **Les PK bots vivent dans `battlepool/.env` local** (`BOT_PK_1..4`) — JAMAIS committées.
- `smart_contracts/simulation_accounts.json` : ADRESSES SEULES committées (la PK n'est pas requise au runtime — le runtime matche sur wallet_address lowercase).
- **L'auto-payout des bots passe par `SessionManager::isBotUser()`** (lit simulation_accounts.json, comparaison lowercase) — tout bot absent du JSON sera traité comme HUMAIN (signature MetaMask au lieu de payOut) → bloqué.
- Martingale : max level 4 (bet ×16) — bot 4/6 déjà montés à 0.0016 en bêta.
- Pool DB `pool.size = [2]` (1v1 actuel) — la prod cible 5 (compte : defaultPoolMaxSize du CONTRAT est 5 ; config/pool.php 2 = pour la simulation). Cohérence à arbitrer si on passe à 5 (les pools on-chain humaines ont maxSize 5).

## 5. FIXES LIVRÉS 2026-10-06 (dans l'ordre, tous déployés et vérifiés)

| Commit | Fix | Preuve |
|---|---|---|
| `6e7c225` | Deeplink mobile natif `core://wc?uri=` (plus de page 404 core.app) | bandeau iOS direct |
| `f1e565f` | requiredNamespaces + fallback annulé sur pagehide | relais inspecté |
| `3a9aa65` | `metadata.redirect.universal` = retour dApp post-approbation | retours utilisateur |
| `fc7308a` | SUPPRESSION du fallback universal (iOS gelait timers — tirait au retour de Core → page 404) | user test |
| `b94ff5f` | **Journal visuel 🐞** (debug-log.js) pop-up + copie presse-papiers iOS | outil actif |
| `bd16659` | **6 fixes wallet mobile** : URI zombie reset / listeners leak / chainId hex-décim / pré-switch + re-open signature / réutilisation de session WC / sérialiseur erreurs | journal 11:23 |
| `c5b1849` | Garde anti-modal AllWallets (AppKit auto-ouvre) + toast signature explicite | capture 11:24 |
| `9dbfce3` | i18n « Switch Network → Pingala → Signer » | ×6 langues |
| `2a5606c` | **Page `#/onboarding`** (QR EIP-3085 + copier/coller + 5 étapes + auto-add) | serveur prod |
| `2f27a38` | **Instrumentation métier** : sérialiseur erreurs, `[Business]`/`[Business-MP]`/`[API-FAIL]`, middleware `BusinessLog` + canal `business` (daily 14 j), canal `deprecations` créé | logs lisibles |
| `68841eb` | `_restoreWcSessionIfNeeded()` (session WC tuée à tort par listeners AppKit sur gel socket iOS — restauration depuis SignClient storage) | « Please call connect() » disparu |
| `b7c7317` | Traceurs `[Web3WC-DIAG]` de mort de session (session_delete wallet vs disconnect dApp vs cleanup interne) | diagnostic |
| `ff06894`+`f97b083` | `ensureWalletVisibleForTx()` (ré-ouverture Core avant CHAQUE tx métier) + **timeouts anti-freeze 120 s/180 s** (bouton figé impossible) | journal 14:07 |
| `1e51cfa`+`b2ed64d` | Indexer heartbeat + timeouts (getBlockNumber 10 s / getLogs 15 s / cycle 20 s / webhooks AbortController 15 s) | hang 25 min résolu |
| `cc68a42` | **`CONFIRMATIONS_REQUIRED=0`** — Pingala ne mine QUE si transactions : bloc de tête orphelin avec confirmations≥1 (OfferCreated 1 h non traitée, bloc 50) | marketplace chaînée ✓ |
| `91dadbd` | **Bots prod** (4 comptes fondés+enrôlés) + **service batch-processor compose** + **migration bet_amount decimal(18,8)** (8,2 tronquait 0.0004→0) + **LeavePoolController** + bouton UI + i18n ×6 | FIGHTS=7 en bêta |
| `d7fa280` | **Airdrop de bienvenue (faucet 50 TST one-shot)** : `FaucetController` + `POST /api/user/faucet-claim`, `ProcessFaucetJob` (flag posé seulement après transfert on-chain réussi), endpoint bridge `/faucet` (raw native transfer via `FAUCET_WALLET_PK`), config `economy.airdrop`, bouton UI + i18n ×6, migration `has_received_airdrop`/`airdrop_tx_hash` | ⚠️ NON DÉPLOYÉ (faucet non financé) |

## 6. BUGS CONNUS NON CORRIGÉS

- **Session WC mourait encore parfois au 2ᵉ flux métier** (traceurs armés, pas de verdict encore) — le RESTAURE + le pré-switch couvrent le cas, mais si un journal montre `session_delete` REÇU DU WALLET, la cause est côté Core (chaîne pas dans leur registre).
- **Marketplace front n'affiche pas toujours la tx** quand elle réussit (retour utilisateur 14:41) : le chaînage indexer→DB→UI marchera via `loadOffers` polling mais le **feedback instantané post-tx** reste à ajouter (attendre tx.miné + refetch au lieu de notification optimiste).
- `[Referral]`/`[Marketplace] Erreur chargement stats` au chargement AVANT auth (401 attendus) — cosmétique, à silencier ou conditionner.
- Wrapper `BP-FATAL` exit du deploy est un **faux positif** (healthcheck compose v5.6) — le déploiement Réussit. Vérifier en lisant « DEPLOY COMPLETE » et `docker exec` du bundle SERVI.
- Tests phpunit : 117 passent ; échecs restants pré-existants hors périmètre (Auth/Profile/Ipfs/SessionPayout + 5 métier).
- **Bouton Leave pool** : NON testé utilisateur (endpoint déployé, UI déployée). À valider par testeur avant de le considérer stable.
- **Airdrop faucet** : implémenté + commité (`d7fa280`) mais **le portefeuille faucet `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` (owner/deployer, 5.01 TST) n'est pas financé à 50 TST**. Sans financement, le transfert natif échoue (`insufficient funds`). À financer AVANT tout test. Le deployer étant déjà l'owner du contrat, réutiliser sa clé comme faucet est un choix à valider (découplage hot/cold recommandé : créer une clé faucet DÉDIÉE).

## 7. PIÈGES OPÉRATOIRE (les morts de la session)

1. **Le code n'est PAS monté en volume** — tout `docker cp` est perdu à la recréation. Modifier la SOURCE puis rebuild image (`run_deploy.ps1` fait tout).
2. **Secrets workflow OBLIGATOIRE** : `node secrets-manager.mjs provision` → deploy → `deprovision`. Jamais de plaintext au repos. `.env.staging` **LF PUR** au VPS (`sed -i 's/\r$//'` fait par le deploy ; un CRLF a coûté une journée : projectId 33 chars → 403 Reown).
3. **PowerShell + ssh + $( )** : les pipes PowerShell corrompent les binaires et réinjectent CRLF ; `$()` interprétés par PowerShell si double-quotes. **Pattern fiable** : script `.sh` LOCAL écrit LF-pur (write tool + `-replace "\r",""`), puis `cmd /c "type script | ssh ""docker exec -i sh -c 'cat > /tmp/x.sh && sh -c `"tr -d 15 < /tmp/x.sh > /tmp/x2.sh`" && sh /tmp/x2.sh'"""` OU `docker exec -i battlepool-app sh -c 'cat > /tmp/x.sh'` avec redirection stdin.
4. **docker exec ne passe pas par l'entrypoint** : secrets/docker non chargés — pour `php artisan` dans le container, exporter d'abord les secrets (pattern mig_in_container.sh).
5. **Le frontend bundle servi fait foi** — grep le `app.js` du container (`docker exec battlepool-app grep -c`) après chaque deploy. `grep -c 'ancienne-string'` = 0 ET `grep -c 'nouvelle-string'` = 1.
6. **Le compose `command:` override le Dockerfile CMD** (app.js au lieu de run_batch_processor.js) — d'où le nouveau service batch-processor. **Ne jamais scaler ce service sans réflexion** (file de nonces sérialisée du bridge : goulot réel).
7. **Laravel config:** cache en prod — après changement de config/settings, `php artisan config:clear` (surtout avec OCTANE : les variables env ne sont pas relues, OCTANE_SERVER cache le config).
8. **Marqueur de build** : le journal mobile affiche `[BOOT] BP-MOBILE-DEPLOY=2` pour confirmer le bundle servi (la confusion « j'ai testé l'ancien bundle » est classique). Changez la valeur à chaque passe visuel.
9. **ChainId 99999 partout** — ne jamais revenir à 1337/31337 (Hardhat legacy). Les scripts de test local utilisent 31337, les scripts prod 99999.
10. **`docker compose up --scale queue-worker-limits=2` imposé** — 3 workers au total (queue-worker + 2 × queue-worker-limits). Le devops runbook détaille.

## 8. FLUX MOBILE CORE (validé iPhone, la séquence exacte)

1. clic **Connect Wallet** → toast « Ouverture de Core Wallet… »
2. URI WC émise ~2 s (poll `display_uri`), deeplink natif `core://wc?uri=…`
3. bandeau Safari « Ouvrir dans Core ? » → **Core** → l'utilisateur approuve (~30 s)
4. session capturée par le poll dApp (`provider.session.namespaces.eip155.accounts`)
5. switch Pingala envoyé (Core le traite au passage) + modal AppKit auto-fermée
6. **Core se ré-ouvre** : bandeau « Switch Network » (1 clic Pingala, Core mémorise) puis **signature**
7. reverify `/api/wallet/verify-signature` → token API → auth:success → Echo connecté
8. (tx métier) : **Core se ré-ouvre AVANT chaque tx** (`ensureWalletVisibleForTx`) — l'utilisateur voit la demande immédiatement, sans freeze

Le bandeau « Switch Network » de Core apparaît jusqu'à ce qu'il ait **mémorisé** Pingala comme chaînan approuvé (une fois). Pas de contournement possible — UX de Core.

## 9. CHANTIERS PROCHAINS (ordre recommandé)

0. **Financer le faucet airdrop** (prérequis test `d7fa280`) : injecter ≥50 TST dans `0xD62909…63Ee` (ou créer une clé faucet dédiée via `FAUCET_WALLET_PK`). Puis déployer `d7fa280` et tester le claim one-shot.
1. **Tester Leave pool** (bug connu n°4) + **feedback marketplace instantané** (bug n°2).
2. **Pool size 5 en prod** (config/pool.php `size [2]` est local-only) + vérifier que les bots/humains partagent bien les pools on-chain (`addSingleUserToPool` maxSize 5 vs pool DB size 2 — cohérence à arbitrer).
3. **Rattrapage UX** : bouton 🐞 à retirer de la prod finale, instrumentation `[Business]` à garder (dérivée en `debugLog` silencieux).
4. **Audit QA indépendant** de la passe complète (obligatoire protocole orchestrateur ≥ 3 itérations) — pôle qa_e2e_regression_agent.
5. **CI/CD** optionnel (l'utilisateur a dit « script local seulement » — ne pas forcer).
6. **Rotation de la clé deployer** recommandée (elle a circulé en clair en conversation).

## 10. COMMANDES UTILES (copier-coller directement)

```powershell
# Déploiement complet (le seul pattern validé)
node secrets-manager.mjs provision
powershell -ExecutionPolicy Bypass -File deploy\run_deploy.ps1 -GitSha <sha_explicit_never_head>
node secrets-manager.mjs deprovision

# Vérifier le bundle servi (OBLIGATOIRE après un deploy front)
ssh -i deploy/id_ed25519_deploy -o BatchMode=yes root@31.187.72.98 "docker exec battlepool-app grep -c '<marker>' /var/www/html/public/build/assets/app.js"

# Logs serveur métier
ssh ... "docker exec battlepool-app tail -50 /var/www/html/storage/logs/business.log"
ssh ... "docker logs battlepool-bridge --tail 50"     # indexer/matchmaking/marketplace events
ssh ... "docker logs battlepool-batch-processor --tail 30"  # matchmaking loop

# État des bots (à distance via ssh + script local PHP LF pur)
# → pattern mig_in_container.sh ; sinon GET /api/health suffit au sanity check

# Vérifier la chaîne (depuis la machine locale)
node scratch\check_bet2.php   # + d'autres helpers scratch/
```

## 11. RÈGLE D'OR POUR L'ORCHESTRATEUR & SOUS-AGENTS

- JAMAIS de `public $queue` dans un Job Laravel (conflit PHP 8 fatal) — `->onQueue('limits')` sur le dispatch.
- JAMAIS de secrets en clair dans le repo (`.agents/` est local, les fichiers .env sont gitignored, les PK vivent dans `battlepool/.env` local + secrets manager).
- JAMAIS de clé technique renommée (`snt`, `sntToken` = alias immuable du code, même si le libellé UI devient PRANA).
- TOUJOURS vérifier le bundle **servi** (grep sur le container) avant de déclarer un deploy front réussi.
- TOUJOURS `node secrets-manager.mjs deprovision` après les opérations.
- TOUJOURS dérouler un runbook de rollback si un deploy échoue (dérive vers runbook §5).
- **Ce fichier est la seule passerelle de connaissance** entre agent cloud et local — mettre à jour §5/§6/§7/§9 à CHAQUE modification non triviale.

—
*Généré par l'Orchestrateur BattlePool, 2026-10-06.*