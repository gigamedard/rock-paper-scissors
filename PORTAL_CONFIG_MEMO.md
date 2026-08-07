# 🎮 PORTAL UNIFIÉ — MÉMOIRE DE CONFIGURATION

> **Date** : 2026-08-05
> **Statut** : ✅ VALIDÉ — Portail fonctionnel, Marketplace testé par l'utilisateur
> **Ce document est la référence absolue** pour relancer/comprendre l'intégration des 2 applications dans un seul portail.

---

## 1. Vue d'ensemble de l'architecture

```
                        ┌─────────────────────────────────┐
                        │    bp-proxy (nginx) PORT 8090   │
                        │    ========= PORTAL UNIFIÉ ===== │
                        │  /          → App 1 (8001)      │
                        │  /portal    → portal.html       │
                        │  /app2/     → App 2 (8080)      │
                        └────────┬───────────┬────────────┘
                                 │           │
                 ┌───────────────┘           └───────────────┐
        ┌────────▼─────────┐                     ┌───────────▼─────────┐
        │ APP 1 BATTLEPOOL │                     │ APP 2 WEB3 COMBAT   │
        │ (Laravel+Octane) │                     │ (Laravel+artisan)   │
        ├──────────────────┤                     ├─────────────────────┤
        │ Web    : 8001    │                     │ API    : 8000       │
        │ Reverb : 8008    │                     │ Reverb : 8081       │
        │ Blk    : 8546    │                     │ Front  : 8080       │
        │ DB     : 3307    │                     │ Blk    : 8545       │
        │ Redis  : 6379    │                     │ DB     : 3306       │
        └──────────────────┘                     └─────────────────────┘
```

**Principe clé** : le proxy 8090 sert les deux apps sur la **même origine** → `localStorage`
(token auth + `user_locale`) partagé sans effort entre les deux apps.

---

## 2. ⚠️ LES DEUX RÈGLES D'OR (ne JAMAIS casser)

### Règle 1 — LES DEUX BLOCKCHAINS DOIVENT AVOIR chainId = 31337

| App | RPC (hôte) | chainId | Fichier config |
|---|---|---|---|
| App 1 | `http://127.0.0.1:8546` | **31337** | `battlepool/hardhat.config.js` (`networks.hardhat.chainId`) |
| App 2 | `http://127.0.0.1:8545` | **31337** | défaut Hardhat (ne pas toucher) |

> 🔴 **HISTORIQUE** : App 1 était en `1337` → MetaMask rejetait toutes les tx
> (`Trying to send a raw transaction with an invalid chainId. The expected chainId is 31337`).
> **CORRECTION** : `battlepool/hardhat.config.js` ligne ~24 → `chainId: 31337`.
> ⚠️ Ne pas le remettre à 1337 ! Un seul réseau MetaMask 31337 suffit pour les 2 apps.

### Règle 2 — LE FRONTEND APP 1 DOIT POINTER VERS 8546 (PAS 8545)

`public/js/config.js` → `HARDHAT_RPC: "http://127.0.0.1:8546"`

> 🔴 **HISTORIQUE** : le conteneur servait un `config.js` périmé en `8545` (= blockchain App 2)
> → `allowance` renvoyait `0x` / contrat non résolu.
> **CORRECTION** : mis à jour vers `8546` dans la source + `docker cp` dans le conteneur.

---

## 3. Configuration MetaMask (côté navigateur)

| Réseau | RPC URL | Chain ID | Symbole |
|---|---|---|---|
| **Battlepool Hardhat** (App 1) | `http://127.0.0.1:8546` | **31337** | ETH |
| **Combat Game Hardhat** (App 2) | `http://127.0.0.1:8545` | **31337** | ETH |

> ⚠️ Changer de réseau selon l'app testée dans le portail.
> L'ancien réseau `AVAKZ` (chainId 1337) était **verrouillé** dans MetaMask → impossible à
> modifier → à supprimer/recréer. Le champ Chain ID grisé = réseau ajouté programmatiquement.

---

## 4. Contrats déployés (adresses Hardhat déterministes)

### App 1 — Battlepool (sur nœud 8546, chainId 31337)
| Contrat | Adresse |
|---|---|
| Battlepool (game) | `0x5FbDB2315678afecb367f032d93F642f64180aa3` |
| MarketplaceEscrow | `0x5FC8d32690cc91D4c39d9d3abcBD16989F875707` |
| SNTToken (Giga Special Token, "SNT") | `0xDc64a140Aa3E981100a9becA4E685f962f0cF6C9` |

> Les adresses sont déterministes (Hardhat) → **identiques** avant/après le changement de chainId.
> Endpoint serveur : `GET /api/artefacts` renvoie ABI + adresses (utilisé par le frontend).

### App 2 — CombatGame (sur nœud 8545)
| Contrat | Adresse |
|---|---|
| CombatGame | `0x5FbDB2315678afecb367f032d93F642f64180aa3` |

> ⚠️ Même adresse par défaut Hardhat pour un contrat DIFFÉRENT — pas de conflit car chaînes séparées.

---

## 5. Wallets & Tokens

| Wallet | Adresse | Solde (2026-08-07) |
|---|---|---|
| Compte Hardhat #0 | `0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266` | **3 SNT** + ETH — ⚠️ **spammé par le worker** (nonce ~29832, croît en continu) : NE PAS l'utiliser pour tester le marketplace |
| **Wallet utilisateur (session)** | `0x70997970C51812dc3A010C7d01b50e0d17dc79C8` | **953 SNT** + ETH — nonce stable (7) : ✅ **compte de test MARKETPLACE** |
| Owner SNTToken | `0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D` | 999 000 SNT (mint initial 1M) |

> 🔑 Clé privée compte #0 (standard Hardhat) : `***REMOVED***`
> 🔑 Clé privée compte #1 (standard Hardhat) : `***REMOVED***`
> 🔴 **PROCÉDURE DE TEST MARKETPLACE (recommandée)** :
> 1. Dans MetaMask, importer la clé privée du **compte #1** (ci-dessus) et l'activer.
> 2. Se connecter au portail avec ce compte (le wallet utilisé par la session détermine le user DB).
> 3. Créer une offre : Approve puis Create (le nonce frais est géré automatiquement par `_sendWithFreshNonce`).
> ⚠️ Après un **reset/rebuild** de la blockchain App 1, les SNT sont perdus → **re-transférer** (compte #0 → #1).

---

## 6. Fichiers du portail (à connaître)

| Fichier | Rôle |
|---|---|
| `public/portal.html` | Le portail unifié (langue + wallet + menu + iframe popup) — **assets inline** (restylé DA jeu 2026-08-07) |
| `routes/web.php` | Route `GET /portal` (AJOUTÉE avant le catch-all `/{any}`) |
| `proxy/nginx.conf` | Config du proxy nginx unifié (root → 8001, /app2/ → 8080) |

### Route /portal (dans routes/web.php, AVANT le catch-all)
```php
Route::get('/portal', function () {
    return file_get_contents(public_path('portal.html'));
})->name('portal');
```

---

## 7. Procédure de RELANCE complète

```powershell
# 1. Démarrer Docker Desktop (s'il ne tourne pas)
Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"

# 2. Les conteneurs redémarrent automatiquement (restart: unless-stopped)
#    Vérifier : docker ps -a  → tous "Up"

# 3. Conteneurs manquants à relancer manuellement :
docker start bp-proxy

# 4. Après un REBUILD de l'image blockchain App 1 (loss of chainId config) :
#    - Ré-appliquer le chainId 31337 si l'image a été rebuildée avec 1337
docker cp "G:\DEV\PHP\rock-paper-scissors\battlepool\hardhat.config.js" rock-paper-scissors-blockchain-1:/app/hardhat.config.js
docker restart rock-paper-scissors-blockchain-1
docker restart rock-paper-scissors-bridge-1   # (crash "network changed: 1337 => 31337")

# 5. Après un REBUILD de l'image app-1 : (⚠️ PLUS NÉCESSAIRE depuis 2026-08-07)
#    L'image inclut désormais le portail restylé, config.js (8546) ET le build Vite.
#    Pour régénérer l'image (ex: nouveau restyle) :
#    docker compose build app
#    docker compose up -d --force-recreate app
#    NB : si le package.json a changé, régénérer d'abord le lock avec npm 11 (node >= 22) :
#    npm install --no-audit --no-fund   (node 24 recommandé)

# 6. Après un reset de la blockchain App 1 : re-transférer les SNT
node "C:\Users\GWX122~1\AppData\Local\Temp\opencode\transfer_snt.js"
```

---

## 8. Tests de validation (à re-exécuter après tout rebuild)

```powershell
# Portail + les deux apps via proxy
Invoke-WebRequest http://127.0.0.1:8090/portal   # → 200 (portal)
Invoke-WebRequest http://127.0.0.1:8090/         # → 200 (BATTLEPOOL | Arena)
Invoke-WebRequest http://127.0.0.1:8090/app2/    # → 200 (Web3 Combat Game)

# Wallet challenge App 1 (POST !)
Invoke-WebRequest http://127.0.0.1:8090/api/wallet/generate-message -Method POST -ContentType "application/json" -Body '{"wallet_address":"0x1234567890abcdef1234567890abcdef12345678"}'
# → HTTP 200 + JSON {"message":"Sign this message to verify your wallet: ..."}

# chainId des deux nœuds = 31337
# POST http://127.0.0.1:8546  eth_chainId → 0x7a69
# POST http://127.0.0.1:8545  eth_chainId → 0x7a69
```

---

## 9. Problèmes connus (non bloquants) & TODO

| # | Problème | Statut |
|---|---|---|
| 1 | Latence API App 2 ~500ms (php artisan serve + bind-mount Windows) | ⚠️ connu |
| 2 | Indexer App 2 : crash-loop au boot (ECONNREFUSED) puis stable | ⚠️ connu |
| 3 | StackOverflow sur re-déploiement contrat App 2 (`<UnrecognizedContract>`) | ⚠️ connu |
| 4 | Bridge App 1 : boucle "Catching up" (ne rattrape jamais le tip) | ⚠️ connu |
| 5 | `web3-core.js` App 1 : switch WalletConnect force encore `0x539` (1337) → à corriger si WalletConnect utilisé | TODO |
| 6 | ~~Persistance `portal.html` + `config.js` perdus au rebuild image App 1~~ | ✅ **RÉSOLU 2026-08-07** : le rebuild image inclut désormais TOUS les fichiers restylés (DA jeu) + `config.js` en 8546. Le Dockerfile builder est passé en `node:24-slim` (npm 11) car le `package-lock.json` est généré par npm 11 et exige `node >= 22.12` (puppeteer-core@25). |
| 7 | Automatisation switch réseau MetaMask dans le portail (`wallet_switchEthereumChain` → `0x7a69`) | TODO optionnel |
| 8 | ~~`Nonce too low` sur le marketplace~~ | ✅ **RÉSOLU 2026-08-07 (partiellement côté code)** : le worker/bridge spame le compte #0 (`0xf39F...`) ~1 tx/5s en boucle (automining) → nonce nœud à ~29832 vs MetaMask périmé. Fix dans `marketplace.js` : `_sendWithFreshNonce()` lit le nonce frais (`getTransactionCount pending`) à chaque envoi + retry sur `NONCE_EXPIRED`. ⚠️ Pour tester SEREINEMENT : utiliser le **compte #1** (`0x7099...`, nonce stable = 7, 953 SNT) au lieu du compte #0 (3 SNT + nonce spammé). |
| 9 | ~~403 "Accès réservé aux influenceurs" sur `/api/influencer/dashboard`~~ | ✅ **RÉSOLU 2026-08-07** : la table `influencers` était VIDE (seed commenté). Fait via la route existante `POST /api/influencer/join-test` (non destructive) → pool FR + influencer (user id=1) + stats créés. Dashboard 200. |
| 10 | ~~Rogue PHP XAMPP squatte `127.0.0.1:8090` → 404/500 au lieu du proxy Docker~~ | ✅ **RÉSOLU 2026-08-07 (RÉCURRENT !)** : `php -S 127.0.0.1:8090 -t public` (XAMPP) reprend le port à chaque session. **Fix** : `Stop-Process -Id (PID du php.exe sur 8090) -Force` puis `Get-NetTCPConnection -LocalPort 8090` doit montrer uniquement `::` (com.docker.backend) et `::1` (wslrelay). Toujours tester via `localhost:8090` ou `127.0.0.1:8090` APRÈS vérification qu'il n'y a plus de listener php. |

---

## 10. Résumé des erreurs debuggées (référence rapide)

| Erreur | Signification | Solution |
|---|---|---|
| `invalid chainId, expected 31337` (RPC 0x539) | Nœud App 1 en 1337 OU réseau MetaMask en 1337 | chainId 31337 partout |
| `could not decode result data (value="0x")` sur allowance | mauvais RPC (8545 au lieu de 8546) OU contrat absent | config.js → 8546 |
| `execution reverted 0xe450d38c` = `ERC20InsufficientBalance` | wallet à 0 SNT | transférer des SNT (script) |
| `could not coalesce error ... eth_sendTransaction` | chaîne ethers v6 vers MetaMask, erreur RPC sous-jacente | dépend de l'erreur réelle (ci-dessus) |
| `network changed: 1337 => 31337` (bridge crash) | bridge démarré AVANT le changement de chainId | `docker restart rock-paper-scissors-bridge-1` |
| `Nonce too low. Have 29668, want 29689` (createOffer/approve) | le worker spame le compte #0 (nonce nœud >> nonce MetaMask) | utiliser le **compte #1** (`0x7099...`) pour tester + fix `_sendWithFreshNonce()` dans `marketplace.js` (déjà déployé) |
| 404/500 Apache au lieu du portail sur `localhost:8090` | rogue `php -S 127.0.0.1:8090` (XAMPP) a repris le port | `Stop-Process` du php.exe sur 8090 ; vérifier que seul Docker écoute |
| 403 "Accès réservé aux influenceurs" | pas d'enregistrement dans la table `influencers` | `POST /api/influencer/join-test` (avec Bearer token) |
