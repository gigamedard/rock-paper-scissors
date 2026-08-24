# GUIDE DE DEPLOIEMENT EN PRODUCTION
## Rock-Paper-Scissors Web3

> **Derniere mise a jour :** 24 aout 2026
> **Branche de securite :** `pentest/blackbox-2026-08-20`
> **Etat :** 21/21 vulnabilites corrigees (3 rounds de pentest black-box)

---

## 0. AVERTISSEMENT CRITIQUE

Ce guide decrit les etapes et points d'attention pour deployer cette application
en production. L'application a ete developpee et testee en local avec Hardhat
(reseau de test). Le deploiement en production sur Avalanche Fuji/Mainnet
necessite des **modifications specifiques** decrites ci-dessous.

**NE JAMAIS deployer en production sans avoir suivi CHAQUE etape de ce guide.**

---

## 1. CHECKLIST PRE-DEPLOIEMENT

### 1.1 Contrat Solidity (Battlepool.sol)

- [ ] **Remplacer le reseau Hardhat par Fuji/Mainnet**
  - `hardhat.config.js` : le network `hardhat` (chainId 31337, mnemonique publique, automine 2s) est pour le LOCAL uniquement
  - En production, utiliser le network `fuji` (chainId 43113) ou `mainnet` (chainId 43114)
  - Le contrat sera deploye sur le vrai reseau via `npx hardhat run full_deploy.js --network fuji`

- [ ] **Verifier `triggerPoolEmittedEventForTesting`**
  - Cette fonction est bloquee par `require(block.chainid == 31337)` en production
  - Sur Fuji (43113) ou Mainnet (43114), elle revertira systematiquement
  - C'est voulu : cette fonction ne doit JAMAIS etre accessible en production

- [ ] **Verifier les roles on-chain avant ouverture**
  - `owner` : clé froide (GAME_WALLET_PK) — doit etre une clé dediee, JAMAIS Hardhat #0
  - `signer` : clé chaude (SIGNER_WALLET_PK) — utilisee par le bridge pour signer les claims
  - `payoutOperator` : clé chaude (PAYOUT_OPERATOR_PK) — utilisee par le bridge pour payOut/batchPayOut
  - `devWallet` : adresse de reception des fees — doit etre une adresse controlee par l'equipe
  - **Ces 4 roles doivent etre des adresses DIFFERENTES**
  - Verifier avec : `contract.owner()`, `contract.signer()`, `contract.payoutOperator()`, `contract.devWallet()`

- [ ] **Verifier les parametres du contrat**
  - `feeBasisPoints` : 500 (5%) — cap a 1000 (10%) par `MAX_FEE_BASIS_POINTS`
  - `securityCoefficient` : 1000
  - `stagnantBlockLimit` : 100 blocks — ajuster selon le temps de block du reseau (Fuji ~2s, Mainnet ~1s)
  - `defaultMinCooldown` : 86400 (24h) — ajuster selon la strategie

- [ ] **Timelock 2 jours actif**
  - `transferOwnership`, `setSigner`, `setPayoutOperator`, `setDevWallet` necessitent queue + 2 jours d'attente
  - `cancelTimelock(opHash)` permet d'annuler une operation en attente
  - Surveiller les events `TimelockQueued` pour detecter une compromission

- [ ] **`initializeRoles()` fenetre de 100 blocks**
  - Cette fonction ne peut etre appelee qu'une fois, dans les 100 blocks suivant le deploiement
  - Elle bypass le timelock pour le setup initial
  - Apres 100 blocks, elle revertira — c'est voulu

### 1.2 Cles privees et secrets

- [ ] **Generer des cles NEUVES pour la production**
  - `GAME_WALLET_PK` (owner) : generer avec `node -e "const{Wallet}=require('ethers');console.log(Wallet.createRandom().privateKey)"`
  - `SIGNER_WALLET_PK` (signer) : generer une cle separate
  - `PAYOUT_OPERATOR_PK` (payoutOperator) : generer une cle separate
  - `MARKETPLACE_WALLET_PK` : generer une cle separate
  - **NE JAMAIS reutiliser les cles de test local** (elles ont ete exposees dans les rapports de pentest)

- [ ] **Importer les cles dans les secrets chiffres**
  ```bash
  # Sur le serveur de production (Linux) :
  node secrets-manager.mjs init           # genere la passphrase (GPG ou fichier 600)
  # Placer chaque cle dans .secrets/<name> (plaintext temporaire)
  echo "0x<VOTRE_CLE>" > .secrets/game_wallet_pk
  echo "0x<VOTRE_CLE>" > .secrets/signer_wallet_pk
  # etc.
  node secrets-manager.mjs encrypt        # chiffre en .enc, supprime le plaintext
  ```

- [ ] **Generer des secrets backend neufs**
  - `INTERNAL_API_SECRET` : `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`
  - `MYSQL_ROOT_PASSWORD` : `node -e "console.log(require('crypto').randomBytes(24).toString('base64url'))"`
  - `DB_APP_PASSWORD` : idem (separe du root)
  - `REVERB_APP_KEY` : `node -e "console.log(require('crypto').randomBytes(12).toString('hex'))"`
  - `REVERB_APP_SECRET` : `node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"`
  - `APP_KEY` : `php artisan key:generate` ou `node -e "console.log('base64:'+require('crypto').randomBytes(32).toString('base64'))"`

- [ ] **Fondre les wallets avec du vrai AVAX**
  - `GAME_WALLET_PK` (owner) : besoin d'AVAX pour les transactions admin (setFee, timelock, etc.)
  - `PAYOUT_OPERATOR_PK` : besoin d'AVAX pour les transactions payOut/batchPayOut
  - `SIGNER_WALLET_PK` : besoin d'AVAX uniquement si le bridge envoie des transactions signees (normalement non — il signe hors-chain)
  - `MARKETPLACE_WALLET_PK` : besoin d'AVAX pour les operations marketplace

### 1.3 Configuration environnement

- [ ] **`.env.staging` — secrets VIDES**
  - Toutes les valeurs sensibles doivent etre vides dans `.env.staging`
  - Les secrets sont injectes via Docker secrets (`/run/secrets/`)
  - Verifier : `grep -E "SECRET|PASSWORD|KEY=" .env.staging | grep -v "=$" | grep -v "null"` → doit retourner RIEN

- [ ] **`.env` — secrets VIDES**
  - Idem : aucune valeur secrete dans `.env`
  - Les scripts host chargent les secrets depuis `.secrets/*.enc` (dechiffres via DPAPI/GPG)

- [ ] **`APP_ENV=production`** (pas `staging` ni `local`)
  - Dans `.env.staging` (utilise par les containers) : `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL` : votre domaine HTTPS (ex: `https://rps.votredomaine.com`)

- [ ] **`ADMIN_WALLET_ADDRESS` et `ADMIN_PASSWORD`**
  - `ADMIN_WALLET_ADDRESS` : l'adresse wallet de l'administrateur (pas Hardhat #0)
  - `ADMIN_PASSWORD` : mot de passe admin fort
  - Sans ces valeurs, le seeder refuse de creer un admin en production

- [ ] **HTTPS / TLS**
  - L'application ecoute en HTTP sur le port 8080 (interne Docker)
  - En production, placer un reverse proxy (Nginx, Caddy, Traefik) avec TLS devant
  - Le reverse proxy termine le TLS et forward vers `127.0.0.1:8001`
  - **NE JAMAIS exposer le port 8080 directement sans TLS**

- [ ] **CORS**
  - Configurer `config/cors.php` pour n'autoriser que votre domaine
  - Ne pas utiliser `*` en production

### 1.4 Docker / Infrastructure

- [ ] **Ports binds a 127.0.0.1**
  - Tous les ports sont deja binds a `127.0.0.1` dans `docker-compose.yml`
  - En production avec reverse proxy, seul le port 80/443 du reverse proxy est expose
  - Verifier : `docker compose config | grep ports -A 1` → tous doivent etre `127.0.0.1:`

- [ ] **`security-entrypoint.sh` sur TOUS les containers PHP**
  - `app`, `reverb`, `queue-worker`, `queue-worker-limits` utilisent `security-entrypoint.sh`
  - Cet entrypoint lit `/run/secrets/` et exporte en variables d'environnement
  - Sans cet entrypoint, les secrets Docker ne sont PAS charges (bug Reverb du pentest round 3)

- [ ] **`db-entrypoint.sh` sur le container MySQL**
  - Lit `/run/secrets/mysql_password` et definit `MYSQL_ROOT_PASSWORD`
  - Sans cet entrypoint, MySQL ne demarrera pas (pas de mot de passe root)

- [ ] **Docker secrets definis**
  - `docker-compose.yml` section `secrets:` doit lister tous les fichiers `.secrets/*`
  - Verifier : `docker compose config | grep -A 1 "secrets:"` → tous les secrets definis

- [ ] **User MySQL `rps_app` (non-root)**
  - `init-db` cree automatiquement l'utilisateur `rps_app` avec droits limites
  - L'app utilise `rps_app` + `DB_APP_PASSWORD` (Docker secret)
  - Le mot de passe root n'est utilise que par `db` et `init-db`

- [ ] **Redis persistant en production**
  - En local : `appendonly no` (queues perdues au redemarrage — acceptable pour le dev)
  - En production : `appendonly yes` ou utiliser un Redis manage
  - Sinon les jobs en queue sont perdus au redemarrage

---

## 2. ETAPES DE DEPLOIEMENT

### 2.1 Sur le serveur de production (Linux)

```bash
# 1. Cloner le depot
git clone <repo> /opt/rock-paper-scissors
cd /opt/rock-paper-scissors
git checkout pentest/blackbox-2026-08-20  # ou la branche de release

# 2. Installer les dependances host
npm install  # pour secrets-manager.mjs
cd battlepool && npm ci && cd ..
cd smart_contracts && npm ci && cd ..

# 3. Initialiser le gestionnaire de secrets chiffres
node secrets-manager.mjs init

# 4. Creer les fichiers de secrets en clair (temporaire)
mkdir -p .secrets
# Generer et placer chaque secret :
echo "0x<Game_WALLET_PK>" > .secrets/game_wallet_pk
echo "0x<SIGNER_WALLET_PK>" > .secrets/signer_wallet_pk
echo "0x<PAYOUT_OPERATOR_PK>" > .secrets/payout_operator_pk
echo "0x<MARKETPLACE_WALLET_PK>" > .secrets/marketplace_wallet_pk
echo "<INTERNAL_API_SECRET>" > .secrets/internal_api_secret
echo "<MYSQL_ROOT_PASSWORD>" > .secrets/mysql_password
echo "<DB_APP_PASSWORD>" > .secrets/db_app_password
echo "<APP_KEY>" > .secrets/app_key
echo "<REVERB_APP_KEY>" > .secrets/reverb_app_key
echo "<REVERB_APP_SECRET>" > .secrets/reverb_app_secret

# 5. Chiffrer les secrets (supprime le plaintext)
node secrets-manager.mjs encrypt

# 6. Configurer .env.staging pour la production
# (voir section 1.3 — APP_ENV=production, APP_URL, ADMIN_WALLET_ADDRESS, etc.)
cp .env.staging.example .env.staging  # si existe, sinon editer directement
# Editer .env.staging : APP_ENV=production, APP_DEBUG=false, APP_URL=https://...

# 7. Deployer le contrat sur Fuji/Mainnet
# IMPORTANT : provisionner les secrets d'abord (le container blockchain les lit)
node secrets-manager.mjs provision
cd battlepool
npx hardhat run full_deploy.js --network fuji  # ou mainnet
cd ..
# Noter les adresses des contrats deployes (Battlepool, SNTToken, MarketplaceEscrow)
# Les mettre dans .env.staging : BATTLEPOOL_ADDRESS, SNT_TOKEN_ADDRESS, MARKETPLACE_ESCROW_ADDRESS

# 8. Reconstruire les images Docker
docker compose build app bridge reverb
docker build -f Dockerfile.blockchain -t rock-paper-scissors-blockchain:latest .

# 9. Demarrer le stack (provision → up → deprovision)
node secrets-manager.mjs provision
docker compose up -d
# Attendre que tous les containers soient healthy...
node secrets-manager.mjs deprovision

# 10. Verifier
curl http://127.0.0.1:8001/api/health
# Doit retourner : {"status":"OK","database":"OK","cache":"OK"}
```

### 2.2 Reverse proxy (Nginx/Caddy/Traefik)

```nginx
# Exemple Nginx
server {
    listen 443 ssl http2;
    server_name rps.votredomaine.com;

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # API Laravel
    location / {
        proxy_pass http://127.0.0.1:8001;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket Reverb
    location /app/ {
        proxy_pass http://127.0.0.1:8008;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 86400;
    }
}

server {
    listen 80;
    server_name rps.votredomaine.com;
    return 301 https://$server_name$request_uri;
}
```

---

## 3. POINTS D'ATTENTION CRITIQUES EN PRODUCTION

### 3.1 NE PAS FAIRE

| Action | Risque |
|---|---|
| Reutiliser les cles de test local | Les cles ont ete exposees dans les rapports de pentest |
| Utiliser Hardhat #0 comme owner | Cle publique connue, n'importe qui peut admin le contrat |
| Mettre `APP_DEBUG=true` | Fuit d'informations detaillees (stack traces, config) |
| Mettre `APP_ENV=local` | Active les routes dev-login, TEST_BYPASS, debug |
| Exposer un port sans TLS | Interceptation des tokens, signatures, etc. |
| Utiliser `docker compose restart` apres deprovision | Les secrets ne sont plus sur le host → containers casses |
| Mettre `feeBasisPoints > 1000` | Revert du contrat (cap 10%) |
| Appeler `triggerPoolEmittedEventForTesting` sur Fuji/Mainnet | Revert (chainId != 31337) |
| Laisser `REDIS_PASSWORD=null` en prod | Acces Redis sans authentification |
| Deployer sans reverse proxy TLS | Toutes les communications sont en clair |

### 3.2 A FAIRE

| Action | Pourquoi |
|---|---|
| Utiliser `.\start.ps1` (ou l'equivalent Linux) | Provisionne, demarre, deprovisionne automatiquement |
| Surveiller les events `TimelockQueued` | Detecter une compromission de la cle owner (2 jours pour reagir) |
| Backuper `.secrets/*.enc` + passphrase | Perte = impossibilite de relancer l'app |
| Backuper le volume `db_data` | Perte = perte de toutes les donnees utilisateurs |
| Monitoring des queues Redis | `default` doit rester ~0, `limits` peut croitre (inoffensif) |
| Monitoring du bridge | Si le bridge tombe, le jeu s'arrete (plus de fights, payouts, syncs) |
| Rotation periodique des cles signer/payoutOperator | Via `queueSetSigner` + 2 jours + `executeSetSigner` |
| Monitoring on-chain du contrat | Surveiller `devBalance`, `feeBasisPoints`, `owner`, `signer` |
| Configurer un firewall | N'autoriser que 80/443 en entrée, 8546/8001/8008 en 127.0.0.1 seulement |

### 3.3 Rotation des cles (si compromission suspectee)

Le contrat supporte la rotation des cles via Timelock (2 jours) :

```
1. queueSetSigner(nouvelleAdresse)     → event TimelockQueued
2. Attendre 2 jours
3. executeSetSigner(nouvelleAdresse)   → signer mis a jour
4. Mettre a jour .secrets/signer_wallet_pk.enc avec la nouvelle cle
5. Redemarrer le bridge (.\start.ps1)
```

Si la cle owner est compromise :
```
1. queueTransferOwnership(nouvelleAdresse)
2. Attendre 2 jours (l'attaquant aussi doit attendre)
3. executeTransferOwnership(nouvelleAdresse)
4. L'ancienne cle n'a plus aucun pouvoir
```

Pour annuler une operation en attente (compromission detectee a temps) :
```
cancelTimelock(opHash)    → l'operation en attente est annulee
```

### 3.4 Gestion des secrets sur Linux (production)

Le `secrets-manager.mjs` supporte Linux via 3 mecanismes (par ordre de preference) :

1. **GPG** (recommande) : `gpg --symmetric --cipher-algo AES256`
   - Installer : `apt-get install gnupg`
   - La passphrase est chiffree avec GPG, dechiffree avec la cle de l'utilisateur

2. **Variable d'environnement** `SECRETS_PASSPHRASE`
   - Pour CI/CD : `export SECRETS_PASSPHRASE="votre_passphrase"`
   - Pas ideal pour un serveur persistant (dans le profil shell)

3. **Fichier avec permissions 600** : `~/.config/rps/secrets-passphrase`
   - Fallback si GPG n'est pas disponible
   - `chmod 600 ~/.config/rps/secrets-passphrase`
   - Moins securise que GPG (plaintext mais restricted permissions)

### 3.5 Monitoring recommande

| Element | Commande | Seuil d'alerte |
|---|---|---|
| API health | `curl http://127.0.0.1:8001/api/health` | `status != "OK"` |
| Queue default | `docker exec <redis> redis-cli LLEN laravel_database_queues:default` | > 100 |
| Queue limits | `docker exec <redis> redis-cli LLEN laravel_database_queues:limits` | > 5000 (inoffensif mais surveiller) |
| Bridge health | `docker inspect <bridge> --format {{.State.Health.Status}}` | != "healthy" |
| Contrat owner | `contract.owner()` | != adresse attendue |
| Contrat feeBasisPoints | `contract.feeBasisPoints()` | > 500 (5%) |
| Contrat devBalance | `contract.devBalance()` | croissance anormale = fees non retires |
| Timelock events | Surveiller `TimelockQueued` | Tout event = investigation requise |
| Plaintext files | `ls .secrets/*.enc -1 | wc -l` vs `ls .secrets/* -1 | wc -l` | plaintext > 0 (devrait etre 0) |
| docker inspect leaks | `docker inspect <container> | grep -E "SECRET|PASSWORD|WALLET" | grep -v "=$"` | > 0 (devrait etre 0) |

---

## 4. ARCHITECTURE DE PRODUCTION

```
                    Internet
                       |
                  [Firewall: 80/443]
                       |
              [Reverse Proxy TLS]
              (Nginx/Caddy/Traefik)
                  /           \
     127.0.0.1:8001      127.0.0.1:8008
          |                    |
    +-----+-----+        +-----+-----+
    |   app     |        |  reverb    |
    | (Laravel) |        | (WebSocket)|
    | Octane    |        |            |
    +-----+-----+        +-----+-----+
          |                    |
    +-----+-----+        +-----+-----+
    |  redis    |        |    db      |
    | (queues)  |        |  (MySQL)   |
    +-----------+        |  rps_app   |
                         +-----+------+
                               |
    +-----+------+    +-------+-------+
    |  bridge    |    |  blockchain   |
    | (Node.js)  |--->| (Hardhat/Fuji)|
    | payoutOp   |    |  Contrat      |
    | signer     |    |  Battlepool   |
    +------------+    +---------------+
          |
    .secrets/*.enc (AES-256-GCM)
    passphrase: GPG/DPAPI
    0 plaintext sur disque
```

### Flux des secrets

```
.secrets/*.enc (chiffre, sur disque)
     |
     v [provision: dechiffre en memoire]
.secrets/* (plaintext, temporaire)
     |
     v [docker compose up: bind mount]
/run/secrets/* (dans les containers)
     |
     v [security-entrypoint.sh: export]
$APP_KEY, $DB_PASSWORD, $INTERNAL_API_SECRET, etc. (dans PID 1)
     |
     v [deprovision: supprime .secrets/*]
0 plaintext sur disque (host)
```

---

## 5. POST-DEPLOIEMENT : VERIFICATIONS

Apres le deploiement, executer ces verifications :

```bash
# 1. API
curl -s http://127.0.0.1:8001/api/health | jq .
# Attendu: {"status":"OK","database":"OK","cache":"OK"}

# 2. WebSocket
curl -s "http://127.0.0.1:8008/apps/rps_app_id/channels?auth_key=<REVERB_APP_KEY>&auth_timestamp=9999999999&auth_signature=test"
# Attendu: {"channels":[]}

# 3. Containers
docker compose ps --format "table {{.Name}}\t{{.Status}}"
# Tous: Up (healthy)

# 4. Secrets sur disque
ls .secrets/*.enc -1 | wc -l   # > 0 (fichiers chiffres)
ls .secrets/* -1 | wc -l       # = nombre de .enc (pas de plaintext)

# 5. docker inspect (aucun secret)
docker inspect <container> --format '{{range .Config.Env}}{{println .}}{{end}}' | grep -E "SECRET|PASSWORD|WALLET_PK" | grep -v "=$"
# Attendu: (vide)

# 6. Contrat on-chain
node -e "const{JsonRpcProvider,Contract}=require('ethers'); const p=new JsonRpcProvider('<RPC_URL>'); const abi=['function owner() view returns(address)','function signer() view returns(address)','function payoutOperator() view returns(address)','function feeBasisPoints() view returns(uint256)']; const c=new Contract('<CONTRACT_ADDR>',abi,p); (async()=>{console.log('owner:',await c.owner());console.log('signer:',await c.signer());console.log('payoutOp:',await c.payoutOperator());console.log('fee:',(await c.feeBasisPoints()).toString(),'bps')})()"
# Verifier que owner/signer/payoutOperator sont les bonnes adresses

# 7. Pentest rapide (exploit drain)
# Tenter payOut avec amount > balance → doit revert "Amount exceeds user balance"
# Tenter setFeeBasisPoints(10000) → doit revert "Fee cannot exceed 10%"
```

---

## 6. PROCEDURE DE RESTAURATION (apres incident)

### 6.1 Cle owner compromisee

1. Avec la cle owner (si encore controlee) : `queueTransferOwnership(nouvelleAdresse)`
2. Attendre 2 jours (timelock)
3. `executeTransferOwnership(nouvelleAdresse)` — l'ancienne cle perd tout pouvoir
4. Si l'attaquant a aussi queue une operation : `cancelTimelock(opHash_attaquant)`

### 6.2 Cle signer compromisee

1. `queueSetSigner(nouvelleAdresse)`
2. Attendre 2 jours
3. `executeSetSigner(nouvelleAdresse)`
4. Mettre a jour `.secrets/signer_wallet_pk.enc`
5. Redemarrer le bridge (`.\start.ps1`)

### 6.3 Cle payoutOperator compromisee

1. `queueSetPayoutOperator(nouvelleAdresse)`
2. Attendre 2 jours
3. `executeSetPayoutOperator(nouvelleAdresse)`
4. Mettre a jour `.secrets/payout_operator_pk.enc`
5. Redemarrer le bridge

### 6.4 Perte de la passphrase GPG/DPAPI

- Les `.secrets/*.enc` sont PERDUS (impossible a dechiffrer)
- Il faut regenerer tous les secrets :
  - Nouvelles cles wallets → `initializeRoles` impossible (fenetre 100 blocks passee) → utiliser `queueTransferOwnership` + `queueSetSigner` + `queueSetPayoutOperator`
  - Nouveaux secrets backend → mettre a jour `.secrets/*.enc` + redemarrer
- **Lesson** : backuper la passphrase dans un gestionnaire de mots de passe (KeePass, 1Password, etc.)

---

## 7. COMMITS DE SECURITE (reference)

| Commit | Description |
|---|---|
| `e018e16` | security: separate signer/owner roles + fix P0/P1/P3 audit findings |
| `015a628` | security: fix CRITICAL drain bug + add payoutOperator role + Docker secrets |
| `e89d917` | security round 2: Timelock + fee cap + secret rotation + Docker secrets for all |
| `8403f8a` | security: encrypt secrets at rest with AES-256-GCM + DPAPI |
| `72653c2` | security: remove MYSQL_ROOT_PASSWORD from .env + encrypted-at-rest workflow |
| `82c7390` | fix: Reverb entrypoint + Linux support for secrets-manager |

---

## 8. CONTACT / RESPONSABILITES

- **Contrat Solidity** : le dev Smart Contract doit verifier le code avant deploiement on-chain
- **Infrastructure Docker** : le devops doit valider la configuration reseau/firewall/TLS
- **Secrets** : l'administrateur systeme doit generer et stocker les cles de maniere securisee
- **Monitoring** : l'equipe ops doit surveiller les alerts definies en section 3.5

**Ce guide ne remplace pas un audit de securite formel. Il documente les mesures prises apres 3 rounds de pentest black-box. Un audit formalise par une firme tierce est recommande avant tout deploiement avec des fonds reels.**