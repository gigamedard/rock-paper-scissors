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
- **Contrat V2** : frais 250 → **1000** (10%, plafond contrat = 10% → 20.5% impossible).
  `GameSetting=10`.
- Mesures réelles (security=300, T=1.05) : **session gagnée ~8,7 min** (médiane 6,9 ; p90 18,2 ;
  891 sessions réelles). Distribution à queue lourde. **Pertes (double échec) rares (~1,8%) et lentes
  (~90 min)** → non observées. Insight : `P(win)=1/cible_effective` (indépendant de security) ;
  **durée ∝ security²**.

## 3. UI (flow P3)
- **Setup** : `C`, `R=C·X`, `B=C+R`, règle de remboursement, **Total dépôt (une fois) = B+frais**.
  (alignement front/back via `bankroll_security_coefficient`).
- **Dashboard** : panneau P3 = état **Session 1 / Rattrapage / terminée**, B/C/R,
  **remboursements en attente (liste FIFO)** + « vous pouvez rejouer », **remboursements reçus**.
- **Claim** : montant retirable + **« dont remboursement crédité »**.

## 4. Tests (tous verts)
| Suite | Résultat |
|---|---|
| `bankroll_state_machine_test` | 24/24 |
| `refund_queue_simulation` | 27/27 |
| Headless E2E (HTTP/DB/commandes/outbox/unit) | 22/22 |
| Navigateur Playwright (SPA/Setup P3) | 19/19 |
| Navigateur connecté (session restaurée) | 9/9 |
| Navigateur **wallet EIP-1193** (dépôt UI) | 6/6 |

## 5. Bugs importants corrigés
- Claim/AVAXe : `GAME_CURRENCY` perdu → défaut `avaxe` + env.
- Engine : `nextMove` par référence ; Stream phpredis préfixé (`reset($messages)`).
- Sim : solde total au lieu de `battle_balance` (temps de session faux) ; **mise ×X au rattrapage** ;
  sélection de palier (famine) ; **inclusion/ réactivation des humains**.
- Fonds : multi-remboursements FIFO distincts ; ack/trim du Stream.

## 6. État actuel
- Branche `feat/session-recovery`, image **engine37** (app/queue/scheduler/engine).
- `battlepool-engine` (moteur DB, humains) + `battlepool-batch-processor` actifs sur une cohorte
  scoped (12 bots) ; `sim-memory` arrêté. `INTERNAL_INCLUDE_HUMANS=true`,
  `DEFER_SIDE_EFFECTS=true`, `SIM_NO_PAYOUT=true` ; `REFUND_PCT=0.68` ; martingale pool = 4.
- Contrat V2 frais 10% ; GameSetting 10%.

## 7. Restant / prochaines étapes
- **Restauration** de la simulation complète (1000 bots + `sim:memory`) OU run DB pour humains.
- **P3 paramétrage jouable** : `security_coefficient` ~30–100 (sessions minutes) ; calibrer
  `refund% ≤ couverture` ; envisager un **stop-loss** (pertes mesurables/jouables).
- **P2.1** : events live en in-memory si humains inclus dans le moteur rapide.
- **Prod** : services dédiés (engine/outbox/sim), monitoring, cron P-Chain (cotisation validateur).
- Test « vraie session complète » (win/ruin) en navigateur : long (minutes–heures).
