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
