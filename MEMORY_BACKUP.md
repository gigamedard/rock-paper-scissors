# MÉMOIRE / HANDOVER — BattlePool (à lire en premier si reprise de session)

> Sauvegarde de contexte. Si la session/le contexte se termine, **lire ce fichier
> en entier** avant de reprendre. Il résume l'objectif, l'architecture, l'état
> déployé, les bugs corrigés, les mesures et les prochaines étapes.

## 0. Reprise express (TL;DR)
- Branche : **`feat/session-recovery`** ; images **engine37** (app, queue, scheduler, engine).
- VPS : `/opt/battlepool` (compose prod) ; code workspace : `/root/workspace/rock-paper-scissors`.
- **Moteur actif** : DB (`battlepool-engine` + `battlepool-batch-processor`) ; `sim-memory` **arrêté**.
  Cohorte live = **1000 bots autoplay** remis à plat avec les **nouveaux paramètres du test**
  (T=1.2, security=100, frais 0.5 %).
- **Dernier test (2026-11-11)** : temps de session mesuré en in-memory — win médian **3.5 min**,
  loss (double échec) médian **17.7 min**. Détails : `tests/measurements/session_time_security100_T1.2_fee0.5.md`.
- Objectif immédiat : **faire tourner les humains sur le moteur in-memory + outbox** (sinon
  sessions ~1.1–1.9 h sur le moteur DB).
- Docs : `ENGINE_ARCHITECTURE.md` (architecture), `SESSION_SUMMARY.md` (journal détaillé §1→§42).

## 1. Objectif produit
Maximiser le **% de joueurs qui gagnent** de l'argent en **protégeant leur bankroll** :
- sessions avec cible `T` ; en cas de perte, **1 seul rattrapage** ;
- un **fonds de remboursement** (alimenté par les frais) rembourse les échecs ;
- le tout doit tourner à la vitesse du simulateur, **bots et humains identiques**.

## 2. Architecture (3 couches)
1. **Moteurs de jeu**
   - `battlepool-engine` — moteur DB (`php artisan game:engine`) : écrit pools/fights en DB, pour
     le réel/audit. **Arrêté actuellement.**
   - `battlepool-sim-memory` — **full in-memory** (`php artisan sim:memory`) : bots P3, **0 écriture
     DB par pool**, audit via Redis Stream, persistance aux fins de session. **Actif.**
   - `batch-processor` — crée les pools internes (13 joueurs). **Arrêté** (inutile en full memory).
2. **Outbox effets de bord humains** (`side_effect_outbox`, `outbox:process`) : la boucle de jeu
   n'exécute **jamais** payout/notif/on-chain inline ; elle écrit 1 ligne, un consumer asynchrone
   fait le reste. → **parité de vitesse bots/humains**. (détail dans `ENGINE_ARCHITECTURE.md` §3)
3. **P3 — bankroll + rattrapage + fonds**
   - `C = baseBet × security_coefficient` ; `t = T−1` ; `φ_used = φ·T` ;
   - `X = (1+t+φ_used)/t` ; `R = C·X` ; `B = C+R` ;
   - session 1 (capital C) ; ruine → **rattrapage (capital R ET mise baseBet×X)** ; ruine → double échec ;
   - fonds : `F_C=φ_used·C` (session), `F_R=φ_used·R` (rattrapage), file FIFO, paiement par lots.

## 3. État déployé (VPS) — engine37
| Service | Image | État | Rôle |
|---|---|---|---|
| battlepool-app | engine37 | healthy | API/front (Octane) |
| battlepool-queue-worker(-limits) | engine37 | healthy | jobs (outbox/payout/limits) |
| battlepool-scheduler | engine37 | up | `schedule:work` (cleanup, refund, outbox) |
| battlepool-engine | engine37 | healthy | **moteur DB (actif, humains/bots)** |
| battlepool-batch-processor | 6628503 | healthy | crée les pools internes |
| battlepool-sim-memory | engine37 | **arrêté** | full in-memory (à utiliser À LA PLACE de engine+batch) |
| battlepool-bridge | 6628503 | healthy | node chain bridge |
| battlepool-db / redis / reverb | — | — | DB / cache-stream / WS |

## 4. Paramètres actuels (env `.env.staging`)
```
GAME_CURRENCY=avaxe
GAME_ENGINE_ENABLED=true   GAME_ENGINE_WORKER=true   GAME_ENGINE_BATCH_POOLS=40   GAME_ENGINE_TICK=1.5
GAME_ENGINE_SIM_NO_PAYOUT=true   GAME_ENGINE_DEFER_SIDE_EFFECTS=true
GAME_ENGINE_ARCHIVE_BATCH=5000   GAME_ENGINE_ARCHIVE_BATCHES=4
BANKROLL_ENABLED=true   BANKROLL_SESSION_TARGET_Q=1.2   BANKROLL_FEE_RATE=0.005
BANKROLL_SECURITY_COEFFICIENT=100
REFUND_ENABLED=true     REFUND_PCT=0.15
BATCH_PROCESSOR_INTERVAL=3000
```
On-chain : contrat **BattlepoolV2** `0xb628C8dE0ed6c1192dEB4C4C336fe3595370385E` — `feeBasisPoints=50` (**0.5 %**, tx `0x644d9229…a60bc0`). **GameSetting** `smart_contract_fee_percentage=0.5`.
AVAXe `0x9d8AA35058493Aa826be143B568BCfA8E2F537F7`. chainId 99999.
Cohorte : **1000 bots** `autoplay=1`, `target_q=1.2`, security 100 → `C=0.04`, `X=6.03`, `R=0.2412`, `B=0.2812` (seuil `1.206`).

## 5. Fichiers clés
- Moteurs : `app/Services/GameEngineService.php`, `app/Services/InMemorySimulationService.php`,
  `app/Console/Commands/RunGameEngine.php`, `RunMemorySimulation.php`.
- P3 : `app/Services/BankrollService.php`, `BankrollSessionService.php`, `config/bankroll.php`,
  `SessionManager.php` (évaluation session + P3 + sim fast-path + defer outbox), `InternalPoolService.php`.
- Fonds : `app/Services/RefundQueueService.php`, `ProcessRefundQueue.php`, `config/refund.php`,
  migrations `2026_11_05_000000..000002`.
- Outbox : `app/Services/SideEffectOutbox.php`, `ProcessSideEffectOutbox.php`, migration `..._000003`, `bootstrap/app.php` (schedules).
- Audit : `app/Services/FightStreamWriter.php`, `app/Jobs/ArchiveFightStream.php`.
- Front : `resources/js/modules/game.js` (dépôt `B=C+R` si `bankroll_enabled`).
- API : `app/Http/Controllers/BlockchainController.php` (`/api/artefacts` expose `bankroll_*`, `gameV2`, `avaxe`).
- Simulateurs : `public/simulator-recovery.html`, `simulator-fund.html`, `simulator-fast.html`, `simulator-algo6.html`.

## 6. Mesures réelles (in-memory, VPS 2 cœurs)
- Débit : ~7 000–15 000 fights/s (soit ~400–800 pools/s) en full memory avec 1000 bots.
- **Saturation** : ~1000–1200 bots sur 2 cœurs (à 1400 le débit chute).
- **Session GAGNÉE (T=1.2, security=100, frais 0.5 %)** — 6261 sessions, 2026-11-11 :
  **médiane 3.52 min** · p90 20.2 min · moyenne 7.40 min (censurée, vraie > 7.4) · p99 43 min.
  En pools : médiane **338**, moyenne 777.
- **Session PERDUE (double échec)** — 160 sessions : **médiane 17.74 min** ·
  moyenne **20.8 min** · p90 37.0 min · p99 48.9 min. En pools : médiane 3119.
- Taux observé : **97.5 % win / 2.49 % double échec** (théorie : 82.9 % par tentative ;
  au total 97.08 % win / 2.92 % double échec). Pertes sous-représentées par censure.
- Insight : `P(win) = 1/cible_effective` (indépendant de security) ; la **durée ∝ security²**.
- **Moteur DB ~100× plus lent** : ~4–6 pools/s total pour 1000 bots ⇒ session médiane
  ~1.1–1.9 h. Les humains doivent utiliser le **moteur in-memory + outbox**.

## 7. Bugs importants corrigés (ne pas réintroduire)
1. Session « ~2 pools » → **bug** : le sim utilisait le solde total au lieu du `battle_balance` de pool.
2. **Rattrapage : la mise doit être `baseBet×X`** (sinon la session dure ~X² fois plus longtemps).
3. Sim : sélection des pools **par palier de mise**, et **choix aléatoire** parmi les paliers éligibles
   (sinon les pools de rattrapage ne se forment jamais).
4. Audit : phpredis renvoie la clé Stream **préfixée** (`laravel_database_...`) → `reset($messages)`.
5. `nextMove()` : passage par référence (utiliser array+id).
6. P1 : pas de `f_hists`/`queue`/notifs pour bot-vs-bot.

## 8. Limites / risques connus
- **Écart DB vs in-memory** : le moteur DB est ~100× plus lent (sessions ~1.1–1.9 h).
  ⇒ les humains doivent être traités par le moteur rapide (P2.1 live events), sinon parité impossible.
- Le **fonds** n'est **pas** alimenté par le sim-memory (option a) → file/refund restent côté moteur DB/réel.
- **P2.1 non fini** : events humains live (`FightResult`/`MatchStarted` dans l'engine), `f_hists`/IPFS.
- `sim:memory` charge la cohorte **au démarrage** (pas de nouveaux bots à chaud sans restart).
- Payouts on-chain non testés de bout en bout (RPC L1 a eu une coupure ; cotisation P-Chain ~2 mois).

## 9. Prochaines étapes
1. **P2.1 live** : émettre `FightResult`/`MatchStarted` pour les humains dans le moteur **in-memory**
   (outbox déjà prêt) → parité de vitesse et sessions de quelques minutes.
2. **UI** : refléter le flow (déjà fait : Setup/Dashboard/Claim/remboursements).
3. Optionnel : **stop-loss** si on veut borner les pertes (aujourd'hui ~18 min médian).
4. Surveiller `REFUND_PCT` ≤ couverture (0.15 ≤ ~0.20) ; `security=100` = sessions jouables.

## 10. Opérations utiles
```sh
# Voir le sim
docker logs -f battlepool-sim-memory
tail -f /opt/battlepool/report_p3.log
# DB (mot de passe via secret)
PW=$(docker exec battlepool-app cat /run/secrets/db_app_password)
docker exec -e DB_PASSWORD="$PW" battlepool-app php artisan tinker --execute='...'
# Migrations
docker exec -e DB_PASSWORD="$PW" battlepool-app php artisan migrate --force
# Redeploy (après build engineXX)
cd /opt/battlepool && GIT_SHA=engineXX docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate app scheduler queue-worker queue-worker-limits sim-memory
# Basculer sur le moteur DB (au lieu du sim) : arrêter sim-memory, démarrer engine + batch-processor
```
Secrets : `/opt/battlepool/.secrets/` (db_app_password, app_key, faucet_wallet_pk=owner contrat, ...).
DB : conteneur `battlepool-db`, base `rock_paper_scissors`, user `rps_app`.

## 11. Avancement UI (mise à jour)
- ✅ **Étape 1 — Setup** : `public/index.html` (`#p3-breakdown`) + `game.js::updateFeeDisplay`
  affichent `C` (budget session), `R=C·X` (réserve rattrapage), `B=C+R` (bankroll),
  la règle de remboursement, et `TOTAL DEPOSIT (une fois)` = `B+frais`.
  Déployé (engine29). Actif quand `/api/artefacts.bankroll_enabled=true`.
- ✅ **Étape 2 (faite)** : Dashboard — panneau état P3 (Session 1 / Rattrapage / Gagné /
  Remboursement en attente), bankroll, réserve, next session.
- ✅ **Étape 3 (faite)** : Remboursements — endpoint `/api/refund/pending` (via `RefundQueueService::pendingForUser`)
  + panneau file d'attente (« vous pouvez rejouer »).
- ✅ **Étape 4 (faite)** : Claim — distinguer payout de session vs remboursement crédité.

## 12. P2.1 — avancement
- ✅ Combats humains → `fight_result` via outbox async ; pools avec humains → `archive_pool`
  (f_hists via `HistoricalFightService`, hors boucle).
- ✅ IPFS (`session_history` outbox -> `UploadSessionHistoryJob`).
- Choix moteur : **humains => `game:engine` + batch-processor** ; **bots => `sim:memory`** (pas les deux).
- ✅ Humains inclus dans les pools internes (`INTERNAL_INCLUDE_HUMANS`) + P3 init (engine36).
- ✅ E2E validé : dépôt on-chain -> indexeur -> balance DB -> humain matché -> P3 init (fix reactivation humains).
- ✅ Test navigateur wallet (EIP-1193 injecté) : dépôt on-chain via UI -> sync DB -> match humain -> P3 init. P2.1(A) TERMINÉ.
