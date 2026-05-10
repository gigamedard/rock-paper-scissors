# 🛡️ Plan de Robustesse Battlepool

Ce document recense les axes d'amélioration pour rendre l'écosystème Battlepool (Blockchain, Laravel, Node.js, Frontend) résistant aux pannes, aux erreurs de manipulation et aux interruptions système.

---

## 1. Gestion des Nonces et Intégrité Blockchain
*   **Nonce Manager (Backend)** : Centraliser le suivi du nonce du wallet administrateur en base de données pour éviter les collisions `NONCE_EXPIRED` ou `REPLACEMENT_UNDERPRICED`.
*   **Queue de Transactions** : Ne plus envoyer les transactions en synchrone. Utiliser une file d'attente (Laravel Jobs) pour traiter les paiements et validations un par un, avec retry automatique en cas d'échec de réseau.
*   **Validation de Réseau (Frontend)** : Implémenter un détecteur de changement de ChainID et de désynchronisation de MetaMask pour forcer un "Reset Account" ou un avertissement clair.

## 2. Idempotence et Anti-Concurrency (Base de Données)
*   **Verrous Atomiques (Atomic Locks)** : Utiliser `Cache::lock()` dans Laravel pour empêcher qu'un utilisateur rejoigne plusieurs pools simultanément via des clics rapides.
*   **Clés d'Idempotence** : Chaque requête sensible (Bridge -> Laravel) doit porter un identifiant unique (ex: hash de transaction). Si la même requête arrive deux fois, le serveur ne doit pas recréer de données.
*   **State Machine (FSM)** : Renforcer les transitions de statut dans la table `users`. Empêcher par code de passer de `in_pool` à `available` sans une preuve de fin de combat ou de remboursement.

## 3. Résilience aux Interruptions (Veille & Réseau)
*   **Heartbeat / Watchdog** :
    *   Le **Bridge** et le **Batch Processor** doivent mettre à jour un timestamp `last_ping` en base de données toutes les 10 secondes.
    *   Un script de surveillance (Watchdog) doit pouvoir redémarrer ces services s'ils sont figés (ex: retour de veille machine).
*   **Session Recovery (Frontend)** : Sauvegarder l'état local du jeu (Pool ID, Step actuel) dans le `localStorage`. Au rechargement, le frontend doit interroger le backend (`/api/user/status`) pour reprendre la session exactement là où elle s'était arrêtée.

## 4. Optimisation UX / UI
*   **Contrôle des Interactions** : Désactiver systématiquement les boutons d'action (Join, Claim, Stake) dès le premier clic. Afficher un état "Transaction en cours sur la blockchain...".
*   **Traduction des Erreurs RPC** : Intercepter les erreurs techniques (`execution reverted`, `estimateGas`) pour afficher des messages compréhensibles par l'humain ("Solde insuffisant", "Action déjà effectuée", etc.).
*   **Indicateurs de Connexion** : Ajouter un voyant visuel (Vert/Rouge) pour indiquer l'état de connexion au WebSocket (Reverb) et au Bridge Node.js.

## 5. Observabilité et Débogage
*   **Logging Contextuel** : Inclure l'ID utilisateur et l'ID de pool dans chaque ligne de log (Laravel & Node) pour faciliter le traçage "End-to-End".
*   **Tableau de Bord Santé** : Créer une route `/admin/health` vérifiant la connectivité :
    *   Hardhat RPC (OK/KO)
    *   Helia IPFS (Node ID + Peers)
    *   Reverb WebSocket (Active connections)
    *   Solde du Smart Contract (ETH restants)

---

> **Objectif Final** : Le système doit pouvoir s'auto-réparer et reprendre un état cohérent sans intervention manuelle après une coupure internet ou un crash serveur.
