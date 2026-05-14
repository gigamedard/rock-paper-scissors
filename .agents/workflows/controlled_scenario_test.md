---
description: Test de Scénario Contrôlé & Analyse Statique des Règles
---
# Workflow : Test de Scénario Contrôlé et Analyse Statique

Ce workflow est déclenché lorsque l'utilisateur demande d'exécuter un "Test de Scénario Contrôlé", une "Prédiction de Matchups" ou une "Analyse Statique des Règles".

---

## 🛡️ PHASE 0 — Validation Admin Dashboard (OBLIGATOIRE avant tout TB)

Cette phase doit être exécutée **une seule fois avant chaque run TB** pour garantir que les paramètres dynamiques du Dashboard sont bien lus par les services métier.

### Étape 0.1 — Préparer l'environnement
```bash
# Réinitialiser la DB et seeder les paramètres Admin
php artisan migrate:fresh
php artisan db:seed
php artisan cache:clear

# Vérifier que la table game_settings est peuplée (8 lignes attendues)
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

## Étapes du Scénario Contrôlé Principal

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
