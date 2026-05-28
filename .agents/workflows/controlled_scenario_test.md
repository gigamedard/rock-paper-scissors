---
description: Test de Scénario Contrôlé & Analyse Statique des Règles
---
# Workflow : Protocole TB — Lancement & Test de Scénario Contrôlé

Ce workflow est déclenché lorsque l'utilisateur demande d'exécuter le "protocole TB", un "Test de Scénario Contrôlé", une "Prédiction de Matchups" ou une "Analyse Statique des Règles".

Le protocole TB sert à **lancer toutes les commandes nécessaires** pour que l'application fonctionne correctement et que l'utilisateur puisse la tester.

---

## 🚀 PHASE LANCEMENT — Démarrage de tous les services (OBLIGATOIRE)

Cette phase lance l'intégralité de l'infrastructure nécessaire au fonctionnement de l'application.
**L'ordre de lancement est critique** : la blockchain doit être prête avant le déploiement des contrats, et les contrats déployés avant le bridge Node.js.

### Étape L.0 — Arrêter les services existants

Avant tout lancement, s'assurer qu'aucun ancien processus ne bloque les ports :

```bash
# Option 1 : Script batch (lance dans une fenêtre séparée)
stop_all.bat

# Option 2 : Commandes manuelles PowerShell (si stop_all.bat échoue)
# Tuer les processus par port
netstat -aon | Select-String "LISTENING" | Select-String "8545|8001|8008|3000"
# Pour chaque PID trouvé : taskkill /PID <PID> /F
```

**Ports à libérer :** 8545, 8001, 8008, 3000

### Étape L.1 — Lancer Hardhat Node (blockchain locale)

```bash
# Depuis le dossier battlepool/
npx hardhat node
# ⚠️ C'est un processus long-running, le lancer en arrière-plan
# Résultat attendu : "Started HTTP and WebSocket JSON-RPC server at http://127.0.0.1:8545/"
```

**Attendre 5-8 secondes** que le noeud soit complètement démarré avant de continuer.

**Vérification :** `netstat -aon | Select-String "8545"` doit montrer LISTENING.

### Étape L.2 — Préparer la base de données

```bash
# Migration fresh + Seed des GameSettings (8 entrées attendues)
php artisan migrate:fresh --seed --force

# Nettoyer le cache
php artisan cache:clear
php artisan config:clear

# Vérification
php artisan tinker --execute="echo 'GameSettings: ' . \App\Models\GameSetting::count();"
# Résultat attendu : GameSettings: 8
```

> **IMPORTANT** : Le `--seed` est OBLIGATOIRE. Sans lui, la table `game_settings` sera vide et les services métier (SessionManager, InternalPoolService) ne pourront pas lire les paramètres dynamiques.

### Étape L.3 — Déployer les Smart Contracts

```bash
# Depuis le dossier battlepool/
npx hardhat run full_deploy.js
```

**Résultat attendu :**
- `Battlepool deployed to: 0x...`
- `SNTToken deployed to: 0x...`
- `MarketplaceEscrow deployed to: 0x...`
- `✅ Updated smart_contracts/config.js with new addresses.`
- `✅ Updated Laravel .env with new addresses.`

> **NOTE** : Un crash Rust (`thread panicked at interval.rs`) peut apparaître à la fin avec Node.js v25+. C'est un bug connu de Hardhat, **le déploiement est réussi** tant que les adresses de contrats sont affichées. Il faudra peut-être relancer le Hardhat node après.

**Attendre 5-10 secondes** après le déploiement.

### Étape L.4 — Relancer Hardhat Node (si nécessaire)

Si le déploiement a crashé le noeud Hardhat (erreur Rust), le relancer :

```bash
# Vérifier si Hardhat tourne encore
netstat -aon | Select-String "8545"

# Si RIEN n'apparait, relancer :
npx hardhat node    # depuis battlepool/
```

### Étape L.5 — Lancer le serveur Laravel API

```bash
# Port 8001 — serveur multi-worker
set PHP_CLI_SERVER_WORKERS=4    # (Windows CMD)
# $env:PHP_CLI_SERVER_WORKERS=4  # (PowerShell)
php artisan serve --port=8001
```

**Vérification :** `netstat -aon | Select-String "8001"` doit montrer LISTENING.

### Étape L.6 — Lancer le Queue Worker (⚠️ CRITIQUE)

```bash
php artisan queue:work --tries=3 --timeout=120
```

> **⚠️ CRITIQUE** : Depuis la refonte asynchrone (branche `refactor/blockchain-optimization-2026-05-27`), **TOUS les appels blockchain passent par des Jobs Laravel** (SetCooldownJob, SyncLimitsJob, SendPaymentJob, VerifyCardPurchaseJob, etc.). Sans le Queue Worker, aucune transaction blockchain ne sera exécutée.

**Vérification :**
```bash
php artisan tinker --execute="echo 'Jobs en attente: ' . DB::table('jobs')->count();"
# Résultat attendu au démarrage : 0
```

### Étape L.7 — Lancer Reverb WebSocket

```bash
php artisan reverb:start
# Résultat attendu : serveur WebSocket sur le port 8008
```

**Vérification :** `netstat -aon | Select-String "8008"` doit montrer LISTENING.

### Étape L.8 — Lancer le Bridge Node.js (app.js)

```bash
# Depuis le dossier smart_contracts/
node app.js
# Résultat attendu : serveur HTTP sur le port 3000
```

**Vérification :** `netstat -aon | Select-String "3000"` doit montrer LISTENING.

### Étape L.9 — Vérification finale de tous les services

```bash
# Tous les ports doivent être LISTENING :
netstat -aon | Select-String "LISTENING" | Select-String "8545|8001|8008|3000"
```

| Service | Port | Rôle |
|---|---|---|
| Hardhat Node | `8545` | Blockchain locale (comptes Hardhat) |
| Laravel API | `8001` | Backend REST API |
| Queue Worker | — | Exécution des Jobs blockchain asynchrones |
| Reverb WebSocket | `8008` | Notifications temps réel |
| Node.js Bridge | `3000` | Pont Laravel ↔ Blockchain (Ethers.js) |

**Critère de succès Phase Lancement :**
- ✅ 4 ports actifs (8545, 8001, 8008, 3000)
- ✅ Queue Worker en écoute
- ✅ GameSettings = 8 entrées
- ✅ Smart Contracts déployés (adresses dans `.env` et `config.js`)

### Raccourci : start_all.bat

Le script `start_all.bat` à la racine du projet exécute toutes ces étapes automatiquement dans des fenêtres CMD séparées. Pour l'utiliser :
```bash
# Depuis la racine du projet
start_all.bat
```

Pour arrêter tous les services :
```bash
stop_all.bat
```

---

## 🛡️ PHASE 0 — Validation Admin Dashboard (OPTIONNEL — après le lancement)

Cette phase vérifie que les paramètres dynamiques du Dashboard sont bien lus par les services métier.

### Étape 0.1 — Vérifier les paramètres en DB

```bash
# Déjà fait par le seed dans la Phase Lancement
php artisan tinker --execute="echo \App\Models\GameSetting::count();"
# Résultat attendu : 8
```

### Étape 0.2 — Vérifier les paramètres depuis l'API Admin

**Prérequis :** Avoir un compte Admin (lancer `php quick_admin.php` ou seed utilisateur admin).

```bash
# Récupérer le token admin via login
curl -X POST http://127.0.0.1:8001/api/login -H "Content-Type: application/json" \
  -d '{"wallet_address":"0x...","password":"..."}'

# Récupérer les paramètres depuis l'API Admin
curl http://127.0.0.1:8001/api/admin/settings \
  -H "Authorization: Bearer <TOKEN>"
# Résultat attendu : JSON groupé avec 8 clés (economy, blockchain, gameplay, simulation)
```

### Étape 0.3 — Test de Sécurité des Routes Admin

```bash
# Tester qu'un utilisateur non-admin reçoit bien 403
curl http://127.0.0.1:8001/api/admin/stats \
  -H "Authorization: Bearer <TOKEN_NON_ADMIN>"
# Résultat attendu : {"error":"Unauthorized. Admin access required."}

# Tester qu'un utilisateur sans token reçoit 401
curl http://127.0.0.1:8001/api/admin/stats
# Résultat attendu : 401 Unauthenticated
```

### Étape 0.4 — Vérifier le Branchement GameSetting → SessionManager

Modifier temporairement `security_coefficient` via le Dashboard (ou directement en DB) pour valider que le changement est pris en compte **sans redémarrer le serveur** :

```bash
# Changer la valeur via l'API Admin
curl -X POST http://127.0.0.1:8001/api/admin/settings \
  -H "Authorization: Bearer <TOKEN_ADMIN>" \
  -H "Content-Type: application/json" \
  -d '{"min_bet_eth": "0.05"}'

# Purger le cache (après toute modification depuis la DB directement)
php artisan cache:clear

# Vérifier dans les logs que le SessionManager lit la nouvelle valeur
# Chercher dans storage/logs/laravel.log : "Security Coefficient from DB: "
# ou "min_bet_eth" dans les logs de l'InternalPoolService
```

**Critère de succès Phase 0 :** Toutes les étapes 0.1 à 0.4 passent sans erreur.

---

## 🖥️ PHASE 0.5 — Validation du Frontend SPA Modulaire (OPTIONNEL)

Cette phase garantit que le nouveau frontend modulaire (Vite.js + Vanilla JS) est opérationnel **avant** de lancer les bots de simulation.

### Étape 0.5.1 — Build Vite & Démarrage
```bash
# Recompiler les assets si le code source a changé
npm run build

# Vérifier que les fichiers compilés existent
ls public/build/assets/
# Résultat attendu : app-*.js, app-*.css, marketplace-*.css, referral-*.css
```

### Étape 0.5.2 — Test de Chargement Initial
```bash
# Ouvrir dans le navigateur : http://127.0.0.1:8001
# Vérifier :
# ✅ Page visible (pas d'écran noir)
# ✅ Header "BATTLEPOOL" présent
# ✅ Liens de nav : Arena | Marketplace | Parrainage
# ✅ Bouton "Connect Wallet" visible
# ✅ Aucune erreur JS critique dans la console (F12)
```

### Étape 0.5.3 — Test de Navigation SPA
```
Cliquer sur chaque lien dans la navbar et vérifier :
- "Marketplace" → Formulaire de création (3 inputs : SNT, AVAX, Durée) + zone "Offres Actives"
- "Parrainage"  → Code "---" + 4 stats à 0 + Leaderboard "Chargement..."
- "Arena"       → Retour à "Ready to Fight?"
```

### Étape 0.5.4 — Test Flux Authentifié (avec compte bot #0 ou #1)
```bash
# Utiliser le compte Hardhat #0 dans MetaMask
# Wallet : 0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266

# Dans le navigateur :
# 1. Cliquer "Connect Wallet" → Approuver MetaMask → Signer le message
# 2. Résultat attendu :
#    - Adresse courte (0xf39F...2266) dans le header
#    - Vue Dashboard avec balance ETH
# 3. Cliquer "Parrainage" → Le code de parrainage doit s'afficher (ex: "AB12CD")
# 4. Cliquer "Marketplace" → Stats passent de "--" à "0" (API connectée)
```

### Étape 0.5.5 — Vérification des Erreurs Console

| Type d'erreur | Statut | Action |
|---|---|---|
| `401 Jeton manquant` avant connexion | ✅ Normal | Ignorer |
| `Cannot find snt_address` (contrat non déployé) | ✅ Normal | Ignorer |
| `TypeError: Cannot read properties of undefined` | ❌ Critique | Arrêter et corriger |
| `404 on /build/assets/app-*.js` | ❌ Critique | Relancer `npm run build` |
| `ReferenceError: X is not defined` | ❌ Critique | Arrêter et corriger |

**Critère de succès Phase 0.5 :** Navigation fonctionnelle + connexion wallet réussie + aucune erreur critique en console.

---

## 🔬 PHASE 1 — Analyse Statique & Scénario Contrôlé (OPTIONNEL)

1. **Analyse Statique des Règles (sans exécuter le code)** :
   - Parcourir les services backend (ex: `FightService.php`, `PoolLifecycleService.php`, `SessionFinishedEventListener.php`) et les Smart Contracts.
   - Vérifier théoriquement toutes les règles demandées par l'utilisateur (Transfert de fonds, Éjection des perdants sans argent, Rétention des gagnants, Payouts vers les portefeuilles Web3).
   - Générer un rapport d'analyse détaillant le parcours exact dans le code pour chaque règle.

2. **Prédiction Déterministe des Matchups** :
   - Si un scénario pratique doit être simulé, NE PAS lancer la simulation au hasard.
   - Demander à l'utilisateur son adresse exacte de portefeuille (MetaMask).
   - Recalculer le `Salt` de la pool (via `predict_matchups.js` ou équivalent) en utilisant le hachage `keccak256` exact des adresses des participants.
   - Appliquer le tri alphanumérique déterministe pour prédire à l'avance *qui affronte qui*.

3. **Mise en place du Scénario Contrôlé** :
   - Assigner des `pre-moves` codés en dur aux bots dans `simulation_bots.js` en fonction des affrontements prévus (ex: forcer Bot 1 à faire 'rock' et Bot 2 à faire 'scissors' pour garantir la victoire de Bot 1).
   - Si l'utilisateur veut tester l'éjection pour fonds insuffisants, créer un script (ex: `bankrupt_bot.php`) pour vider artificiellement la balance "hors combat" du bot ciblé juste avant la résolution du match.

4. **Exécution et Observation** :
   - Lancer la simulation modifiée.
   - Demander à l'utilisateur de jouer le coup prévu via l'interface.
   - Analyser les logs pour valider que le scénario contrôlé s'est bien déroulé comme l'Analyse Statique l'avait prédit.
   - **Vérifier spécifiquement** dans `storage/logs/laravel.log` les lignes contenant `"Audit Zéro Mock"` pour confirmer que les paramètres lus proviennent bien de la DB et non de valeurs hardcodées.

---

## 📋 Résumé rapide — Commandes de lancement

```bash
# === LANCEMENT RAPIDE (manuel, dans cet ordre) ===

# 1. Hardhat Node (Terminal 1)
cd battlepool && npx hardhat node

# 2. DB + Seed (Terminal principal — attendre 5s après Hardhat)
php artisan migrate:fresh --seed --force
php artisan cache:clear

# 3. Smart Contracts (Terminal principal — attendre que l'étape 2 soit finie)
cd battlepool && npx hardhat run full_deploy.js

# 4. Laravel API (Terminal 2)
php artisan serve --port=8001

# 5. Queue Worker (Terminal 3 — ⚠️ CRITIQUE)
php artisan queue:work --tries=3 --timeout=120

# 6. Reverb WebSocket (Terminal 4)
php artisan reverb:start

# 7. Bridge Node.js (Terminal 5)
cd smart_contracts && node app.js
```
