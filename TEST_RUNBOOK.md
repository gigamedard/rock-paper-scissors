# TEST_RUNBOOK — Procédure de test manuel navigateur

**Projet :** `G:\DEV\PHP\rock-paper-scissors\` (Laravel + Octane/Swoole + Redis + Reverb + Bridge Node.js + Hardhat)
**Objectif :** remettre à zéro l'environnement, déployer les contrats, peupler les bots, et laisser 4 comptes réservés à l'humain.
**Dernière mise à jour :** 2026-08-28 — **PROTOCOLE P.R.O.T.** documenté (§0bis), auto-réparation des ABIs au boot (HANDOVER §18), comptes humains pré-créés, funding services.

---

## 0. ⚡ PROTOCOLE P.R.O.T. — Toute intervention manuelle passe par là

**P**rovision → **R**ecreate (jamais restart) → **O**pérer → **T**eardown (deprovision)

> **Pourquoi ce protocole existe :** les secrets sont chiffrés au repos (`.secrets/*.enc`). Le
> `deprovision` supprime le plaintext → les bind mounts `/run/secrets/` des containers deviennent
> des **fantômes** (fichiers `?????????` illisibles, visibles dans `ls -la /run/secrets/` mais
> `cat` → No such file or directory). **Un `docker restart` ne répare PAS un fantôme** (le mount
> reste stale) et un nouveau `provision` ne le recrée pas pour un container **existant**. Seule
> la **recréation** du container re-binde les fichiers. De même, après un restart blockchain,
> la chaîne Hardhat repart à 0 → l'indexeur (état persistant en DB) devient "du futur" →
> événements ratés → marketplace/game figés.

### Les 4 règles d'or

| R | Règle | Pourquoi |
|---|---|---|
| **P** | `node secrets-manager.mjs provision` **AVANT** tout restart/recréation de container ou usage de tinker/scripts | Sans plaintext, `/run/secrets/` = fantômes |
| **R** | **Recréer** plutôt que redémarrer si les secrets ont été déprovisionnés depuis la création : `docker compose up -d --force-recreate <svc>` (ou repasse par `start.ps1`) | `restart` conserve le mount fantôme |
| **O** | Opérer (diagnostics, scripts bp-test, SQL...) | — |
| **T** | `node secrets-manager.mjs deprovision` **EN DERNIER**, puis ne plus toucher aux containers | Sécurité (0 secret en clair) |

### Arbre de décision — « je dois intervenir, je fais quoi ? »

```
QUEL EST LE PROBLÈME ?
│
├─ Machine redémarrée / Docker Desktop down / stack totalement down ?
│    → .\start.ps1   (gère : Docker Desktop, MTU WSL2, fantômes .secrets,
│                      provision, up, health, deprovision — HANDOVER §18.8)
│    → PUIS si la blockchain a redémarré (blockNumber < sync_states) :
│       POST-RESTART PROTOCOL ci-dessous.
│
├─ Je dois lancer un script dans un container (tinker, node, .sh) ?
│    → provision → docker exec ... → deprovision (P→O→T, sans restart)
│
├─ J'ai besoin de restarter un container (bridge, workers...) ?
│    → provision AVANT, restart, deprovision APRÈS (P→R→O→T)
│    → si après restart le container crashe "invalid private key" / "Access denied rps_app"
│      : c'est le mount fantôme → provision + docker compose up -d --force-recreate <service>
│
├─ La marketplace/game ne réagit plus aux événements on-chain ?
│    → POST-RESTART PROTOCOL ci-dessous (chaîne redémarrée ?)
│
└─ Une offre marketplace invisible / UI vide ?
     → HANDOVER §18.2 (ABI check) + bridge fail-fast "✅ [BRIDGE] ABIs validées"
```

### POST-RESTART PROTOCOL (chaîne Hardhat redémarrée — à exécuter dans l'ordre)

```powershell
# 1. VÉRIFIER le décalage chaîne vs indexeur
docker exec rock-paper-scissors-blockchain-1 sh -c "cd /app && node -e \"const {JsonRpcProvider}=require('ethers');new JsonRpcProvider('http://localhost:8545').getBlockNumber().then(b=>console.log('chain block:',b))\""
# (sync_states visibles via bp-test\check_trades.sh)

# 2. Si la chaîne est PLUS JEUNE que l'indexeur → reset les états
#    (scripts bp-test\ ; fallback : pipe stdin docker exec -i ... "cat > /tmp/x.sh")
docker exec rock-paper-scissors-app-1 sh /tmp/reset_indexer_marketplace.sh   # marketplace + snt
docker exec rock-paper-scissors-app-1 sh /tmp/reset_indexer_game.sh          # game

# 3. Funder les wallets de service (TOUJOURS après un restart blockchain — §17.1)
#    Idempotent ; relancer si "nonce too low"
Get-Content -Raw bp-test\fund_services.cjs | docker exec -i rock-paper-scissors-blockchain-1 sh -c "cat > /app/fund_services.cjs"
docker exec rock-paper-scissors-blockchain-1 sh -c "cd /app && node fund_services.cjs"
#    Vérifier : bp-test\check_balances.cjs → PAYOUT 100 / SIGNER 100 / OWNER 1000 ETH

# 4. Restart du bridge (AVEC secrets provisionnés !)
docker restart rock-paper-scissors-bridge-1

# 5. Vérifications finales
docker logs rock-paper-scissors-bridge-1 --since 1m | Select-String "ABI valid|reprend|synced"
docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:default   # attendu : 0
(Invoke-WebRequest http://127.0.0.1:8001/api/health -UseBasicParsing).Content             # {"status":"OK"}
# → puis deprovision
node secrets-manager.mjs deprovision
```

### Les symptômes → diagnostic express

| Symptôme | Cause | Remède |
|---|---|---|
| Container crash-loop `invalid private key` | mount fantôme (deprovision puis restart) | provision + `--force-recreate` |
| Bridge log `Access denied 'rps_app' (password: NO)` | idem | idem |
| `ls -la /run/secrets/` → `?????????` | bind mount stale après deprovision | `--force-recreate` (provisionné) |
| Bots figés, fight `waiting_for_result`, bridge "Only owner..." | wallets de service à 0 ETH | `fund_services.cjs` |
| Marketplace/game mute (aucun event) | sync_states > blockNumber | reset sync_states + restart bridge |
| `provision` → `EISDIR: ... \.secrets\X` | répertoires fantômes .secrets | nettoyer les dossiers (start.ps1 le fait) |
| Build docker bloqué sur `npm ci` | MTU WSL2 (VPN Huawei) | `wsl -d docker-desktop -- ip link set dev eth0 mtu 1400` |
| Offre marketplace invisible | ABI corrompue (§18.2) | auto-réparé au boot ; sinon fix_marketplace_abi.cjs |

**Scripts bp-test** (`C:\Users\GWX1223153\AppData\Local\Temp\opencode\bp-test\`) : `fund_services.cjs`,
`check_balances.cjs`, `reset_indexer_marketplace.sh`, `reset_indexer_game.sh`, `check_trades.sh`,
`check_db_state.sh`, `create_humans.mjs`, `fund_snt.cjs`, `fix_marketplace_abi.cjs`, `fix_addresses.cjs`,
`verify_config.cjs`, `test_config_repair.cjs`. **NB : ces scripts sont volatiles (Temp)** — à copier en lieu sûr
ou dans le dépôt si on veut les pérenniser.

---

## 1. Démarrer la stack complète

```powershell
# 1a. Provisionner les secrets (les containers les lisent via /run/secrets/)
node secrets-manager.mjs provision

# 1b. Démarrer la stack (tous les services + 2 répliques du worker limits)
docker compose up -d --scale queue-worker-limits=2

# 1c. Vérifier l'état
docker ps --filter name=rock-paper-scissors --format "{{.Names}} | {{.Status}}"
```

**Services attendus (11) :** `app`, `worker`, `queue-worker`, `queue-worker-limits` (×2), `reverb`, `redis`, `db`,
`blockchain`, `bridge`, `init-db` (one-shot, Exited).

**Ports :** app `127.0.0.1:8001` (interne 8080), reverb `127.0.0.1:8008`, blockchain `127.0.0.1:8546` (interne 8545), db `127.0.0.1:3307`.

---

## 2. Réinitialiser la blockchain (état vierge) et redéployer les contrats

```powershell
# 2a. Redémarrer le noeud Hardhat (le réseau Hardhat est éphémère : restart = état vierge)
docker restart rock-paper-scissors-blockchain-1
Start-Sleep -Seconds 6

# 2b. Recompiler (au cas où le .sol a changé)
docker exec rock-paper-scissors-blockchain-1 sh -c "cd /app && npx hardhat compile"
```

### 2c. Déployer les 3 contrats + initialiser les rôles + funder les wallets de service

Copier ce script dans le conteneur blockchain (qui a `ethers` + les 3 secrets), puis l'exécuter :

```powershell
# Écrire un script deploy_reset.cjs et le copier (voir §2d), ou l'exécuter en une fois
docker cp deploy_reset.cjs rock-paper-scissors-blockchain-1:/app/deploy_reset.cjs
docker exec rock-paper-scissors-blockchain-1 sh -c "cd /app && node deploy_reset.cjs"
```

### 2d. Contenu de `deploy_reset.cjs` (réutilisable)

```javascript
// deploy_reset.cjs — déploie les 3 contrats + init roles + fund les wallets de service
const { JsonRpcProvider, Wallet, Contract, formatEther, parseEther } = require('ethers');
const hre = require('hardhat');
const fs = require('fs');

function readSecret(name) {
  try { return fs.readFileSync('/run/secrets/' + name, 'utf8').trim(); } catch (e) { return undefined; }
}

async function main() {
  const provider = new JsonRpcProvider('http://localhost:8545');
  // Compte Hardhat #0 (pré-fundé 10000 ETH)
  const funder = new Wallet('0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80', provider);

  // 1. Déployer Battlepool
  const Battlepool = await hre.ethers.getContractFactory('Battlepool');
  const battlepool = await Battlepool.deploy();
  await battlepool.waitForDeployment();
  console.log('Battlepool:', await battlepool.getAddress());

  // 2. Déployer SNTToken
  const SNTToken = await hre.ethers.getContractFactory('SNTToken');
  const snt = await SNTToken.deploy();
  await snt.waitForDeployment();
  console.log('SNTToken:', await snt.getAddress());

  // 3. Déployer MarketplaceEscrow (requiert l'adresse du token + owner)
  const MarketplaceEscrow = await hre.ethers.getContractFactory('MarketplaceEscrow');
  const escrow = await MarketplaceEscrow.deploy(await snt.getAddress(), funder.address);
  await escrow.waitForDeployment();
  console.log('MarketplaceEscrow:', await escrow.getAddress());

  // 4. Initialiser les rôles du Battlepool (4 args !)
  const ownerPk = readSecret('game_wallet_pk');
  const signerPk = readSecret('signer_wallet_pk');
  const payoutPk = readSecret('payout_operator_pk');
  const ownerWallet = new Wallet(ownerPk, provider);
  const signerAddr = new Wallet(signerPk).address;
  const payoutAddr = new Wallet(payoutPk).address;
  const bp = new Contract(await battlepool.getAddress(), [
    'function initializeRoles(address _owner, address _signer, address _payoutOperator, address payable _devWallet)',
    'function setDefaultPoolMaxSize(uint256)',
  ], ownerWallet);
  await (await bp.initializeRoles(ownerWallet.address, signerAddr, payoutAddr, ownerWallet.address)).wait();
  console.log('Roles initialisés');
  await (await bp.setDefaultPoolMaxSize(2)).wait();
  console.log('Pool max size = 2');

  // 5. Funder les wallets de service (remis à 0 par le restart)
  for (const [name, pk] of [['PAYOUT_OPERATOR', payoutPk], ['SIGNER', signerPk], ['OWNER', ownerPk]]) {
    const w = new Wallet(pk, provider);
    const bal = await provider.getBalance(w.address);
    if (bal < parseEther('10')) {
      await (await funder.sendTransaction({ to: w.address, value: parseEther('100') })).wait();
      console.log(`${name} fundé (100 ETH)`);
    }
  }
}

main().catch(e => { console.error(e); process.exit(1); });
```

> **Note :** les 3 contrats déployés en premier par le compte #0 obtiennent toujours les mêmes adresses
> (Battlepool `0x5FbDB231...`, SNT `0xe7f1725E...`, Escrow `0x9fE46736...`), donc pas besoin de changer `.env`.

---

## 3. Rafraîchir la base de données

```powershell
# Écrire un script reset_db.sh local, le copier puis l'exécuter
docker cp reset_db.sh rock-paper-scissors-app-1:/tmp/reset_db.sh
docker exec rock-paper-scissors-app-1 sh /tmp/reset_db.sh
```

### Contenu de `reset_db.sh`

```sh
#!/bin/sh
export APP_KEY="$(cat /run/secrets/app_key)"
export DB_PASSWORD="$(cat /run/secrets/db_app_password)"
export DB_APP_PASSWORD="$(cat /run/secrets/db_app_password)"
export MYSQL_PASSWORD="$(cat /run/secrets/mysql_password)"
export INTERNAL_API_SECRET="$(cat /run/secrets/internal_api_secret)"
export REVERB_APP_KEY="$(cat /run/secrets/reverb_app_key)"
export REVERB_APP_SECRET="$(cat /run/secrets/reverb_app_secret)"
php artisan migrate:fresh --seed --force
```

> Le seed ne crée **AUCUN bot** — juste l'admin (Hardhat #0, id=1). Les bots sont créés par le script §4.

---

## 4. Redémarrer le bridge et les workers (après reset)

```powershell
docker restart rock-paper-scissors-bridge-1 rock-paper-scissors-queue-worker-1 rock-paper-scissors-queue-worker-limits-1
Start-Sleep -Seconds 5
```

---

## 5. Peupler les bots (via `simulation_bots.js`)

**Comptes réservés à l'humain :** Hardhat #0, #1, #2, #99 → les bots doivent utiliser les AUTRES index.

```powershell
# Depuis G:\DEV\PHP\rock-paper-scissors (le script tourne sur l'HÔTE Windows)
$env:LARAVEL_API_URL = "http://127.0.0.1:8001/api"
$env:FUJI_RPC_URL = "http://127.0.0.1:8546"
$env:BASE_BET = "0.01"

# 20 bots, en partant de l'index 3 (on saute #0, #1, #2 ; #99 est hors plage)
node smart_contracts/simulation_bots.js 20 3
```

**Paramètres :** `node smart_contracts/simulation_bots.js <nombreBots> <indexDépart>`

- Les bots utilisent les comptes Hardhat dérivés de la mnémonique `test test ... junk`.
- Chaque bot dépose `base_bet (0.01) × security_coefficient (1000) + fee 2.5%` = **10.25 ETH**.
- Le script fait : auth → IPFS (pre-moves) → `submitPremoveCID` on-chain.

---

## 6. Désactiver l'autoplay sur les comptes humains réservés (CRITIQUE)

Après création des bots, si vous jouez avec un compte qui était un bot (ou si un compte humain a `autoplay_active=1`),
le moteur le recycle en continu. Désactiver :

```powershell
# Script disable_autoplay.sh à copier dans le conteneur app
docker cp disable_autoplay.sh rock-paper-scissors-app-1:/tmp/disable_autoplay.sh
docker exec rock-paper-scissors-app-1 sh /tmp/disable_autoplay.sh
```

### Contenu de `disable_autoplay.sh` (adapter le wallet)

```sh
#!/bin/sh
export APP_KEY="$(cat /run/secrets/app_key)"
export DB_PASSWORD="$(cat /run/secrets/db_app_password)"
export DB_APP_PASSWORD="$(cat /run/secrets/db_app_password)"
export MYSQL_PASSWORD="$(cat /run/secrets/mysql_password)"
export INTERNAL_API_SECRET="$(cat /run/secrets/internal_api_secret)"
export REVERB_APP_KEY="$(cat /run/secrets/reverb_app_key)"
export REVERB_APP_SECRET="$(cat /run/secrets/reverb_app_secret)"
php artisan tinker --execute="
\$u = \App\Models\User::where('wallet_address','0x70997970C51812dc3A010C7d01b50e0d17dc79C8')->first();
if (\$u) {
  \$u->autoplay_active = false;
  \$u->status = 'stopped';
  \$u->session_started = false;
  \$u->pool_id = null;
  \$u->save();
  echo 'Autoplay désactivé pour user #' . \$u->id . PHP_EOL;
}
"
```

---

## 7. Compiler le frontend (si modification des sources JS/Vue)

Le frontend servi est le **bundle Vite** (`/build/assets/app.js`), pas les sources. Toute modif dans
`resources/js/` nécessite :

```powershell
# Sur l'hôte (node 25 + npm 11 disponibles sur l'hôte, PAS dans le conteneur app)
npm run build

# Copier le bundle dans le conteneur app
docker cp "G:\DEV\PHP\rock-paper-scissors\public\build" rock-paper-scissors-app-1:/var/www/html/public/build
```

---

## 8. Comptes Hardhat réservés à l'humain

| Hardhat # | Adresse | Usage |
|---|---|---|
| #0 | `0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266` | Réservé humain (aussi compte déployeur/admin) |
| #1 | `0x70997970C51812dc3A010C7d01b50e0d17dc79C8` | Réservé humain |
| #2 | `0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC` | Réservé humain |
| #99 | `0x98d08079928fccb30598c6c6382abfd7dbfaa1cd` | Réservé humain |

**Mnémonique MetaMask :** `test test test test test test test test test test test junk`
**RPC :** `http://127.0.0.1:8546` — **Chain ID :** `31337`

---

## 9. Wallets de service (à funder après chaque reset blockchain)

| Rôle | Adresse | Clé (secret) |
|---|---|---|
| Owner (cold) | `0x4B35f60D18F2Caf3534bEcE3Df38FBc02c818E63` | `game_wallet_pk` |
| Signer | `0x8E22598DD062A086c580778eD7434D039E621E42` | `signer_wallet_pk` |
| PayoutOperator (hot) | `0x5aa8eb45a9F6F87D8c51c51ea2639559Df632ebd` | `payout_operator_pk` |

> Le bridge utilise `PAYOUT_OPERATOR_PK` (hot) pour `setUserLimits`/`payOut`/`updateUserBalance`. Il n'a PAS la clé
> owner. Si `SyncUserLimitsJob` FAIL avec "Sender doesn't have enough funds", c'est que `PAYOUT_OPERATOR` est à sec (§2d, étape 5).

---

## 9bis. Distribuer les SNT aux comptes réservés

Le contrat `SNTToken` mint 1M SNT au compte **#100** (`0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D`), PAS au déployeur #0.
Pour funder les comptes réservés, signer avec la PK de #100 (dérivée de la mnémonique à l'index 100).

```powershell
# Écrire un script fund_snt.cjs et le copier dans le conteneur blockchain
docker cp fund_snt.cjs rock-paper-scissors-blockchain-1:/smart_contracts/fund_snt.cjs
docker exec rock-paper-scissors-blockchain-1 sh -c "cd /smart_contracts && node fund_snt.cjs"
```

### Contenu de `fund_snt.cjs`

```javascript
const { JsonRpcProvider, Contract, formatEther, parseEther, HDNodeWallet } = require('ethers');

async function main() {
  const provider = new JsonRpcProvider('http://localhost:8545');
  const SNT_ADDRESS = '0x0165878A594ca255338adfa4d48449f69242Eb8F'; // = contracts.snt.address dans config.js
  const abi = ['function balanceOf(address) view returns (uint256)', 'function transfer(address to, uint256 amount) returns (bool)'];

  // Owner du SNT = compte Hardhat #100 (INITIAL_OWNER_ADDRESS codé en dur dans SNTToken.sol)
  const hd = HDNodeWallet.fromPhrase(
    "test test test test test test test test test test test junk",
    undefined,
    "m/44'/60'/0'/0/100"
  );
  const ownerWallet = hd.connect(provider);
  const snt = new Contract(SNT_ADDRESS, abi, ownerWallet);

  const targets = {
    '#1': '0x70997970C51812dc3A010C7d01b50e0d17dc79C8',
    '#2': '0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC',
    '#99': '0x98d08079928fccb30598c6c6382abfd7dbfaa1cd',
  };

  for (const [label, addr] of Object.entries(targets)) {
    const bal = await snt.balanceOf(addr);
    if (bal > 0n) { console.log(`${label}: déjà ${formatEther(bal)} SNT`); continue; }
    const tx = await snt.transfer(addr, parseEther('100'));
    await tx.wait();
    console.log(`${label}: ${formatEther(await snt.balanceOf(addr))} SNT`);
  }
}
main().catch(e => console.error('ERR:', e.message));
```

> **Pièges :** (1) l'adresse SNT réelle est `0x0165878A...` (celle de `config.js`), pas `0xe7f1725E...` ; (2) ethers
> peut renvoyer "nonce too low" entre 2 transferts rapprochés → relancer par compte individuellement.

---

## 10. Checklist de test (ordre recommandé)

1. `provision` → `docker compose up -d --scale queue-worker-limits=2` → vérifier `docker ps`
2. Restart blockchain → **le boot auto-déploie + resynchronise les ABIs** (chercher `✅ ABIs synced from artifacts: game(108), snt(25), marketplace(26)` dans les logs)
3. `bp-test\fund_services.cjs` dans le container blockchain (wallets de service — idempotent, relancer si "nonce too low")
4. Vérifier `eth_blockNumber` vs `blockchain_sync_states` : si chaîne plus jeune → `bp-test\reset_indexer_marketplace.sh` puis restart bridge
5. `reset_db.sh` (migrate:fresh --seed)
6. Restart bridge + workers
7. `simulation_bots.js 20 3` (20 bots, skip #0/#1/#2/#99)
8. `bp-test\create_humans.mjs` (pré-créer comptes #1/#2/#99 avec autoplay=0) — remplace l'étape 6 historique
9. `bp-test\fund_snt.cjs` (100 SNT aux comptes #1/#2/#99)
10. Vérifier : `queues:default = 0`, `SyncUserLimitsJob = DONE`, health API `{"status":"OK"}`, log bridge `✅ [BRIDGE] ABIs validées`
11. Ouvrir http://127.0.0.1:8001 → connecter MetaMask (compte réservé) → jouer
12. En fin de session : `node secrets-manager.mjs deprovision`

---

## 11. Diagnostics rapides (réutilisables)

```powershell
# Taille des queues
docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:default
docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:limits

# Logs workers (broadcasts vs limits)
docker logs rock-paper-scissors-queue-worker-1 --tail 10
docker logs rock-paper-scissors-queue-worker-limits-1 --tail 10

# Santé API
Invoke-WebRequest -Uri http://127.0.0.1:8001/api/health -UseBasicParsing | Select-Object -ExpandProperty Content

# Nombre de users/bots/fights (via un script .sh copié dans le conteneur app)
docker exec rock-paper-scissors-app-1 sh /tmp/db_check.sh
```

---

## 12. Pièges récurrents (résumé — détail dans HANDOVER.md §17-§18)

1. **`SyncUserLimitsJob` FAIL** → `PAYOUT_OPERATOR` à 0 ETH → funder via `bp-test\fund_services.cjs` (HANDOVER §17.1, §18.7).
2. **"Amount exceeds user balance"** → solde on-chain < solde DB → `updateUserBalance` manuel (HANDOVER §17.2).
3. **Overlay combat bloquant le claim** → autoplay resté actif + bug UI `hideCombatOverlay` (HANDOVER §17.3).
   Prévention : pré-créer les comptes humains via `bp-test\create_humans.mjs` (autoplay=0 garanti).
4. **Frontend pas à jour** → `npm run build` + `docker cp public/build` (HANDOVER §17.4).
5. **`docker exec sh -c` cassé sous PowerShell** → écrire un `.sh` + `docker cp` ; si docker cp échoue, fallback stdin : `Get-Content -Raw f | docker exec -i CONT sh -c "cat > /tmp/x"` (HANDOVER §18.4).
6. **Secrets illisibles** → provisionner AVANT, déprovisionner APRÈS (HANDOVER §17.6). Un restart APRÈS deprovision = crash bridge (`Access denied rps_app`).
7. **Pas de clé owner dans le bridge** → utiliser le conteneur `blockchain` pour l'admin (HANDOVER §17.7).
8. **`initializeRoles` = 4 arguments** (HANDOVER §17.8).
9. **SNT minté au compte #100, pas #0** → `bp-test\fund_snt.cjs` (HANDOVER §17.10).
10. **5 fonctions `onlyPayoutOperator`** (fix 25/08, HANDOVER §17.11).
11. **Marketplace UI vide** (offre créée mais invisible) → ABI marketplace écrasée par l'ABI Battlepool dans `config.js`.
    Auto-réparé au boot blockchain (`full_deploy.js` resynchronise les ABIs depuis les artifacts, HANDOVER §18.3) ;
    le bridge fail-fast si ABI invalide. Vérif : `node -e "require('./smart_contracts/config.js').contracts.marketplace.abi.filter(x=>x.type==='event').map(x=>x.name)"`.
    Réparation manuelle de secours : `bp-test\fix_marketplace_abi.cjs` + `fix_addresses.cjs` + reset sync_states marketplace/snt.
12. **Après chaque restart blockchain** (HANDOVER §18.4) : (a) comparer `eth_blockNumber` et `blockchain_sync_states.last_processed_block` — si la chaîne est plus jeune, reset sync_states ; (b) re-funder les wallets de service ; (c) restart bridge après. Le boot blockchain affiche `✅ ABIs synced from artifacts: game(108), snt(25), marketplace(26)`.
13. **`readFileSync` Node sur bind mount Windows depuis container** → `ENODATA` possible ; le pattern copie-/tmp est implémenté dans `full_deploy.js` (ne pas régresser).
14. **ethers "nonce too low"** en boucle de funding → relancer le script idempotent.
