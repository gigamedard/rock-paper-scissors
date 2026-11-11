# BattlePool — Rapport final consolidé (P1 → P3, engine, UI, audits)

> Synthèse de tout le chantier. Détails : `SESSION_SUMMARY.md` (§1→§41),
> `MEMORY_BACKUP.md` (handover), `ENGINE_ARCHITECTURE.md` (architecture).

## 1. Objectif
Rock-Paper-Scissors on-chain sur **Pingala L1** (chainId 99999), devise **AVAXe** (ERC-20),
gas **TST**. But : **maximiser le % de joueurs qui gagnent** tout en **protégeant leur bankroll**,
avec un moteur **aussi rapide que le simulateur**, **bots et humains identiques**.

## 2. Les 3 chantiers
### P1 — Quick wins
- Bot-vs-bot : plus de `f_hists`, `queue_table`, notifications, broadcasts `FightResult`.
- Soldes en 1 écriture/pool ; `isBot()` = `autoplay_active`.
- Correctifs session : `solde >= base` ⇒ la session continue (évite les faux succès) ;
  réactivation des bots auto-stoppés ; `max_balance`/`min_balance` pour l'analyse des ruines.

### P2 — Engine (vitesse)
- `GameEngineService` : pools (13 joueurs, 5 rounds PFC) calculés **en mémoire**, **1 bulk insert**
  des `fights`, 1 save/joueur, puis `SessionManager::evaluatePoolEnd`.
- **Audit découplé** : chaque combat → **Redis Stream** → `ArchiveFightStream` (consumer group →
  JSONL + XACK + XTRIM).
- **Worker persistant** `game:engine` (compose `battlepool-engine`), `Pool::match()` no-op ;
  `batch_pools=40`.
- **Full in-memory** `sim:memory` : 0 écriture DB/pool, audit Stream, persistance aux fins de session.
- **Mode simulation bots** : `SIM_NO_PAYOUT` (pas de payout on-chain).
- Mesures : **~4 000 fights/min** (moteur DB) ; **~20 000–300 000+ fights/min** (full in-memory) ;
  saturation ~1000–1200 bots sur 2 cœurs.

### P2.1 — Humains + outbox + audit (parité)
- **Outbox asynchrone** (`side_effect_outbox` + `outbox:process` schedulé) : la boucle de jeu
  n'exécute **jamais** l'I/O lente. Types : `payout`, `session_finished`, `fight_result`,
  `notification`, `sync_limits`, `cooldown`, `archive_pool` (f_hists), `session_history` (IPFS).
- **IPFS** : `session_history` → `HistoricalFightService`/`UploadSessionHistoryJob` (CID on-chain) hors boucle.
- **Humains dans les pools internes** : `INTERNAL_INCLUDE_HUMANS` (autoplay=0, email non-`bot_%`).
  Vérifié : humain matché → **P3 initialisé**.
- **Dépôt on-chain → sync DB** : `DepositReceived` (indexeur bridge) → `/internal/update-balance` ;
  **réactivation des humains** après dépôt (fix).
- **Tests navigateur** : (a) SPA/Setup 19/19 ; (b) session restaurée 9/9 ; (c) **wallet EIP-1193 injecté**
  6/6 → dépôt `B+frais` via l'UI → DB = 2.892 → match → P3 init.

### P3 — Bankroll + rattrapage + fonds de remboursement
- `C = baseBet × security_coefficient` ; `T` (target session) ; `φ` (frais) ;
  `X = (1+t+φ·T)/t` ; `R = C·X` ; `B = C+R`.
- Session gagnée → `+C·t`. Ruine → **1 rattrapage** : capital `R` **ET mise `baseBet·X`** (même %).
  Rattrapage perdu → **double échec** → **fonds de remboursement** (FIFO).
- Fonds alimenté par `F_C=φ_used·C` (session) + `F_R=φ_used·R` (rattrapage) ;
  rembourse `refund%·B` ; paiement par lots (seuil 10%, batch 5%), jamais négatif ;
  multi-remboursements distincts par joueur ; crédit du solde au paiement ; rejeu possible pendant l'attente.
- Viabilité : `refund% ≤ couverture = frais/(q·B)`.
- **Contrat V2** : frais portés à **1000** (10%) puis **50 (0,5 %)** pour le test du 2026-11-11
  (tx `0x644d9229…a60bc0`) ; `GameSetting` aligné (0.5).
- **Test 2026-11-11 — frais 0.5 %, security=100, T=1.2** (in-memory, 1000 bots, 6421 sessions) :
  - **Session gagnée** : médiane **3.52 min** · p90 20.2 min · moyenne 7.40 min (censurée) ·
    p99 43 min ; pools médian 338.
  - **Session perdue (double échec)** : médiane **17.74 min** · moyenne **20.8 min** · p90 37 min ;
    pools médian 3119. Taux observé 97.5 % win / 2.49 % double échec.
  - Détails : `tests/measurements/session_time_security100_T1.2_fee0.5.md`.
- Mesures antérieures (security=300, T=1.05) : session gagnée ~8,7 min ; pertes très rares/lentes.
  Insight confirmé : `P(win)=1/cible_effective` (indépendant de security) ; **durée ∝ security²**.
- **Écart moteur** : le moteur DB (~4–6 pools/s pour 1000 bots) donnerait ~1.1–1.9 h par session ;
  le moteur in-memory donne ~3.5 min ⇒ **les humains doivent passer sur l'in-memory + outbox**.

## 3. UI (flow P3)
- **Setup** : `C`, `R=C·X`, `B=C+R`, règle de remboursement, **Total dépôt (une fois) = B+frais**.
  (alignement front/back via `bankroll_security_coefficient`).
- **Cible de session** : sélecteur **1.05x / 1.2x** (+ options débloquées par cartes).
  En P3, la cible choisie pilote **X, R et B** côté UI et backend (`users.target_q`) :
  1.05 → `X=21.105, R=0.8442, B=0.8842` ; 1.2 → `X=6.03, R=0.2412, B=0.2812` (C=0.04).
- **Dashboard** : panneau P3 = état **Session 1 / Rattrapage / terminée**, B/C/R,
  **remboursements en attente (liste FIFO)** + « vous pouvez rejouer », **remboursements reçus**.
- **Claim** : montant retirable + **« dont remboursement crédité »**.

## 4. Tests (tous verts)
| Suite | Résultat |
|---|---|
| `bankroll_state_machine_test` | 24/24 |
| `refund_queue_simulation` | 27/27 |
| Headless E2E (HTTP/DB/commandes/outbox/unit) | 22/22 |
| Navigateur Playwright (SPA/Setup P3, cibles 1.05/1.2) | 23/23 |
| Navigateur connecté (session restaurée) | 9/9 |
| Navigateur **wallet EIP-1193** (dépôt UI) | 6/6 |

## 5. Bugs importants corrigés
- Claim/AVAXe : `GAME_CURRENCY` perdu → défaut `avaxe` + env.
- Engine : `nextMove` par référence ; Stream phpredis préfixé (`reset($messages)`).
- Sim : solde total au lieu de `battle_balance` (temps de session faux) ; **mise ×X au rattrapage** ;
  sélection de palier (famine) ; **inclusion/ réactivation des humains**.
- Fonds : multi-remboursements FIFO distincts ; ack/trim du Stream.

## 6. État actuel
- Branche `feat/session-recovery`, image **engine38** partout (app/queue/queue-limits/scheduler/engine).
- `battlepool-engine` (moteur DB) + `battlepool-batch-processor` actifs ; `sim-memory` arrêté.
  Cohorte **1000 bots** `target_q=1.2`, security 100 (`C=0.04`, `R=0.2412`, `B=0.2812`).
- `INTERNAL_INCLUDE_HUMANS=true`, `DEFER_SIDE_EFFECTS=true`, `SIM_NO_PAYOUT=true`,
  `REFUND_PCT=0.15` (≤ couverture ~20.6 %).
- Contrat V2 **frais 0.5 %** ; GameSetting 0.5.

## 7. Restant / prochaines étapes
- **P2.1 live** : traiter les humains sur le **moteur in-memory** (events `FightResult`/
  `MatchStarted` via outbox) → parité avec les bots et sessions de quelques minutes.
- **UI** : déjà alignée (Setup/Dashboard/Claim/remboursements).
- Optionnel : **stop-loss** pour borner les pertes (~18 min médian aujourd'hui).
- **Prod** : services dédiés (engine/outbox/sim), monitoring, cron P-Chain (cotisation validateur).
- Test « vraie session complète » (win/ruin) en navigateur : long (minutes–heures).
