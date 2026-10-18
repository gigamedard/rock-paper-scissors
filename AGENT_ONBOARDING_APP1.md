# AGENT ONBOARDING — App 1 « BattlePool » (rock-paper-scissors)

> **À lire avant toute modification.** Ce fichier décrit l'état RÉEL et le protocole de
> collaboration LOCAL ↔ CLOUD. Il complète `docs/COLLAB_STATE.md` (tableau noir partagé).

---

## 0. Où est le code

- **Repo (remote)** : `github.com/gigamedard/rock-paper-scissors`
- **Branche d'intégration** : `master` (**PR uniquement**, jamais de push direct).
- **Branche de travail courante** : `feat/app1-deeplink-app2` (vérifier celle fournie par
  l'opérateur avant de lire/modifier).
- **App 2 (Godot) = autre repo** : `github.com/gigamedard/godot_fight`, sous-dossier
  `Web3_Combat_Game/`. Ne pas confondre.
- Fichiers **non versionnés** : `.env.staging`, `.secrets/`, `battlepool/.env`, `scratch/`,
  `deploy/` (gitignored), `graphify-out/`.

---

## 0bis. Collaboration LOCAL ↔ CLOUD (rituel)

- **LOCAL** = poste Windows, déploie via SSH. **CLOUD** = `root@srv2032251` (VPS `31.187.72.98`).
- **Tableau noir** : `docs/COLLAB_STATE.md`. **Journal** : `docs/handoffs/YYYY-MM-DD-<local|cloud>.md`
  (**append-only**).

### AVANT de travailler
```bash
git fetch --all --prune
git switch <branche fournie>
git pull --rebase
```
puis lire `docs/COLLAB_STATE.md` + les **2 dernières passations**.

### Pendant
- Prendre une tâche : la passer `IN PROGRESS` + owner dans `COLLAB_STATE.md`.
- **Verrouiller les hotspots** (`public/index.html`, `resources/js/app.js`,
  `resources/js/web3/web3-core.js`, `routes/api.php`, `deploy/deploy_vps.sh`) :
  `Verrou fichier : <fichier> par <owner> (depuis <date>)`.
- **Une branche par tâche** ; PR obligatoire ; **jamais pousser sur `master`**.

### AVANT de rendre la main
- `commit` + `push`.
- Mettre à jour `COLLAB_STATE.md` (tâche + ancrages).
- **Ajouter** une entrée `docs/handoffs/` (append-only).

### Règles apprises
- **Toujours `git fetch` avant d'affirmer « up to date »** (un push sur la mauvaise branche
  est un no-op silencieux).
- Vérifier `git branch --show-current` **et** `git branch -a` avant de committer.
- Un seul agent par fichier chaud ; un seul déployeur à la fois (verrou deploy).

---

## 1. Architecture déployée (VPS `31.187.72.98`)

```
Caddy (SNI battlepool.31.187.72.98.sslip.io, 443)
 ├─ /app/*  → 127.0.0.1:8008  (Laravel Reverb WebSocket)
 └─ /*      → 127.0.0.1:8001  (app Octane : /api + bundle public/build)
```
Conteneurs : `battlepool-app`, `battlepool-queue-worker`, `battlepool-queue-worker-limits-1/2`,
`battlepool-bridge`, `battlepool-batch-processor`, `battlepool-db`, `battlepool-redis`,
`battlepool-reverb`. **DB non exposée.**

⚠️ **Ne jamais toucher** : `avalanchego`, `signature-aggregator` (L1), conteneurs
`web3_combat-*`, `/opt/web3-combat`, blocs Caddy L1/App2.

---

## 2. Chaîne & contrats (Pingala)

- **Pingala Chain** : chainId **99999** (`0x1869f`), symbole **TST** (18 déc.),
  RPC `https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc`,
  **pas d'explorer**. L1 auto-hébergée sur le VPS (SPOF).
- **Contrats** : `Battlepool` `0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF` ·
  `PranaToken` `0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B` ·
  `MarketplaceEscrow` `0x6C12875a81356d6b3042737C8eb7AAE9B4a088ac` ·
  `WAVAX` `0x0a324396FB40C9cf10f665bFDb21FFceBe66CfD4`.
- **Owner / deployer** : `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee`.
- Référence : `battlepool/pingala_deployments.json`.
- **Réseau centralisé front** : `resources/js/web3/web3-core.js` (`PRANA_NETWORK`) —
  ne pas dupliquer ailleurs.

---

## 3. Déploiement (pipeline COMPLET — rebuild images)

```powershell
node secrets-manager.mjs provision
powershell -ExecutionPolicy Bypass -File deploy\run_deploy.ps1 -GitSha <sha>
node secrets-manager.mjs deprovision
```
- `/opt/battlepool` = clone git ; `run_deploy.ps1` fetch + checkout du SHA (la branche `master`
  du VPS est **épinglée** au SHA déployé).
- **Le code applicatif n'est PAS monté en volume** → **jamais `docker cp`** : modifier la source,
  puis rebuild.
- Vérifier le **bundle servi** (`docker exec battlepool-app grep -c '<marqueur>' /var/www/html/public/build/assets/app.js`).
- Après deploy : renseigner `Dernier déployé` dans `COLLAB_STATE.md` §4.

---

## 4. Pièges connus

- **`.sslip.io`** : MetaMask/Core affichent « Malicious site » (DNS wildcard + IP) → vrai domaine
  pour la prod (bêta privée = sslip.io toléré).
- **chainId 99999 partout** ; après reset chaîne → **réinitialiser le cache MetaMask**
  (Paramètres → Avancé → Effacer les données d'activité).
- **Pas de faucet public** sur Pingala → funder les wallets qui signent (TST natif).
- **LF uniquement** dans `.env`/scripts (CRLF casse sous Linux).
- **`LOG_LEVEL=warning`** et `APP_DEBUG=false` en staging/prod.
- Ne jamais exposer/committer de secret (`.env`, `.secrets/`, clés privées).
- Ne jamais committer `scratch/`, `deploy/`, `proxy/certs/`, `graphify-out/`.

---

## 5. Règles de travail

- Branche par sujet ; commits descriptifs (français) `feat(app1): …` / `fix(app1): …` /
  `sec(prod): …` / `docs(app1): …`.
- **PR obligatoire** vers `master` (le LOCAL merge).
- Toujours vérifier la cohérence : adresse contrat + RPC + chainId dans
  `web3-core.js` / `.env.staging` / `smart_contracts/config.js` / `battlepool/.env`.
- Mission explicite requise pour tout déploiement (pas de deploy « au passage »).
