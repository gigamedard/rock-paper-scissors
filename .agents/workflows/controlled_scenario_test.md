---
description: Test de Scénario Contrôlé & Analyse Statique des Règles
---
# Workflow : Test de Scénario Contrôlé et Analyse Statique

Ce workflow est déclenché lorsque l'utilisateur demande d'exécuter un "Test de Scénario Contrôlé", une "Prédiction de Matchups" ou une "Analyse Statique des Règles".

## Étapes à suivre par l'Assistant :

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
