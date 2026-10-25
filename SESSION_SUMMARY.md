# SESSION_SUMMARY.md — Résumé d'état pour reprendre le projet

> À lire **en premier** dans une nouvelle conversation. État consolidé à la fin de la
> session du 2026-10-23. Complète `BP_SESSION_STATE.md` (mémoire longue) et
> `docs/COLLAB_STATE.md` (protocole LOCAL↔CLOUD).

---

## 0. Où on en est (les 10 lignes)

- Projet **BattlePool** : jeu PvP RPS on-chain, L1 **Pingala** (chainId **99999**, natif **TST**),
  RPC `https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc`.
- **Prod** : `https://battlepool.31.187.72.98.sslip.io` — conteneurs `battlepool-*` healthy.
- **SHA déployé en prod** : **`fe88739`** (branche `feat/avaxe-bridge`).
- **Migration TST → AVAXe FAITE** : devise de jeu = **AVAXe** (ERC-20 `0x9d8A…37F7`), contrat
  **BattlepoolV2** `0xb628C8dE…385E` (dépôt/payout/claim ERC-20). TST = gas natif uniquement.
- **240 bots** (indices 0-239, `simulation_accounts.json` 244 entrées), pools de **5**.
- **Config matchmaking** (équilibrée après bench) : serveur `run_batch_processor.js` intervalle
  **1,57 s** (env `BATCH_PROCESSOR_INTERVAL`), **6 workers** Swoole, lock `metrics_collect_tick` TTL **10 s**,
  polling client **200 ms**.
- **Optimisations DB** (migrations) : index composites `f_hists`/`users`/`pools`, buffer pool **2 G**,
  `innodb_flush_log_at_trx_commit=2` (dans `docker-compose.prod.yml`, pas dans git).

---

## 1. Branches & SHA (à la fin de la session)

| Branche | SHA tip | Rôle |
|---|---|---|
| `feat/app1-deeplink-app2` | `9712b03` | **Ligne de travail principale** (toutes les features UI/game) |
| `feat/app1-claim-pingala-fix` | `3653ef0` | **Version corrigée du claim** (force Pingala) — mise de côté à la demande de l'opérateur |
| `feat/avaxe-bridge` | `fe88739` | **Branche active = prod** (migration AVAXe + 240 bots + index DB) |
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
