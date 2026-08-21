
Excellent. La refactorisation est terminée.

Pour valider que les 4 chantiers sont un succès et parfaitement intégrés, propose-moi une stratégie de test complète. Cette stratégie doit couvrir l'ensemble du flux, de l'interface utilisateur à la blockchain.

Je veux un plan de test "End-to-End" organisé en 4 scénarios principaux pour valider chaque nouvelle interaction :

---

**1. Scénario de Test 1 : Démarrage et Connexion (Chantiers 3 & 4)**
* **Objectif :** Valider que le serveur Laravel "headless" sert la SPA et que l'authentification API fonctionne.
* **Actions de test :**
    1.  Démarrer le serveur Laravel.
    2.  Visiter `http://127.0.0.1:8000/`.
    3.  Vérifier que `index.html` est bien servi (et non une vue Blade).
    4.  Tenter une connexion portefeuille.
    5.  Vérifier que l'appel à `/api/wallet/verify-signature` fonctionne et renvoie un `auth_token`.
    6.  Vérifier que ce token est stocké dans le `localStorage` du navigateur.

---

**2. Scénario de Test 2 : Accès aux Données (Chantier 4 <-> 3)**
* **Objectif :** Valider que la SPA (authentifiée) peut charger des données depuis les routes API sécurisées.
* **Actions de test :**
    1.  Après la connexion (Scénario 1), naviguer vers la page "Dashboard Influenceur" (sans recharger la page).
    2.  Vérifier que le JavaScript de la SPA lance un appel `fetch` à la route `/api/influencer/dashboard` en incluant le `Bearer token`.
    3.  Vérifier que le middleware `auth:api-token` de Laravel valide le token et renvoie les données JSON du dashboard.
    4.  Vérifier que le dashboard s'affiche correctement avec les données reçues.

---

**3. Scénario de Test 3 : Communication Interne (Chantier 3 -> 2)**
* **Objectif :** Valider que Laravel peut appeler de manière sécurisée le worker Node.js unifié (`app.js`).
* **Actions de test :**
    1.  Démarrer le serveur `app.js` (Chantier 2).
    2.  Créer une route de *test* temporaire dans Laravel (ex: `/api/test-node-call`).
    3.  Cette route doit appeler une API du serveur Node.js (ex: `/sendPayment`).
    4.  Vérifier que le serveur Node.js reçoit l'appel de Laravel, le traite et renvoie une réponse de succès.
    5.  Valider que l'appel échoue si le `INTERNAL_API_SECRET` n'est pas bon (si tu as le temps de tester ce cas).

---

**4. Scénario de Test 4 : Le "Full Loop" (Blockchain -> 2 -> 3 -> 4)**
* **Objectif :** Valider l'ensemble de la chaîne de communication, du début à la fin.
* **Actions de test :**
    1.  Avoir tous les serveurs (Laravel, Node.js) en marche.
    2.  Ouvrir la SPA (`index.html`) et se connecter.
    3.  **Action :** Effectuer une action sur la blockchain (ex: acheter une offre sur le Marketplace ou déclencher un `PoolEmitted` du jeu).
    4.  **Vérification (Chantier 2) :** Le terminal du `app.js` doit afficher qu'il a détecté l'événement (ex: `🔔 [MARKETPLACE] OfferFulfilled...`).
    5.  **Vérification (Chantier 3) :** Le `app.js` doit appeler l'API interne de Laravel (ex: `/api/internal/trades/update-status`).
    6.  **Vérification (Chantier 3) :** Le middleware `auth.internal` de Laravel doit valider l'appel (grâce au `X-Internal-Secret`).
    7.  **Vérification (BD) :** L'état de l'offre dans la base de données doit passer à "fulfilled".
    8.  **Vérification (Chantier 4) :** La page du Marketplace dans la SPA doit se rafraîchir automatiquement (ou manuellement) et l'offre achetée doit avoir disparu de la liste des offres "ouvertes".

---

Génère-moi une liste de tâches (To-do list) basée sur ces 4 scénarios pour que je puisse commencer les tests de validation.
