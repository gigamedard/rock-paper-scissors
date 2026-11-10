# BattlePool — Architecture du moteur de jeu (P2/P3)

> Document de référence : comment le jeu calcule les combats/sessions à grande
> vitesse, comment les effets de bord (payouts/notifs) sont décalés, et comment
> le modèle P3 (bankroll + rattrapage + fonds de remboursement) fonctionne.

---

## 1. Vue d'ensemble

```
                    ┌──────────────────────────────┐
                    │  Batch processor (1.5 s)     │  crée les pools internes (13 joueurs)
                    └──────────────┬───────────────┘
                                   │ HTTP interne
                    ┌──────────────▼───────────────┐
                    │  Laravel app (Octane/Swoole) │
                    └──────────────┬───────────────┘
          ┌────────────────────────┼─────────────────────────┐
          │                        │                         │
 ┌────────▼────────┐     ┌─────────▼─────────┐     ┌─────────▼─────────┐
 │ Moteur DB       │     │ Moteur mémoire    │     │ Outbox effets     │
 │ (game:engine)   │     │ (sim:memory)      │     │ (outbox:process)  │
 │ pools/fights DB │     │ 0 écriture/pool   │     │ payouts/notifs    │
 └────────┬────────┘     └─────────┬─────────┘     └─────────┬─────────┘
          │                        │                         │
          └────────────┬───────────┘                         │
                       │                                      │
              ┌────────▼────────┐                    ┌────────▼────────┐
              │ Redis Stream    │  audit/équité      │ Queue workers   │
              │ (fights)        │  → ArchiveFight    │ (payout, notif) │
              └─────────────────┘                    └─────────────────┘
```

Principe directeur : **la boucle de jeu ne fait jamais d'I/O lente**. Le calcul
est en mémoire ; la persistance et les effets de bord (paiements, notifications,
on-chain) sont **différés**.

---

## 2. Les deux moteurs

### 2.1 Moteur DB (`game:engine`) — « réel »
- Traite les pools `from_server_waitting`/`batched`.
- 5 rounds PFC en mémoire, **1 bulk insert** des `fights`, 1 save/joueur.
- Fin de pool via `SessionManager::evaluatePoolEnd`.
- Persiste tout (pools, fights, users) → nécessaire pour les **humains/audit DB**.
- Débit mesuré : **~4 000 fights/min** (500 bots, load ~4,5).

### 2.2 Moteur mémoire (`sim:memory`) — « full in-memory »
- `InMemorySimulationService` charge la cohorte en mémoire (état complet).
- Joue les pools (5 rounds) **sans aucune écriture DB par pool**.
- Chaque combat → **Redis Stream** (audit). Persistance **uniquement aux fins de
  session** (`persistDirty`).
- Débit mesuré : **~20 000–30 000 fights/min** (500 bots, load ~2,5).
- Sert aux **simulations de bots** (vitesse/stratégie) ; option (a) : pas de
  dette de fonds en DB (le fonds reste sur le moteur réel).

---

## 3. Innovation — Outbox des effets de bord (parité bots/humains)

### Problème
Un humain doit recevoir : payout/signature, notifications, cooldown, syncLimits
(on-chain), broadcast `SessionFinished`. Ces opérations sont **lentes** (réseau,
contrat, signatures). Si on les exécute dans la boucle, les humains ralentissent
le jeu (et donc les bots qui partagent le moteur).

### Solution : Outbox asynchrone
1. La boucle de jeu calcule le résultat (identique bots/humains).
2. Pour un humain, elle **écrit 1 ligne** dans `side_effect_outbox`
   (`pending`, payload) et **continue immédiatement**.
3. Le consumer (`outbox:process`, planifié chaque minute + queue workers)
   exécute réellement l'effet, avec **retries/backoff** et idempotence.
4. Les bots (« sim ») n'émettent **rien** → aucun coût.

```
jeu ──(1 INSERT rapide)──► side_effect_outbox ──(consumer)──► payout / notif / on-chain
   └─ continue sans attendre
```

### Types d'effets gérés
| type | exécution |
|---|---|
| `payout` | `ProcessPayoutJob` (on-chain) |
| `session_finished` | broadcast `SessionFinished` |
| `fight_result` | broadcast `FightResult` (humains) |
| `notification` | `GameNotification` |
| `sync_limits` | `SyncUserLimitsJob` |
| `cooldown` | `SetCooldownJob` |

### Garanties
- **La vitesse du jeu ne dépend plus des payouts.**
- Retries : 5 tentatives, backoff 30→300 s ; échec final `failed` (audit).
- Activation : `GAME_ENGINE_DEFER_SIDE_EFFECTS=true` (défaut `false` → inline).
- Fichiers : `SideEffectOutbox`, `outbox:process`, migration
  `2026_11_05_000003_create_side_effect_outbox`, wiring dans `SessionManager`.

---

## 4. Modèle P3 — bankroll + rattrapage + fonds

- `C = baseBet × security_coefficient` (capital session 1, fixe).
- `T` cible de session (ex. 1.2) ; `t = T−1`.
- `φ` frais ; `φ_used = φ·T`.
- `X = (1 + t + φ_used)/t` ; `R = C·X` (réserve) ; `B = C+R` (bankroll).
- **Session 1** : joue `C` vers `C·T·(1+φ)`.
  - gagne → `+C·t` ; perd → **1 rattrapage** avec `R`.
- **Rattrapage** : joue `R` ; gagne → se refait ; perd → **double échec**.
- **Fonds de remboursement** (`refund_queue`, FIFO) : rembourse `refund%×B`
  aux double-échecs, alimenté par `F_C = φ_used·C` (session) et `F_R = φ_used·R`
  (rattrapage). Paiement par lots (seuil 10 %, batch 5 %), jamais négatif.
- **Viabilité** : `refund% ≤ couverture = frais / (q·B)`.
  À `T=1.2, φ=10%` : couverture ≈ **69.9 %** → `refund=68 %` recommandé.

Fichiers : `BankrollService`, `BankrollSessionService`, `RefundQueueService`,
`refund:process`, migrations `2026_11_05_000000..000002`.

---

## 5. Audit / équité
- Chaque combat → `XADD battlepool:fights` (Redis Stream).
- `ArchiveFightStream` (consumer group) → archive JSONL + `XACK` + `XTRIM`.
- Découplé du jeu : si le consumer retarde, le jeu continue.

---

## 6. Configuration (flags)
| Variable | Rôle |
|---|---|
| `GAME_ENGINE_ENABLED` | moteur mémoire via `Pool::match()` |
| `GAME_ENGINE_WORKER` | worker persistant (Pool::match no-op) |
| `GAME_ENGINE_SIM_NO_PAYOUT` | bots sans payout/on-chain |
| `GAME_ENGINE_DEFER_SIDE_EFFECTS` | **outbox humains** |
| `GAME_ENGINE_BATCH_POOLS` | pools/tick du worker DB |
| `BANKROLL_ENABLED` / `BANKROLL_SESSION_TARGET_Q` / `BANKROLL_FEE_RATE` | P3 |
| `REFUND_ENABLED` / `REFUND_PCT` | fonds de recouvrement |

---

## 7. Vitesses mesurées (VPS 2 cœurs)
| Moteur | Débit | Load |
|---|---|---|
| Ancien turbo | ~1 500–1 700 fights/min | – |
| DB engine (500 bots) | ~4 000 fights/min | ~4,5 |
| **Full in-memory (500 bots)** | **~20 000–30 000 fights/min** | **~2,5** |

---

## 8. Limites / suite
- `sim:memory` est un service compose (`battlepool-sim-memory`) ; ne pas lancer
  en même temps que `engine` + `batch-processor`.
- Outbox : consumer planifié 1×/min ; pour de gros volumes humains, passer en
  service `outbox:process --loop` (débit supérieur) ou queue dédiée.
- Idempotence des payouts : à renforcer (clé d'idempotence par session).
- P2.1 (events humains live, `f_hists`/IPFS) reste à finaliser pour la prod.

---

## 9. Note critique — rattrapage (mise ×X)
La session de rattrapage doit garder le **même ratio capital/mise** que la session 1 :
capital `R = C·X` **ET** mise `= baseBet·X` (même `target`). Sinon la durée de résolution
∝ (capital/mise)² explose en `X²` (ex. X=23 → ~530× plus long). Les pools se forment **par
palier de mise** : le rattrapage a son propre palier (`baseBet·X`).
