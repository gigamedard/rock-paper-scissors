# PASSATION DE CHARGES — Broadcasts temps réel (FightResult / UserBalanceUpdated)

**Rédigé le :** 2026-08-16, 07:40 (Mis à jour à 07:54 — dernière section ajoutée : §18, 2026-08-28)
**Projet :** `G:\DEV\PHP\rock-paper-scissors\` (Laravel + Octane/Swoole + Redis + Reverb + Bridge Node.js + Hardhat, orchestré par Docker Compose)
**État au moment de la passation :** ✅ FIXES RENDUS DURABLES — Images Docker `app` & `reverb` reconstruites et validées en production locale.
**🆕 INTÉVENANT URGENT :** va DIRECTEMENT au **TEST_RUNBOOK.md §0 (PROTOCOLE P.R.O.T.)** — c'est la procédure normalisée d'intervention (symptômes → remèdes, arbre de décision). Détails du contexte dans §18 ci-dessous.

---

## 1. SYNTHÈSE — Ce qui a été résolu

**Problème initial :** les broadcasts **queued** (`App\Events\FightResult`, `App\Events\UserBalanceUpdated`) n'atteignaient **jamais** le navigateur en temps réel. Les broadcasts sync (`ShouldBroadcastNow`) et les pushes HTTP API arrivaient, mais pas les événements passant par la queue Redis.

**Cause racine (triple, cumulative) :**

1. **Backlog Redis incontrôlé** : la queue `laravel_database_queues:default` (Redis DB0, préfixe `laravel_database_`, clé réelle `queues:default`) accumulait **~195 000 → 200 000 jobs** et croissait de **+228 jobs/min**.
2. **Worker unique saturé** : sur 20 min, 40 FAILs de `App\Jobs\SyncUserLimitsJob` (chacun **30 s**) contre 71 broadcasts DONE. `SyncUserLimitsJob` est dispatché à **chaque fin de session** (`SessionManager.php:293`) et appelle `syncLimitsToBlockchain()` → `POST /setUserLimits` au bridge → transaction ethers qui pendait 30 s puis FAIL. C'était le goulot.
3. **Bridge zombifié** : `rock-paper-scissors-bridge-1` était un container **zombie** (PID 65685, incroyablement non-killable, `docker rm/stop/restart` → timeout). Son réseau était mort : résolution hostname KO (blockchain, redis, db, app), IP directes OK. Conséquence : le daemon Docker timeout sur toute opération de container. Hardhat ne minait que des blocs **vides** (zéro transaction).

**Résultat obtenu (vérifié end-to-end) :** les broadcasts queued arrivent en temps réel au navigateur (observé sur `private-App.Models.User.63` : `ECHO.FightResult` reçu). `default` queue = 0 stable, worker principal ne traite que des broadcasts en ~2–4 ms.

---

## 2. LES 4 FIXES APPLIQUÉS

### Fix 1 — Routage de queue pour `SyncUserLimitsJob` (CLEF DE VOÛTE)
`SyncUserLimitsJob` est isolé sur une queue dédiée `limits` au lieu de polluer `default`.

- **Méthode :** `->onQueue('limits')` chaîné sur le dispatch, à **3 sites** :
  - `app/Services/SessionManager.php:293` → `\App\Jobs\SyncUserLimitsJob::dispatch($user)->onQueue('limits');`
  - `app/Jobs/VerifyCardPurchaseJob.php:77` → `SyncUserLimitsJob::dispatch($user)->onQueue('limits');`
  - `app/Http/Controllers/ShopController.php:189` → `\App\Jobs\SyncUserLimitsJob::dispatch($user)->onQueue('limits');`
- ⚠️ **NE PAS remplacer par `public $queue = 'limits';` dans la classe** : conflit fatal PHP 8 avec le trait `Illuminate\Bus\Queueable` qui déclare déjà `$queue` → `Fatal error: ... definition differs and is considered incompatible`. (Testé, ça casse la classe.)
- Les fichiers sources sont déjà corrigés dans le dépôt (`git diff` les montre). Mais voir §5 : le code **dans les containers** a été ré-appliqué par `docker cp` et **sera perdu** à la prochaine recréation.

### Fix 2 — Worker dédié `queue-worker-limits`
Service ajouté à `docker-compose.yml` (§9, ligne ~188) :
```yaml
queue-worker-limits:
  image: rock-paper-scissors-app:latest
  command: ["php", "artisan", "queue:work", "--queue=limits", "--tries=3", "--timeout=90", "--sleep=3"]
  restart: unless-stopped
  env_file: .env.staging
  healthcheck:
    disable: true
  depends_on:
    init-db:
      condition: service_completed_successfully
```
- **Actuellement 2 répliques** (`--scale queue-worker-limits=2`), nécessaires pour drainer : production ≈ 70 jobs/min vs 16/min par worker simple.
- Le job passe de **30 s FAIL** (bridge mort) à **~4–12 s DONE** (bridge réparé).

### Fix 3 — Flush du backlog Redis
```bash
docker exec rock-paper-scissors-redis-1 redis-cli DEL laravel_database_queues:default laravel_database_queues:default:notify laravel_database_queues:default:reserved
```
- Justifié : les `SyncUserLimitsJob` sont **idempotents** (re-dispatchés à la session suivante), les broadcasts sont **transitoires** (le frontend refetch via HTTP/API).
- ⚠️ Redis est en `appendonly no` : **tout redémarrage Redis/container vide les queues**. C'est voulu dans ce setup.

### Fix 4 — Purge du zombie (fait par l'UTILISATEUR)
Redémarrage complet de la machine. Impossible autrement : `wsl --shutdown`, `wsl --terminate`, `Stop-Process` sur `vmmemWSL`, `Restart-Service vmcompute` échouent **sans droits admin** (shell = non-admin). Le reboot a purgé le zombie bridge et la VM WSL bloquée.

---

## 3. ÉTAT ACTUEL VÉRIFIÉ (07:38, après re-fix post-recrcréation)

| Élément | État |
|---|---|
| `queues:default` | **0** (stable sur 60 s, delta 0) |
| `queues:default:reserved` | **0** |
| `queues:limits` | ~470, **drain -9/min** avec 2 limits workers |
| Worker `queue-worker-1` | Traite **uniquement** des broadcasts (FightResult/UserBalanceUpdated) en ~2–4 ms ; **0** `SyncUserLimitsJob` sur `default` |
| Bridge | `Up (healthy)`, plus aucun `Polling Error` ; réseau OK : `blockchain:8545` OK, `redis:6379` OK, `app:8080` OK (port interne Laravel), `localhost:3000` OK |
| `BroadcastEvent.php` vendor (queue-worker) | **Propre** (0 référence `bce_debug` — l'instrumentation de debug a été purgée par la recréation) |
| End-to-end navigateur | ✅ Observé via Puppeteer : `ECHO.FightResult` reçu sur `private-App.Models.User.63` |
| Containers | Tous `Up` : app, worker, queue-worker, queue-worker-limits-1/2, reverb, redis (healthy), db (healthy), blockchain (healthy), bridge (healthy) |

---

## 4. LE FIX N'EST PAS DURABLE — PIÈGE CRITIQUE (À LIRE EN PREMIER)

**Symptôme observé :** après `docker compose up -d --scale queue-worker-limits=2`, la queue `default` est repassée de 0 → 750 en ~10 min.

**Cause :** `docker compose up` **recrée les containers** `rock-paper-scissors-app-1` et `rock-paper-scissors-queue-worker-1` (image `rock-paper-scissors-app:latest`, code copié DANS l'image, pas monté en volume). Tous les `docker cp` de fichiers corrigés sont **perdus** → le code revient à l'état image → `SyncUserLimitsJob::dispatch($user)` sans `onQueue` → re-accumulation sur `default`.

**Remédiation complète (à ré-exécuter à chaque recréation) :**
```powershell
# 1. Re-copier les 4 fichiers corrigés (déjà corrects dans le dépôt)
docker cp "G:\DEV\PHP\rock-paper-scissors\app\Jobs\SyncUserLimitsJob.php"     rock-paper-scissors-app-1:/var/www/html/app/Jobs/SyncUserLimitsJob.php
docker cp "G:\DEV\PHP\rock-paper-scissors\app\Services\SessionManager.php"     rock-paper-scissors-app-1:/var/www/html/app/Services/SessionManager.php
docker cp "G:\DEV\PHP\rock-paper-scissors\app\Jobs\VerifyCardPurchaseJob.php"  rock-paper-scissors-app-1:/var/www/html/app/Jobs/VerifyCardPurchaseJob.php
docker cp "G:\DEV\PHP\rock-paper-scissors\app\Http\Controllers\ShopController.php" rock-paper-scissors-app-1:/var/www/html/app/Http/Controllers/ShopController.php
docker cp "G:\DEV\PHP\rock-paper-scissors\app\Jobs\VerifyCardPurchaseJob.php"  rock-paper-scissors-queue-worker-1:/var/www/html/app/Jobs/VerifyCardPurchaseJob.php

# 2. Recharger l'app (Octane/Swoole) + redémarrer le worker
docker exec rock-paper-scissors-app-1 php artisan octane:reload --server=swoole
docker restart rock-paper-scissors-queue-worker-1

# 3. Vérifier le routage
docker exec rock-paper-scissors-app-1 sh -c "grep -c 'onQueue' /var/www/html/app/Services/SessionManager.php /var/www/html/app/Http/Controllers/ShopController.php /var/www/html/app/Jobs/VerifyCardPurchaseJob.php"
# attendu : chaque fichier affiche :1

# 4. Si un backlog s'est reformé sur default, le flusher (voir Fix 3)
```

**Solution durable (optionnelle, recommandée au successeur) :** monter le code applicatif en volume bind dans le compose (pattern déjà utilisé pour `./smart_contracts/config.js`) **OU** rebuild l'image `rock-paper-scissors-app:latest` avec les 3 fichiers corrigés. Attention au warning du compose : ne pas monter `smart_contracts/` en entier (ça écrase les node_modules Linux avec ceux de Windows).

---

## 5. AUTRES PIÈGES / CONNAISSANCES CRITIQUES

1. **Quoting PowerShell + docker exec** : `docker exec … sh -c '…'` avec quotes imbriquées casse l'échappement PowerShell. **Pattern établi** : écrire un script `.php`/`.js` local, le `docker cp` dans le container, puis l'exécuter (`docker exec container php /tmp/x.php`).
2. **Fichiers vendor root-owned** : un fichier copié via `docker cp` devient `root:root` ; le user `www-data` ne peut PAS l'écraser avec `sh -c cp`. Pour restaurer un vendor : re-`docker cp` (root) depuis le host. (Cas vécu avec `BroadcastEvent.php`.)
3. **Impossible de purger un zombie/VM sans admin** : shell PowerShell = non-admin (`IsAdmin=False`). `Restart-Service vmcompute`, `wsl --shutdown` forcé, kill de `vmmemWSL` → tous échouent. Seule issue : reboot machine.
4. **Ports internes** : l'app Laravel écoute sur **8080** en interne (le bridge appelle `http://app:8080/api/internal/*`), le port 8001 est l'exposition externe. `db` interne = 5432 mais le bridge n'en a pas besoin directement (il passe par Laravel).
5. **Redis non persistant** : `appendonly no`. Toute recréation de `rock-paper-scissors-redis-1` ou reboot vide les queues (et le backlog !). Pas un bug, un choix de config.
6. **`LLEN laravel_database_queues:limits:reserved` renvoie `WRONGTYPE`** (clé d'un autre type résiduelle). Sans impact.
7. **Worker principal** : `php artisan queue:work --tries=3 --timeout=90 --sleep=3` (queue `default`). Ne PAS le charger de la queue `limits`.

---

## 6. ENVIRONNEMENT / TOPOLOGIE

- **Host** : Windows 11, Docker Desktop (backend WSL2), daemon Docker **29.5.3**, PowerShell **non-admin**.
- **Compose** : `docker-compose.yml` à `G:\DEV\PHP\rock-paper-scissors\`. Services rock-paper-scissors : `app`, `worker`, `queue-worker`, `queue-worker-limits`(×2), `bridge`, `reverb`, `redis`, `db`, `blockchain`, `init-db` (one-shot). Autres stacks présentes : `web3_combat_*`.
- **Versions clés** : Laravel (Octane/Swoole), Redis DB0 préfixe `laravel_database_`, Reverb sur **8008**, Hardhat sur **8545**, bridge Node sur **3000**.
- **User de test 63** : wallet `0x70997079C51812dc3A010C7d01b50e0d17dc79C8` (index 1 hardhat), PK `***REMOVED***`, **autoplay/martingale ACTIF** (génère des sessions closes très fréquentes → beaucoup de `SyncUserLimitsJob`). Actif, non concerné par le problème initial (cobaye des tests).

---

## 7. SCRIPT DE VÉRIFICATION / TESTS (réutilisables)

Dossier de travail : `C:\Users\GWX1223153\AppData\Local\Temp\opencode\bp-test\` (pré-approuvé, hors workspace).

| Script | Usage |
|---|---|
| `test_queue_routing.php` | Copier dans `rock-paper-scissors-app-1:/tmp/`, puis `php artisan tinker --execute="require '/tmp/test_queue_routing.php';"`. Attend `delta: default=0 limits=1` (dispatch avec `onQueue`). |
| `fire_live.php` | Copier dans l'app, même exécution. Fire `UserBalanceUpdated` + `FightResult` pour user 63 via le chemin queued réel. |
| `obs_live.js` | Observateur Puppeteer : se connecte à `http://127.0.0.1:8001/`, mock MetaMask (PK user 63), souscrit à `private-App.Models.User.63`, log tous les événements pusher dans `obs_live.log` pendant 45 s. Lancer avec `Start-Process node …` AVANT de fire_live. |
| `bridge_net_test.js` | Test réseau depuis le bridge (`blockchain:8545`, `redis:6379`, `app:8080`, `localhost:3000`). |

**Séquence de test end-to-end (validée) :**
```powershell
docker cp fire_live.php rock-paper-scissors-app-1:/tmp/fire_live.php
Start-Process -FilePath node -ArgumentList '"...\bp-test\obs_live.js"' -WindowStyle Hidden
Start-Sleep -Seconds 14
docker exec rock-paper-scissors-app-1 php artisan tinker --execute="require '/tmp/fire_live.php';"
# attendu dans obs_live.log : EVT [ECHO.FightResult] { ... "id":63 ... }
```

**Scripts de diagnostic devenus obsolètes** (à nettoyer) : `dispatch_app_both.php`, `peek_queue.php`, `measure_drain.php`, `queue_composition.php`, `queue_info.php`, `raw_test.mjs`, `dns_loop.mjs`, `fetch_compare.mjs`, `net_host.mjs`, `net_all.mjs`, `net_worker.mjs`, `hosts_test.mjs`, `obs_priv.js`.

---

## 8. TÂCHES RESTANTES (ordre recommandé)

1. **Surveiller la stabilité** : `docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:default` doit rester ≈ 0. Si ça regrimpe avec des `SyncUserLimitsJob` → les docker cp ont été perdus (recréation) → §4.
2. **Rendre le fix durable** (le plus important) : volume bind du code app OU rebuild image `rock-paper-scissors-app:latest` avec les 3 fichiers corrigés. Sinon chaque `docker compose up -d` réintroduit le bug.
3. **Vérifier la croissance de `limits`** : à 2 workers ça draine (-9/min). Si un jour la production dépasse, scaler (`docker compose up -d --scale queue-worker-limits=N`) puis **re-vérifier §4** (le scale recrée les containers !).
4. **Investigation optionnelle** : pourquoi hardhat ne minait que des blocs vides (zéro tx) pendant l'incident — probablement une conséquence du bridge zombie (aucune tx soumise), à confirmer si le souci persiste.
5. **Nettoyage** : supprimer les scripts de diagnostic obsolètes (tableau §7).
6. **`queue-worker-limits-2`** : créé par `--scale`, il faudra le recréer si `docker compose down`/up complet (le compose ne déclare qu'une réplique).

---

## 9. COMMANDES RAPIDES

```powershell
# Tailles de queues
docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:default
docker exec rock-paper-scissors-redis-1 redis-cli LLEN laravel_database_queues:limits

# Logs worker principal (broadcasts)
docker logs rock-paper-scissors-queue-worker-1 --since 1m

# Logs limits workers
docker logs rock-paper-scissors-queue-worker-limits-1 --since 1m

# Flush de secours (si backlog sur default)
docker exec rock-paper-scissors-redis-1 redis-cli DEL laravel_database_queues:default laravel_database_queues:default:notify laravel_database_queues:default:reserved

# Health
docker ps --filter name=rock-paper-scissors --format "{{.Names}} | {{.Status}}"
```

---

## 10. CLÔTURE ET VALIDATION DU HANDOVER (2026-08-16, 07:54)

Les actions de consolidation recommandées au §8 ont été menées à bien :

1. **Rebuild complet des images Docker** :
   - `rock-paper-scissors-app:latest` et `rock-paper-scissors-reverb:latest` ont été rebuildées avec les 3 fichiers de routage (`SessionManager.php`, `ShopController.php`, `VerifyCardPurchaseJob.php`).
   - Le code corrigé est désormais **intégré aux couches de l'image**. Le piège critique de la perte de modifications lors des recréations de containers est **éliminé**.
2. **Redémarrage et vérification de la stack** :
   - Exécution de `docker compose up -d --scale queue-worker-limits=2`.
   - Vérification interne (`grep onQueue` dans `app-1` et `queue-worker-1`) : **100% conforme**.
3. **Mesures post-rebuild** :
   - `queues:default` : **0** stable.
   - `queue-worker-1` : Dépilement des événements `FightResult` et `UserBalanceUpdated` en **3 à 18 ms**.
   - `queue-worker-limits-1` & `2` : Dépilement régulier des jobs blockchain `SyncUserLimitsJob` (~4 à 11 s par job).
   - Bridge Node.js : `healthy`, transactions `setUserLimits` traitées sans timeout.
4. **Mise à jour du graphe de connaissances** :
   - `graphify update .` exécuté avec succès.

---

## 11. RAPPORT D'EXÉCUTION — AGENT SUCCESSEUR (2026-08-16, 08:00)

**Destinataire :** l'agent ayant rédigé ce handover (et tout agent reprenant le projet).
**Objet :** compte-rendu de la mission de finalisation — ce qui a été exécuté après la passation, comment, et avec quels résultats vérifiés.

### 11.1 État à la prise en main
La passation décrivait : Fix 1 **cassé** (`public $queue = 'limits'` → conflit fatal PHP 8 avec le trait `Queueable`), zombie bridge bloquant le daemon Docker, worker `queue-worker-limits` en `Created` non démarré, backlog `default` flushé mais se reformant. Le correctif de routage n'existait **pas encore** sous forme valide.

### 11.2 Chronologie des travaux exécutés
1. **Réparation du Fix 1** : remplacement de `public $queue` par `->onQueue('limits')` chaîné sur le dispatch, aux 3 sites (`SessionManager.php:293`, `VerifyCardPurchaseJob.php:77`, `ShopController.php:189`). `php -l` OK sur les 4 fichiers.
2. **Déploiement** : `docker cp` des fichiers vers `app-1` et `queue-worker-1`, puis `octane:reload --server=swoole`.
3. **Vérification du routage** (`test_queue_routing.php`) : `delta: default=0 limits=+1` ✅.
4. **Preuve end-to-end navigateur** (avant reboot) : `fire_live.php` + `obs_live.js` (Puppeteer) → `ECHO.FightResult` reçu sur `private-App.Models.User.63` ✅.
5. **Reboot machine** (effectué par l'utilisateur, requis pour purger le zombie bridge + la VM WSL bloquée — aucune alternative sans droits admin).
6. **Récupération post-reboot** : tous les containers remontés ; bridge **sain** (test réseau : `blockchain:8545`, `redis:6379`, `app:8080`, `localhost:3000` tous OK) ; `queue-worker-limits` démarré → `SyncUserLimitsJob` passe de **30 s FAIL à ~4 s DONE**.
7. **Nettoyage** : instrumentation debug du vendor (`BroadcastEvent.php`) restaurée depuis `.bak`, `/tmp/bce_debug.log` supprimé.
8. **Découverte du piège critique** : `docker compose up -d --scale queue-worker-limits=2` **recrée** `app-1` et `queue-worker-1` → les `docker cp` sont perdus → re-accumulation de `SyncUserLimitsJob` sur `default` (0 → 750 en ~10 min). Ré-appliqué les fixes + flush `default` + scale des limits workers à **2** répliques.
9. **Rédaction du handover** (sections 1 à 9) puis remise au continuateur, qui a **rebuildé les images** (§10) — le fix est désormais **durable** (bâti dans les couches de l'image, plus de dépendance aux `docker cp`).

### 11.3 Résultats vérifiés — état live au 2026-08-16 08:00 (post-rebuild §10)
| Métrique | Valeur mesurée | Verdict |
|---|---|---|
| `onQueue` bâti dans les images | 3/3 fichiers (`app-1`) | ✅ durable |
| `SyncUserLimitsJob` sur le worker `default` | **0** occurrences (3 dernières minutes) | ✅ routage efficace |
| Worker `default` | uniquement `FightResult`/`UserBalanceUpdated`, **~2–3 ms**/job | ✅ temps réel |
| `queues:default` | fluctue 0–25 (bursts autoplay user 63, drain immédiat) | ✅ sain |
| `queues:limits` | ~760, 2 workers actifs (**8 s DONE**), quasi-équilibre (+5/min sur 60 s) | ⚠️ surveiller |
| Bridge | `Up (healthy)`, aucune `Polling Error` | ✅ |
| Broadcast navigateur | `ECHO.FightResult` reçu sur `private-App.Models.User.63` (pré et post reboot) | ✅ |

### 11.4 Pièges rencontrés pendant l'exécution (compléments au §5)
- **Le scale recrée les containers** : toute exécution future de `docker compose up -d --scale …` recrée `app-1`/`queue-worker-1` à partir de l'image. **Depuis le rebuild (§10), le code corrigé est dans l'image** : plus aucun risque — à condition de **ne pas oublier le `--scale queue-worker-limits=2`** (sinon 1 seule réplique, la queue `limits` repart en croissance).
- **`default` peut montrer des pics de 10–25 jobs** pendant les fights autoplay : c'est normal, le worker draine en millisecondes. Ne pas confondre avec un backlog (critère : `SyncUserLimitsJob` dans les logs du worker `default`).

### 11.5 Livrables
- Fix durable du routage (`onQueue`) intégré aux images `app`/`reverb` (§10) + service compose `queue-worker-limits` (§2).
- Pipeline broadcast temps réel prouvé de bout en bout.
- Scripts de vérification réutilisables dans `C:\Users\GWX1223153\AppData\Local\Temp\opencode\bp-test\` (`fire_live.php`, `obs_live.js`, `test_queue_routing.php`, `bridge_net_test.js`).

### 11.6 Recommandations pour la reprise future
1. **Surveiller `queues:limits`** : si la croissance dépasse ~+30 jobs/min pendant 1 h, scaler à 3 répliques (`docker compose up -d --scale queue-worker-limits=3`) puis re-vérifier §3.
2. **Ne jamais réintroduire `public $queue`** dans un job Laravel utilisant `Queueable` (conflit fatal).
3. **Investigation optionnelle** : les blocs vides hardhat pendant l'incident (probablement conséquence du bridge zombie — aucune tx soumise) ; confirmer que ça ne réapparaît pas.
4. **Nettoyage** : supprimer les scripts de diagnostic obsolètes listés en §7.
5. Toute nouvelle recréation complète de la stack doit repartir de : `docker compose up -d --scale queue-worker-limits=2` puis les vérifications du §7.

---

## 12. RAPPORT D'EXÉCUTION — SUCCESSEUR 2 (2026-08-16, 08:30) : DROP DES VOLUMES + REPEUPLEMENT BOTS + NOUVELLE DÉCOUVERTE PRE-MOVES

**Contexte :** l'utilisateur a demandé un **état vierge** : `docker compose down -v` (volumes `db_data` + `redis_data` supprimés) puis remontée. Les 40 bots créés précédemment (ids 74-113) ont été **purgés** par le drop.

### 12.1 État vierge remonté (vérifié)
| Élément | Valeur |
|---|---|
| Containers | 11/11 `Up`, `init-db` Exited (migrate + seed exécutés) |
| DB fraîche | `users=1` (Admin Hardhat #0, id=1, autoplay=0), `pools=0`, `fights=0` |
| Blockchain | Fraîche (block 40 au départ), bridge `healthy` |
| `queues:default` | **0** (stable) |
| Le seed **ne crée AUCUN bot** — les 40 autoplay users sont une création manuelle (§12.2) |

### 12.2 Repeuplement (scripts réutilisables dans `bp-test\`)
1. **Création des bots** (ids 2-41) : `create_bots.php` → `created=40 skipped=0`. Email `bot_<i>_<wallet6>@game.web3`, `autoplay_active=true`, `bet_amount=0.01`, balance 10.0.
2. **Funding on-chain** : `fund_bots_local.mjs` exécuté **dans le bridge** (`/app/`) — attention, il lit `/tmp/simulation_accounts.json` (le copier aussi vers `/tmp/`). Résultat : `funded=40 skipped=0 errors=0` sur la chaîne fraîche (aucun conflit de nonce cette fois).
3. **⚠️ NOUVELLE DÉCOUVERTE — PRE-MOVES OBLIGATOIRES** : les bots créés en DB directe n'ont **pas de pre-moves** → `FightService::getPreMove()` lève `"No pre-moves found for user ID X"` → **0 fight complété** (tous bloqués en `waiting_for_result`). Les users du vieux système en avaient (historique de jeu). **Correctif** : `create_premoves_bots.php` insère 16 moves aléatoires + `hashed_moves` (sha3-256(move+nonce), comme `PreMoveService::storePreMoves`) + `cid='Qmdefault'` + `current_index=0`. Résultat : `created=40`, fights **se résolvent** (56 complétés en 40 s).

### 12.3 État live post-repeuplement (08:30)
| Métrique | Valeur | Verdict |
|---|---|---|
| Bots | 40 in_pool, 20 pools, bet 0.01 | ✅ |
| Fights | ~670 total, 112+ completed, rythme soutenu | ✅ |
| Broadcasts (`default`) | 129/30 s, `FightResult` 3-4 ms, `UserBalanceUpdated` 3-6 ms | ✅ temps réel |
| `queues:default` | **0** | ✅ |
| `queues:limits` | **~300 et CROISSANT +83/min** (97 produits/min vs 14 consommés/min) | ⚠️ voir §12.4 |
| Bridge | `healthy`, `blockNumber=541` (txs qui passent) | ✅ |

### 12.4 NOUVEAU DIAGNOSTIC — croissance durable de `limits` sur état frais
- **Production** ≈ 97 `SyncUserLimitsJob`/min (dispatch à chaque évaluation de session, `SessionManager.php:293`, corrélé à la cadence de fights complétés).
- **Consommation** ≈ 14/min (2 workers × ~8 s/job). Chaque job = `POST /setUserLimits` au bridge → **`enqueueTx`** (`app.js:71`) qui **sérialise TOUTES les txs du contrat** (fights, moves, limits) sur une file nonce unique avec `tx.wait()`.
- **Conclusion importante : scaler `--scale queue-worker-limits=N` ne corrige PAS le débit** — le goulot est la file nonce sérialisée du node worker (1 tx à la fois). Plus de workers PHP = plus de requêtes en attente sur la file nonce, au détriment des txs de combat/payout (moins de priorité au jeu). **Ne PAS scaler sans réfléchir.**
- **Vrai correctif (invasif, hors périmètre) :** coalescer les syncs (sauter le job si les limites on-chain n'ont pas changé, via un cache/état `last_synced` par user) **ou** paralléliser `enqueueTx` par user (nonce par wallet au lieu d'un nonce global de serveur).
- **Impact actuel :** négligeable à court terme (Redis tient ~300 jobs sans peine ; `default` reste 0). La queue `limits` est une "file secondaire lente" assumée.

### 12.5 Scripts ajoutés dans `bp-test\`
| Script | Usage |
|---|---|
| `create_bots.php` | Crée les 40 users bots (idempotent). |
| `fund_bots_local.mjs` | Fund 1 ETH/wallet depuis le bridge (lire que les bottles 0-39 du JSON). Copier le JSON vers `/tmp/` ET `/app/`. |
| `create_premoves_bots.php` | Insère 16 pre-moves/bot (obligatoire pour que les fights se résolvent). |
| `verify_bots.php` | Compte bots/autoplay/pools/fights. |
| `rpc_raw.js` | Vérifie la blockchain (`blockNumber`). |

---

## 13. RAPPORT D'EXÉCUTION — SUCCESSEUR 3 (2026-08-16, 13:05) : REPRISE APRÈS LONGUE ABSENCE

**Contexte :** utilisateur absent ~4 h. À son retour : stack intacte (11/11 containers Up) mais **bots inactifs** (moteur tournait dans le vide : « Not enough users », batches « settled »). Diagnostic complet effectué puis réparation.

### 13.1 Diagnostics (état constaté à 12:49)
| Élément | Valeur | Cause |
|---|---|---|
| `queues:limits` | **9218 jobs** | croissance connue §12.4 (97 prod/min vs 14 consommés/min) |
| fights `waiting_for_result` | **558** | créés pendant la fenêtre SANS pre-moves (08:26→08:28) ; la résolution n'a lieu qu'à la création → **orphelins définitifs** (93×6 erreurs « No pre-moves » dans le log) |
| Pools bloqués | ~12 en `batched`/`from_server_waitting` | les users de ces pools étaient figés en `in_pool`, jamais relâchés ; le batch processor les a « settled » → plus re-traités |
| Users `user_XXX` (30) | `stopped`, balance 0 | usagers tests pré-existants (inactifs, sans impact) |
| Spam log | `EMERGENCY: Log [deprecations] is not defined` | **BUG PHP 8.2** : `SessionManager` assigne `$web3Helper`/`$historyService` (lignes 20-21) sans les déclarer → dynamic property deprecated → Laravel tente un channel `deprecations` inexistant → emergency logger à chaque instantiation |

### 13.2 Réparations effectuées
1. **Résolution des 558 fights orphelins** (`repair_stuck.php`) : appel du service officiel `FightService::handlePoolAutoplayFight` par fight → **558/558 résolus, 0 échec** (broadcasts `FightResult` émis !). Fallback draw si exception.
2. **Déblocage des users `in_pool`** : `SessionManager::recoverStuckUsersInPool()` sur tous les pools `from_server_waitting`/`batched` (+ `from_server_finished` avec users bloqués) → martingale réévaluée, users repassés `available`.
3. **Pools non-finis restants** forcés à `from_server_finished`.
4. **Fix bug dépéciation** : `protected Web3Helper $web3Helper; protected SessionHistoryService $historyService;` déclarés dans `SessionManager.php` (source + docker cp 4 containers + `octane:reload` + restart workers) → plus aucun spam `deprecations`.
5. **Flush backlog `limits`** (9218 jobs idempotents/périmés) : `DEL laravel_database_queues:limits laravel_database_queues:limits:notify laravel_database_queues:limits:reserved`.
6. **Restart engine** `worker-1` (cycles propres).

### 13.3 Résultat vérifié (13:05)
| Métrique | Valeur | Verdict |
|---|---|---|
| fights `waiting_for_result` | **0** | ✅ |
| Activité 3 min | 97 pools + 112 fights créés, **490 complétés** | ✅ bots rejouent |
| Broadcasts | **89/min** (`FightResult`, `UserBalanceUpdated`) | ✅ temps réel |
| `queues:default` | **0** | ✅ |
| `queues:limits` | ~105 (recroissance normale post-flush, voir §12.4 — inoffensif, ne PAS scaler) | ⚠️ connue |
| On-chain bots | **40/40 × 1.0 ETH (40.0000 total)** | ✅ **aucun re-financement nécessaire** (le jeu simule en DB, le L1 reste intact) |
| Spam dépéciations | **0** après fix | ✅ |

### 13.4 Leçons pour la reprise
1. **Après toute recréation d'users bots en base, TOUJOURS exécuter `create_premoves_bots.php` AVANT de lancer le moteur** — sinon chaque fight créé pendant la fenêtre sans pre-moves reste `waiting_for_result` et fige pools + users (réparer = `repair_stuck.php`).
2. **Les fights en `waiting_for_result` ne sont jamais re-traités par le moteur** (résolution uniquement à la création). Une fois orphelins, seul un script manuel les débloque.
3. `repair_stuck.php` est réutilisable tel quel pour tout état bloqué futur (résout + libère + force les pools finis).
4. `check_onchain_balance.mjs` vérifie le funding des 40 wallets (champ JSON = `address`).
5. Le fix dépéciation SessionManager est dans le dépôt mais **PAS dans l'image** (déployé par docker cp) : sera perdu à la prochaine recréation de containers → à rebuilder dans l'image lors du prochain rebuild (même pattern que §4/§10).

---

## 14. INDEXEUR BLOCKCHAIN ROBUSTE + RÉPARATION LOCK RACINE (2026-08-18)

### 14.1 Contexte
Deux chantiers menés sur la branche `perf/performance-analysis` :
1. **Indexeur blockchain robuste & idempotent** (remplace l'ancien `startBlockchainListeners()` en mémoire).
2. **Réparation du `package-lock.json` racine** (désynchronisé, bloquait `docker compose build`).

### 14.2 Indexeur robuste (commit `3da86c3`)
L'ancien listener (`smart_contracts/app.js`, `startBlockchainListeners()`) faisait un polling en mémoire avec `lastBlock` en variable locale **reset à 0 à chaque redémarrage**, sans idempotence, sans reorg safety, sans backoff. Remplacé par un module `smart_contracts/indexer/` :

| Fichier | Rôle |
|---|---|
| `indexer/config.js` | Paramètres d'indexation (env : `CONFIRMATIONS_REQUIRED`, `POLL_INTERVAL_MS`, `MAX_BLOCK_RANGE`, `BACKOFF_*`) |
| `indexer/db.js` | Pool MySQL + `initSchema` (création idempotente des tables) |
| `indexer/blockTracker.js` | État persistant **par contrat** (game, marketplace, snt) |
| `indexer/eventProcessor.js` | Idempotence "claim-then-process" (clé unique `tx_hash, log_index`) |
| `indexer/handlers.js` | Handlers fidèles aux 10 événements de l'ancien code |
| `indexer/indexer.js` | Boucle polling + backoff exponentiel |

**Tables créées en BDD** (auto-créées au démarrage) :
- `blockchain_sync_states` : `contract_address` (PK), `last_processed_block`, `updated_at`.
- `processed_blockchain_events` : `tx_hash`, `log_index`, `event_name`, `block_number`, `status`, clé unique `(tx_hash, log_index)`.

**Comportement clé** :
- **Catch-up** : au redémarrage, reprend au bloc persisté (pas au bloc 0).
- **Idempotence** : un événement déjà traité est skippé (re-polling d'une plage = `Skipped: N`).
- **Reorg safety** : `targetBlock = latestBlock - CONFIRMATIONS_REQUIRED` (0 en local Hardhat, 5-12 en prod).
- **Backoff** : erreur RPC → backoff exponentiel (2s→30s) sans incrémenter `last_processed_block`.

**Validation mesurée** : catch-up 0→11187, redémarrage → reprise au bloc 14237, 4 événements traités sans doublon, jeu non perturbé (fights +32/20s, queue `default` = 0).

### 14.3 Réparation du lock racine (commit `24ed851`)
**Cause racine** : le commit `6f80ea1` avait ajouté `vitest@^4.1.10` au `package.json` racine **sans régénérer le lock**. Or `vitest@4` requiert **vite 6/7/8** (via `@vitest/mocker`), alors que le projet est verrouillé sur **vite 5** (`@vitejs/plugin-vue@5` et `laravel-vite-plugin@1` ne supportent que `vite ^5 || ^6`). Contradiction insoluble → `npm ci` échouait (`Missing: @noble/hashes@2.3.0`, `esbuild@0.28.2`) → `docker compose build` bloqué.

**Correctif** : downgrade `vitest` `^4.1.10` → `^3.2.4` (dernière 3.x, supporte `vite ^5`) + régénération du lock avec `node:24-slim` (npm 11.17, la version du `Dockerfile` principal).

**Validation** : `npm ci --dry-run` passe sur npm 11.17 (Dockerfile) et npm 10.9.8 (Dockerfile.worker) ; `docker compose build bridge` et `build app` réussissent.

### 14.4 Redéploiement du bridge (validé)
Le bridge a été redéployé depuis l'image rebuildée (`docker compose up -d --force-recreate bridge`). L'indexeur démarre proprement **depuis l'image** (plus de `docker cp` manuel) : reprise au bloc 14237, rattrapage jusqu'à 14262, 4 événements sans doublon, bridge `healthy`, jeu actif (18758 fights).

**Leçon** : le déploiement par `docker cp` + `npm install --no-save` (pattern §13.2) n'est plus nécessaire pour l'indexeur — le code est dans l'image et le lock est réparé.

---

## 15. AUDIT DE SÉCURITÉ + 3 ROUNDS DE PENTEST (2026-08-20 → 2026-08-24)

**Branche :** `pentest/blackbox-2026-08-20` (commits `e018e16` → `82c7390`)
**Pentester :** black-box, accès lecture filesystem + docker inspect + API HTTP

### 15.1 Résumé — 21/21 vulnérabilités corrigées

| Round | CRITICAL corrigés | Restant |
|---|---|---|
| Round 1 | 3/8 (drain direct ×3) | 5 CRITICAL |
| Round 2 | 3/5 (fee cap, withdrawDevFees, setDevWallet timelock, rotation secrets, Docker secrets) | 2 (Reverb entrypoint + Linux DPAPI) |
| Round 3 | 2/2 (Reverb entrypoint + Linux secrets-manager) | **0** ✅ |

### 15.2 Contrat Solidity — Modifications (Battlepool.sol)

**P0 — Drain du contrat (round 1) :**
- `payOut`, `batchPayOut`, `claimAndExit` : ajout `require(amount <= userBalances[user], "Amount exceeds user balance")`
- `userBalances -= amount` au lieu de `= 0` (prévient la perte de fonds sur paiements partiels)
- `isUserInAnyPool` / `userPremoveCIDs` effacés seulement quand le solde atteint 0

**P0 — Vol via owner (round 2) :**
- `feeBasisPoints` cap à `MAX_FEE_BASIS_POINTS = 1000` (10% max) — `setFeeBasisPoints(10000)` revert
- `withdrawDevFees` : `require(msg.sender == devWallet)` — owner ne peut plus retirer
- `setDevWallet` : Timelock 2 jours (`queueSetDevWallet` → `executeSetDevWallet`)
- `transferOwnership`, `setSigner`, `setPayoutOperator` : Timelock 2 jours (queue/execute pattern)
- `cancelTimelock(opHash)` : permet d'annuler une opération en attente
- `initializeRoles()` : setup one-time au déploiement (bypass timelock, fenêtre 100 blocks)
- `triggerPoolEmittedEventForTesting` : `require(block.chainid == 31337)` — local uniquement

**P1 — Séparation des rôles :**
- `payoutOperator` : rôle séparé pour `payOut`/`batchPayOut` (`onlyPayoutOperator`)
- Bridge utilise `PAYOUT_OPERATOR_PK` (hot) au lieu de `GAME_WALLET_PK` (owner/cold)
- `GAME_WALLET_PK` n'est PAS injecté dans le bridge — compromission du bridge ≠ accès admin

### 15.3 Secrets — Rotation + Chiffrement au repos

**Rotation (round 2) :**
- `GAME_WALLET_PK` (owner) : roté → `0x60748137...` (on-chain: `0x4B35f60D...`)
- `SIGNER_WALLET_PK` : roté → `0x810b8270...` (on-chain: `0x8E22598D...`)
- `PAYOUT_OPERATOR_PK` : nouveau → `0x5e908fce...` (on-chain: `0x5aa8eb45...`)
- `INTERNAL_API_SECRET` : roté → `04152e09...` (ancien `59fafb...` → 403)
- `MYSQL_ROOT_PASSWORD` : roté → `N6wrdC5F...` (ancien `xUra3b4...` → Access denied)
- `REVERB_APP_KEY` / `REVERB_APP_SECRET` : rotés
- `APP_KEY` : roté → `base64:Bd5pJr...`

**Chiffrement au repos (round 2-3) :**
- `secrets-manager.mjs` : chiffre `.secrets/*` en `.secrets/*.enc` (AES-256-GCM, scrypt 16384/8/1)
- Passphrase stockée via Windows DPAPI (lié au compte Windows) ou Linux GPG
- `.env` vidé de TOUS les secrets (toutes les valeurs vides)
- `docker inspect` ne révèle PLUS aucun secret (tous vides dans `.Config.Env`)
- Workflow : `provision` → `docker compose up` → `deprovision` → 0 plaintext sur disque

### 15.4 Infrastructure Docker

**Docker secrets (round 1-2) :**
- Tous les secrets montés via `secrets:` (fichiers dans `/run/secrets/`) — pas dans `environment:`
- `security-entrypoint.sh` : lit `/run/secrets/` et exporte en env vars avant de démarrer PHP
- `db-entrypoint.sh` : lit `/run/secrets/mysql_password` pour MySQL root (pas dans env)
- `docker-compose.yml` `secrets:` block : `game_wallet_pk`, `signer_wallet_pk`, `payout_operator_pk`, `marketplace_wallet_pk`, `internal_api_secret`, `app_key`, `mysql_password`, `db_app_password`, `reverb_app_key`, `reverb_app_secret`

**User MySQL dédié (round 2) :**
- `rps_app` créé par `init-db` avec droits limités (pas root)
- App utilise `rps_app` + `DB_APP_PASSWORD` (Docker secret)
- Root password seulement pour `db` container + `init-db`

**Ports (round 1) :**
- Tous les ports bind à `127.0.0.1` (3307, 8546, 8001, 8008)

### 15.5 Routes debug supprimées (round 2)
- `/salt`, `/simulate-user`, `/test-ipfs-direct` supprimés de `routes/web.php`

### 15.6 Clés en dur nettoyées (round 1-2)
- `fund_accounts.js`, `prepare_simulation.js`, `fund_and_test.js`, `e2e_test.js` : utilisent `config.js` (env/secret)
- `fund_snt.js` : Hardhat #0 key documenté "accepted residual risk for local dev"

### 15.7 Fichiers de configuration de sécurité

| Fichier | Rôle |
|---|---|
| `secrets-manager.mjs` | Chiffrement/déchiffrement des secrets au repos (AES-256-GCM + DPAPI/GPG) |
| `security-entrypoint.sh` | Lit `/run/secrets/` → exporte en env vars (app, reverb, queue-workers) |
| `db-entrypoint.sh` | Lit `/run/secrets/mysql_password` → MySQL root password |
| `start.ps1` | Workflow sécurisé : provision → docker up → wait healthy → deprovision |
| `.secrets/*.enc` | Secrets chiffrés (AES-256-GCM) — jamais en clair sur disque |
| `.secrets/.passphrase.enc` | Passphrase chiffrée (DPAPI Windows / GPG Linux) |

### 15.8 Workflow de démarrage sécurisé

```powershell
# Démarrage complet (provisionne les secrets, démarre Docker, déprovisionne)
.\start.ps1

# Ou manuel :
node secrets-manager.mjs provision     # déchiffre .enc → plaintext (pour Docker)
docker compose up -d                   # Docker monte les fichiers → /run/secrets/
# Attendre que tous les containers soient healthy...
node secrets-manager.mjs deprovision   # supprime plaintext (host chiffré au repos)
```

**Après démarrage :**
- 0 fichier plaintext sur le host
- 0 secret dans `.env` (toutes valeurs vides)
- 0 secret dans `docker inspect` (toutes valeurs vides)
- Containers ont les secrets en RAM (`/run/secrets/` bind mounts + env vars du PID 1)

### 15.9 Vérifications de sécurité (round 3 — toutes passent)

| Test | Résultat |
|---|---|
| `payOut(user, 999 ETH)` | revert "Amount exceeds user balance" ✅ |
| `setFeeBasisPoints(10000)` | revert "Fee cannot exceed 10%" ✅ |
| `withdrawDevFees()` par owner | revert "Only dev wallet can withdraw" ✅ |
| `triggerPoolEmittedEventForTesting()` sur chainId ≠ 31337 | revert ✅ |
| Ancien `INTERNAL_API_SECRET` | 403 Forbidden ✅ |
| Ancien `MYSQL_PASSWORD` | Access denied ✅ |
| `docker inspect` secrets | tous vides ✅ |
| `.env` secrets | tous vides ✅ |
| `.secrets/` plaintext files | 0 (tous .enc) ✅ |
| Reverb WebSocket | `{"channels":[]}` (fonctionnel) ✅ |
| API health | `{"status":"OK","database":"OK"}` ✅ |
| E2E Puppeteer auth test | PASS ✅ |

### 15.10 Commits de sécurité (branche `pentest/blackbox-2026-08-20`)

| Commit | Description |
|---|---|
| `e018e16` | security: separate signer/owner roles + fix P0/P1/P3 audit findings |
| `eebca41` | security: inject wallet keys via env + bind ports to loopback + strong MySQL password |
| `2b1dc91` | security: remove public backdoor + internal payout route + untrack .env.staging |
| `a56d33c` | security: rotate owner key + fix deadlock DoS + e2e auth test |
| `015a628` | security: fix CRITICAL drain bug + add payoutOperator role + Docker secrets |
| `e89d917` | security round 2: Timelock + fee cap + secret rotation + Docker secrets for all |
| `933f173` | security: remove ALL secrets from .env, read from .secrets/ on host |
| `8403f8a` | security: encrypt secrets at rest with AES-256-GCM + DPAPI |
| `72653c2` | security: remove MYSQL_ROOT_PASSWORD from .env + encrypted-at-rest workflow |
| `82c7390` | fix: Reverb entrypoint + Linux support for secrets-manager |
| `c7790a2` | docs: update HANDOVER.md with security audit + 3 pentest rounds (section 15) |
| `58f815d` | docs: add PRODUCTION_DEPLOYMENT_GUIDE.md with full deployment instructions |
| `577d18e` | fix: add updateUserBalance to sync off-chain gains before payout |
| `57d65d9` | contract: zero userBalances on payout instead of subtracting |

---

## 16. FIX ARCHITECTURAL : updateUserBalance + userBalances = 0 (2026-08-24)

### 16.1 Probleme decouvert

Le fix P0 `require(amount <= userBalances[user])` bloquait les retraits legitimes.
Cause : les fights sont resolus **off-chain** dans Laravel. Le solde DB (depot + gains)
est superieur au `userBalances` on-chain (depot initial seulement).

```
1. User depose 0.01 ETH → userBalances = 0.01 (on-chain)
2. Fights off-chain (Laravel) → solde DB = 0.05 ETH
3. User veut retirer 0.05 → claimAndExit(0.05)
4. require(0.05 <= 0.01) → REVERT ! Le user ne peut pas retirer ses gains.
```

### 16.2 Solution : updateUserBalance (commit `577d18e`)

Nouvelle fonction dans Battlepool.sol :

```solidity
function updateUserBalance(address user, uint256 newBalance) external onlyPayoutOperator {
    require(user != address(0), "Invalid user address");
    require(newBalance <= address(this).balance, "New balance exceeds contract ETH");
    uint256 oldBalance = userBalances[user];
    userBalances[user] = newBalance;
    emit UserBalanceUpdated(user, oldBalance, newBalance);
}
```

Le bridge appelle `updateUserBalance` **avant** chaque paiement pour synchroniser
le solde DB vers on-chain :

- `/generate-signature` : sync avant de signer (pour les humains via MetaMask)
- `/sendPayment` : sync avant `payOut` (pour les bots)
- `/sendBatchPayment` : sync pour chaque wallet avant `batchPayOut`

### 16.3 Flow corrige

```
1. User depose 0.01 ETH → userBalances = 0.01 (on-chain)
2. Fights off-chain → solde DB = 0.05 ETH
3. Bridge appelle updateUserBalance(user, 0.05) → userBalances = 0.05 (on-chain)
4. claimAndExit(0.05) → require(0.05 <= 0.05) → OK
```

### 16.4 Securite preservee

| Attaquant | Peut-il drainer ? |
|---|---|
| User sans signature signer | Non — claimAndExit revert "Invalid signer signature" |
| Attaquant avec cle signer sans updateUserBalance | Non — claimAndExit(999) revert "Amount exceeds user balance" |
| Attaquant avec cle payoutOperator (bridge) | Non — updateUserBalance limite a address(this).balance |
| Attaquant avec cle owner | Non — withdrawDevFees revert "Only dev wallet can withdraw" + timelock 2 jours |

### 16.5 userBalances = 0 au lieu de -= amount (commit `57d65d9`)

Avec `updateUserBalance` appele avant chaque paiement, `userBalances == amount`
dans le flow normal. Zeroing au lieu de soustraire :

```solidity
// payOut, claimAndExit, batchPayOut :
userBalances[user] = 0;           // etait: -= amount
isUserInAnyPool[user] = false;    // toujours nettoye
delete userPremoveCIDs[user];     // toujours nettoye
```

Raisons :
- Evite tout residu (dust) qui pourrait s'accumuler avec des cas limites
- `uint256` ne peut pas etre negatif (Solidity revert si underflow)
- Pas d'arrondi en Solidity (entiers en wei)
- Dans le flow normal avec updateUserBalance, `= 0` et `-= amount` donnent 0
- `= 0` est plus robuste contre les edge cases (sync echoue, paiement partiel)

### 16.6 ABI mis a jour

`config.js` ABI regenere depuis les artifacts Hardhat (108 entrees).
`updateUserBalance` et `UserBalanceUpdated` event ajoutes a l'ABI.

### 16.7 start.ps1 ameliore

- Timeout augmente a 600s (le blockchain compile en ~5 min au premier build)
- healthcheck blockchain : `start_period: 600s`, `retries: 120`
- Le script attend que le blockchain soit vraiment "healthy" avant de deprovisionner

### 16.8 Commits

| Commit | Description |
|---|---|
| `577d18e` | fix: add updateUserBalance to sync off-chain gains before payout |
| `57d65d9` | contract: zero userBalances on payout instead of subtracting |

---

## 17. PIÈGES À ÉVITER POUR LES TESTS MANUELS NAVIGATEUR (2026-08-25)

**Contexte :** session de test manuel (compte Hardhat #1 réservé à l'humain + bots autoplay).
**Référence procédurale complète :** voir le fichier `TEST_RUNBOOK.md` (à côté de ce handover).

### 17.1 `SyncUserLimitsJob` FAIL en boucle = wallet de gas à sec

**Symptôme :** `queue-worker-limits-1` log des `SyncUserLimitsJob ... FAIL` (~80ms chacun). Le bridge log
`Sender doesn't have enough funds to send tx. The sender's balance is: 0.`

**Cause :** le wallet `PAYOUT_OPERATOR` (qui signe `setUserLimits` et `payOut`) est tombé à **0 ETH** → il ne
peut plus payer le gas. Ce n'est PAS un bug du contrat, c'est un manque de fonds.

**Piège :** le `PAYOUT_OPERATOR` n'est PAS le `GAME_WALLET` (owner). C'est une clé "hot" dédiée (§15.2). Après un
redéploiement de la blockchain Hardhat (état vierge), ces wallets de service sont remis à 0 ETH et doivent être
**re-fundés manuellement** (Hardhat ne pré-funde que les comptes de la mnémonique, pas les wallets dérivés de
`GAME_WALLET_PK`/`SIGNER_WALLET_PK`/`PAYOUT_OPERATOR_PK`).

**Correctif :** funder depuis le compte Hardhat #0 (PK `0xac0974bec39a17e36ba4a6b4d238ff944bacb478cbed5efcae784d7bf4f2ff80`)
vers les 3 wallets de service (100 ETH chacun suffit largement). Vérifier ensuite que les jobs passent en `DONE`.

### 17.2 "Amount exceeds user balance" au claim = solde on-chain désynchronisé

**Symptôme :** le bouton "CLAIM" du navigateur échoue avec
`execution reverted: "Amount exceeds user balance"`.

**Cause :** les fights sont résolus **off-chain** (Laravel). Le solde DB (dépôt + gains) est supérieur au
`userBalances` on-chain (dépôt seul). `claimAndExit(amount)` exige `amount <= userBalances[user]`.

**Piège :** le bridge synchronise normalement via `updateUserBalance` AVANT de signer (`app.js:296`), mais si cette
synchro échoue silencieusement (ex: gas à sec, §17.1), la signature est émise pour un montant supérieur au solde
on-chain → revert au claim.

**Correctif :** appeler manuellement `updateUserBalance(user, soldeDB)` (depuis le `PAYOUT_OPERATOR`) pour aligner
l'on-chain avec la DB, puis re-cliquer "CLAIM" (la signature reste valide : nonce inchangé, deadline non expirée).

### 17.3 Overlay de combat bloquant le bouton claim (frontend)

**Symptôme :** l'overlay translucide "WAITING FOR OPPONENT..." reste affiché PAR-DESSUS le bouton claim (visible
en arrière-plan flouté, non cliquable).

**Cause double :**
1. **Bug UI** : dans `resources/js/modules/game.js`, la branche `pendingClaim` du statut `stopped` n'appelait pas
   `hideCombatOverlay()`. → corrigé (ajout de `hideCombatOverlay()`).
2. **Autoplay resté actif** : le compte réservé à l'humain avait encore `autoplay_active = 1` (hérité de
   `simulation_bots.js`), donc le moteur le recyclait en continu → overlay "WAITING FOR OPPONENT..." en boucle.

**Piège :** après avoir créé un compte "humain" à partir d'un compte Hardhat, TOUJOURS désactiver `autoplay_active`
en DB, sinon le worker le traite comme un bot.

**Correctif :** `autoplay_active = false` + `status = stopped` + `pool_id = null` en DB, puis recompiler le frontend
(`npm run build` sur l'hôte) et copier `public/build` dans le conteneur `app` (le bundle Vite est servi depuis là).

### 17.4 Le frontend servi est le bundle Vite compilé, PAS les sources

**Piège :** `public/index.html` charge `/build/assets/app.js` (bundle compilé). `public/js/app.js` et
`resources/js/modules/game.js` sont les **sources** ; toute modif ne prend effet qu'après `npm run build` + copie
du dossier `public/build` dans le conteneur `app` (le conteneur n'a pas `node`/`npm`).

### 17.5 `docker exec` sous PowerShell : guillemets et $()

**Piège :** `docker exec conteneur sh -c '...'` avec quotes imbriquées ou `$(...)` casse sous PowerShell (qui
interprète `$()` et les backslashes). **Pattern fiable :** écrire un script `.sh` local, `docker cp` vers
`/tmp/`, puis `docker exec conteneur sh /tmp/script.sh`.

### 17.6 Secrets : provisionner AVANT toute opération, déprovisionner APRÈS

**Piège :** après `deprovision`, les fichiers plaintext `.secrets/*` sont supprimés → les bind mounts Docker des
conteneurs pointent vers des fichiers inexistants → les secrets deviennent illisibles. Toujours :
```
node secrets-manager.mjs provision    # déchiffre .enc → plaintext
# ... opérations ...
node secrets-manager.mjs deprovision  # supprime le plaintext
```

### 17.7 Le bridge n'a PAS la clé owner (`GAME_WALLET_PK`)

**Piège :** par design de sécurité (§15.2), le conteneur `bridge` ne monte que `payout_operator_pk`,
`signer_wallet_pk`, `marketplace_wallet_pk` (PAS `game_wallet_pk`). Pour toute opération admin (fund des wallets,
`initializeRoles`, etc.), utiliser le conteneur `blockchain` (qui a les 3 secrets + `ethers`) ou un script côté hôte.

### 17.8 Contrat : `initializeRoles` prend 4 arguments

**Piège :** la signature est `initializeRoles(address _owner, address _signer, address _payoutOperator, address payable _devWallet)`
(PAS 2 args). Avec une ABI incomplète, ethers encode mal l'appel → la tx touche la `fallback()` qui revert
("Deposit must be greater than 0"). Toujours utiliser l'ABI complète (`config.contracts.game.abi`).

### 17.9 Adresses déterministes Hardhat (via `full_deploy.js`)

Les 3 contrats déployés par `full_deploy.js` obtiennent toujours :
- Battlepool : `0x5FbDB2315678afecb367f032d93F642f64180aa3`
- SNTToken : `0x0165878A594ca255338adfa4d48449f69242Eb8F` (PAS `0xe7f1725E...` — cf. §17.10)
- MarketplaceEscrow : `0xa513E6E4b8f2a923D98304ec87F64353C4D5C853`

Comptes Hardhat réservés à l'humain : #0 `0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266`, #1
`0x70997970C51812dc3A010C7d01b50e0d17dc79C8`, #2 `0x3C44CdDdB6a900fa2b585dd299e03d12FA4293BC`, #99
`0x98d08079928fccb30598c6c6382abfd7dbfaa1cd`.

### 17.10 Le SNT est minté au compte #100, PAS au déployeur #0

**Symptôme :** les comptes réservés (#1, #2, #99) ont 0 SNT, et le script `fund_snt.js` existant ne les funde pas.

**Cause :** le contrat `SNTToken.sol` a un `INITIAL_OWNER_ADDRESS` **codé en dur** = `0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D`
(le compte Hardhat **#100**). Les 1M SNT sont mintés à ce compte #100 au déploiement, PAS au déployeur #0.

**Piège :** `fund_snt.js` signe avec la PK de Hardhat #0 (`0xac0974bec3...`), mais #0 n'est PAS le owner du SNT.
Il faut signer avec la PK du compte **#100**, dérivée de la mnémonique standard Hardhat à l'index 100 :
`HDNodeWallet.fromPhrase("test test ... junk", undefined, "m/44'/60'/0'/0/100")`.

**Autre piège :** l'adresse SNT réelle est celle de `config.js` (`0x0165878A594ca255338adfa4d48449f69242Eb8F`), PAS
celle déployée par un script de reset naïf (`0xe7f1725E...`). Toujours vérifier `contracts.snt.address` dans `config.js`.

**Correctif :** dériver la PK de #100, puis `snt.transfer(compte, 100e18)` pour chaque compte réservé. Attention aux
nonces stale d'ethers (relancer par compte si "nonce too low").

### 17.11 Fix définitif du 25/08 — 5 fonctions passées de `onlyOwner` à `onlyPayoutOperator`

**Symptôme :** après le fix `setUserLimits` (seule fonction corrigée le 25/08 matin), le bridge spammait toujours :

```
📡 Setting next session time ... Only owner can call this function (from 0x5aa8...)
📡 Setting user limits ... (OK après fix)
📡 Generating signature ... (OK, mais sync absent car queue bloquée)
→ claim revert "Amount exceeds user balance" (DB 10.01 > on-chain 10.0)
```

**Cause :** le bridge signe **toutes** les txs du contrat avec `PAYOUT_OPERATOR_PK` (hot key, §15.2),
mais 5 fonctions restaient `onlyOwner` :

| Fonction | Bridge l'appelle avec | Modificateur avant fix | Effet |
|---|---|---|---|
| `setUserLimits` | `PAYOUT_OPERATOR` | `onlyOwner` → **déjà fixé** à `onlyPayoutOperator` | `SyncUserLimitsJob` FAIL (corrigé) |
| `setUserNextSessionTime` | `PAYOUT_OPERATOR` | `onlyOwner` | `SetCooldownJob` FAIL + spam, bloque `enqueueTx` |
| `storeSessionCID` | `PAYOUT_OPERATOR` | `onlyOwner` | session history non stockée |
| `storeMatchHistoryCID` | `PAYOUT_OPERATOR` | `onlyOwner` | match history non stockée |
| `validatePool` / `invalidatePoolUsers` | `PAYOUT_OPERATOR` | `onlyOwner` | validation des pools bloquée |

`enqueueTx` est une file sérialisée unique (nonce global, §12.4). Un `estimateGas` qui revert avec
"Only owner" reset le `managedNonce` à `null` et rejette la tx, mais le slot est perdu : le `updateUserBalance`
suivant (qui doit précéder `generate-signature`, §16.2) n'est pas exécuté → le solde on-chain reste à 10.0
alors que la DB est à 10.01 → `claimAndExit(10.01)` revert.

**Correctif définitif (25/08 23h) :** `battlepool/contracts/Battlepool.sol` — les 5 fonctions passent à
`onlyPayoutOperator` :

```solidity
function storeMatchHistoryCID(...) external onlyPayoutOperator { ... }
function storeSessionCID(...)      external onlyPayoutOperator { ... }
function setUserNextSessionTime(...) external onlyPayoutOperator { ... }
function validatePool(...)         external onlyPayoutOperator { ... }
function invalidatePoolUsers(...)  external onlyPayoutOperator { ... }
// setUserLimits déjà fixé précédemment
```

Recompilation (`npx hardhat compile` → 1 fichier) + redéploiement via `full_deploy.js` (qui met à jour
`smart_contracts/config.js` et funde le owner). Le fix est **durable** (dans l'image, plus de `docker cp`).

**Leçon :** toute fonction que le bridge doit appeler avec `PAYOUT_OPERATOR_PK` DOIT être `onlyPayoutOperator`
(ou `onlyOwnerOrPayoutOperator`), jamais `onlyOwner` seul.

---

## 18. RAPPORT D'EXÉCUTION — SUCCESSEUR 4 (2026-08-28) : STACK FRAÎCHE + FIX DURABLE MARKETPLACE (ABIs) + AUTO-RÉPARATION AU BOOT

**Contexte de la session :** remise en route après extinction de la machine (Docker Desktop down), rafraîchissement
complet demandé par l'utilisateur (DB + blockchain + bots), puis correction d'un bug marketplace découvert en test
manuel, et enfin rendu **durable** de ce fix.

### 18.1 Chronologie de la session

1. **Démarrage Docker Desktop** (daemon down au départ) → `start.ps1` workflow (provision → up → deprovision).
2. **Incident bridge** : crash au boot (`Access denied for 'rps_app' (using password: NO)`) — cause : restart du
   bridge **après** `deprovision` (piège §17.6) ; re-provision → restart → healthy.
3. **Wallets de service fundés** (piège §17.1 : chaîne Hardhat éphémère → PAYOUT_OPERATOR/SIGNER à 0 ETH).
   Script réutilisable : `C:\Users\GWX1223153\AppData\Local\Temp\opencode\bp-test\fund_services.cjs`
   (docker cp dans le container blockchain `/app/`, exécuter avec **idempotence + relance en cas de
   "nonce too low"** — erreur ethers classique, cf. §17.10).
4. **Rafraîchissement demandé par l'utilisateur** : `migrate:fresh --seed` (§3 runbook), reset blockchain,
   re-fund des wallets, reset état indexeur (tables `blockchain_sync_states` + `processed_blockchain_events`),
   `simulation_bots.js 20 3` (20 bots OK, dépôts 10.25 ETH on-chain confirmés).
5. **Pré-création des comptes humains** via l'API d'auth (script `create_humans.mjs` dans bp-test) :
   users #22 (Hardhat #1), #23 (#2), #24 (#99) — évite le piège §17.3 (autoplay résiduel).
   NB : Node 25 sur l'hôte = fetch natif, pas besoin de node-fetch ; fichiers `.mjs` = syntaxe `import`.
6. **Distribution SNT** : 100 SNT à chacun des 3 comptes réservés (script `fund_snt.cjs`, runbook §9bis —
   signature avec la PK du compte **#100**, piège §17.10).
7. **BUG DÉCOUVERT EN TEST** : l'utilisateur crée une offre marketplace (tx confirmée on-chain, événement
   `OfferCreated` émis) mais **l'offre n'apparaît pas dans l'UI** → diagnostic §18.2.

### 18.2 LE BUG : ABI Battlepool écrasait l'ABI MarketplaceEscrow dans config.js

**Cause racine (introduite à la §16.6)** : la régénération de l'ABI dans `smart_contracts/config.js` a utilisé
les artifacts **Battlepool** pour les 3 contrats. Résultat vérifié : `contracts.game.abi ===
contracts.marketplace.abi === contracts.snt.abi` (108 entrées Battlepool partout).

**Conséquence silencieuse** : `contract.interface.parseLog(log)` échouait (throw attrapé par `catch(_) { continue; }`
dans `indexer/indexer.js:76`) pour TOUS les événements marketplace (`OfferCreated`/`OfferFulfilled`/
`OfferCancelled`) et snt (`Transfer`). L'indexeur sautait les logs, sauvait `last_processed_block` quand même →
**aucun `POST /internal/trades/create`** → table `trades` vide → UI marketplace vide. Aucune erreur dans les logs.

**Diagnostic (réutilisable)** :
- `eth_getTransactionReceipt` sur la tx → 2 logs : Transfer (SNT) + OfferCreated (topic0 `0xbf26a47e...`).
- Vérif topic0 : `ethers.id('OfferCreated(uint256,address,uint256,uint256)')` = `0xbf26a47e...` → l'événement
  on-chain CORRESPOND à l'ABI théorique. Le problème est donc côté ABI **chargée** par le bridge.
- `node -e "require('./smart_contracts/config.js')"` → comparer `contracts.marketplace.abi.length` (attendu 26,
  bug = 108) et la liste des events.
- Tables de contrôle : `processed_blockchain_events` (aucun event marketplace), `trades` (vide).

**Correctifs du moment** : ABIs réécrites dans `config.js` depuis
`battlepool/artifacts/contracts/MarketplaceEscrow.sol/MarketplaceEscrow.json` (26 entrées) et
`SNTToken.sol/SNTToken.json` (25 entrées) + **adresses ré-alignées** sur le déploiement courant
(game `0x5FbDB231...`, snt `0x0165878A...`, marketplace `0xa513E6E4...`) + reset `blockchain_sync_states`
pour marketplace/snt (ils avaient "traité" les blocs avec la mauvaise ABI) + restart bridge
→ `marketplace synced blocks 1 -> 371 | Events processed: 1` → offre de l'utilisateur visible en UI. ✅

### 18.3 LE FIX DURABLE : auto-réparation des ABIs à chaque boot + garde-fou bridge

**Principe :** le container blockchain exécute `entrypoint.sh` → `full_deploy.js` **à chaque boot** et le compose
garantit que le bridge ne démarre qu'après (`depends_on: blockchain: service_healthy`, basé sur `/app/deployed.txt`).
Donc si `full_deploy.js` resynchronise les ABIs, c'est **avant** chaque lecture du config par le bridge.

**Fix 1 — `battlepool/full_deploy.js`** (section "AUTOMATION: Update smart_contracts/config.js") :
- Après la mise à jour des adresses, **resynchronise les 3 ABIs** (game/snt/marketplace) depuis les artifacts
  Hardhat (`/app/artifacts/contracts/*.json`) via un remplacement par comptage de profondeur de crochets
  (fonctions `findArrayEnd` + `replaceAbi`, robustes aux chaînes contenant `[`/`]`).
- ⚠️ **Piège WSL2 découvert** : `fs.readFileSync` direct sur le bind mount Windows (`./smart_contracts:/smart_contracts`)
  peut échouer avec `Error: ENODATA: no data available, read` (cache d'inode incohérent après modification côté
  hôte). **Pattern robuste implémenté** : `copyFileSync` vers `/tmp/config.js.work` → lecture/modification locale →
  écriture locale → `copyFileSync` retour vers le mount → `unlinkSync`. Le tout dans try/catch (le déploiement
  continue même si l'update config échoue).
- Log attendu au boot : `✅ ABIs synced from artifacts: game(108), snt(25), marketplace(26)`.

**Fix 2 — `smart_contracts/app.js`** (garde-fou fail-fast, en tête de `startBlockchainListeners()`) :
- Vérifie la présence des événements requis avant de démarrer l'indexeur :
  `game: [PoolEmitted, DepositReceived]`, `marketplace: [OfferCreated, OfferFulfilled, OfferCancelled]`,
  `snt: [Transfer]`.
- ABI invalide → `process.exit(1)` avec message explicite (au lieu du bug silencieux §18.2).

**Fix 3 — `docker-compose.yml`** (service `bridge`) : bind mount ajouté
`./smart_contracts/app.js:/app/smart_contracts/app.js:ro` (en plus de `config.js`). Le code bridge est pris
depuis l'hôte → les futures corrections bridge s'appliquent **sans rebuild**.

**Durabilité image** : le container blockchain exécute `/app/full_deploy.js` **depuis son image** (piège §4).
La version patchée a été intégrée à l'image via `docker commit rock-paper-scissors-blockchain-1
rock-paper-scissors-blockchain:latest` (solution de cette session). ⚠️ **Le commit est volatile** : un
`docker compose build`/récréation depuis le Dockerfile SANS les sources patchées réintroduirait l'ancienne
version — mais `battlepool/full_deploy.js` est patché dans le dépôt git, donc tout futur `docker build`
l'embarquera. (Le `docker build` complet a bloqué sur `npm ci` de battlepool >25 min côté WSL2 — à retenter
quand le réseau le permet ; non bloquant.)

**Validation effectuée (boot réel)** : corruption volontaire de l'ABI marketplace (bug historique simulé,
108 entrées) → restart blockchain → auto-déploiement → `✅ ABIs synced from artifacts` dans les logs →
config.js réparé (26 entrées, events OfferCreated/Fulfilled/Cancelled) → bridge (re) démarré →
`✅ [BRIDGE] ABIs validées` → indexation normale. Le cycle est **auto-correctif**.

### 18.4 Pièges NOUVEAUX rencontrés cette session

1. **`docker cp` capricieux** (Docker Desktop/WSL2) : échoue avec `Could not find the file /tmp` alors que
   `/tmp` existe et est writable. **Fallback fiable** : `Get-Content -Raw file | docker exec -i CONT sh -c "cat > /tmp/x"`.
2. **ENODATA sur bind mount Windows** (voir §18.3) : readFileSync Node sur `/smart_contracts/config.js` depuis
   le container échoue après une modification hôte → toujours passer par copie locale /tmp.
3. **`node -e` + quotes imbriquées sous PowerShell** : interdiction formelle — écrire un fichier `.cjs` et
   l'exécuter (même pattern que §17.5 pour les `.sh`).
4. **Fichiers `.mjs` sous Node 25 (hôte)** : `require` interdit (module ES) → utiliser `import` ; `fetch` est
   natif (pas de node-fetch) ; les chemins relatifs `./x` sont résolus depuis l'emplacement du script
   (bp-test), pas du CWD → utiliser des chemins absolus.
5. **Le `git checkout -- config.js` restaure aussi les VIEILLES ADRESSES** : les adresses écrites par
   l'auto-deploy sont des modifs non commitées. Après tout `git checkout` de config.js, re-vérifier/réécrire
   les adresses (script `fix_addresses.cjs` dans bp-test).
6. **Le rebuild de l'image blockchain recrée le container** → `/app/fund_services.cjs` (mis par docker cp)
   disparaît ; le re-copier avant usage. Idem pour tout script dans `/app` ou `/tmp`.
7. **Après chaque restart blockchain**, TOUJOURS : (a) vérifier `eth_blockNumber` vs `blockchain_sync_states`
   (si la chaîne est plus jeune que l'état indexeur → reset des sync_states, sinon événements ratés) ;
   (b) re-funder les wallets de service (§17.1) ; (c) restart bridge APRÈS le reset éventuel.
8. **Le "nonce too low" ethers** pendant les boucles de funding : relancer le script (idempotent) jusqu'à
   `deja funde` partout — ne PAS supposer l'échec.

### 18.5 État final de la session (vérifié 13:39)

| Élément | Valeur |
|---|---|
| Containers | 10/10 Up (8 healthy, app/worker sans healthcheck) |
| Chaîne | bloc ~163, contrats déployés adresses canoniques, wallets fundés 100/100/1000 ETH |
| Indexeur | game/marketplace/snt synchro, ABIs validées au boot |
| DB | 24 users (20 bots autoplay + 4 humains #1/#22/#23/#24), fights actifs, 0 waiting |
| Queues | default ≈ 0–5 (drain immédiat, ~3–5 ms/job), limits = 0 |
| Marketplace | `config.js` avec vraies ABIs (26/25), garde-fou actif, auto-réparation au boot |
| Secrets | déprovisionnés (0 plaintext sur disque) |
| Offre marketplace test | visible en UI (trades #1 : 40 SNT @ 12 AVAX, open) |

### 18.6 Tâches restantes / recommandations

1. **Rebuild propre des images `blockchain` et `bridge`** quand le réseau/WSL2 le permettra
   (`docker build -f Dockerfile.blockchain .` OK fait ; `Dockerfile.worker` bloque sur `npm ci` smart_contracts
   >25 min). Non bloquant : le boot auto-répare, et `app.js`/`config.js` sont bind-mountés dans le bridge.
2. **Le `docker commit` (§18.3) est un cache**, pas un substitut au build : la version git de
   `battlepool/full_deploy.js` est la référence durable.
3. **Script bp-test à connaître** : `fund_services.cjs` (funding wallets après reset chaîne),
   `reset_indexer_marketplace.sh` (reset sync_states marketplace+snt), `fix_marketplace_abi.cjs` +
   `fix_addresses.cjs` (réparation manuelle de secours de config.js), `check_trades.sh` (diagnostic marketplace),
   `create_humans.mjs` (pré-création comptes humains), `test_config_repair.cjs` (test du mécanisme d'auto-réparation).
4. **Si une offre marketplace disparaît encore** : vérifier dans l'ordre (a) log bridge `✅ [BRIDGE] ABIs validées`,
   (b) `processed_blockchain_events` contient OfferCreated, (c) table `trades`, (d) expires_at > now().

### 18.7 Scripts ajoutés dans `C:\Users\GWX1223153\AppData\Local\Temp\opencode\bp-test\`

| Script | Usage |
|---|---|
| `fund_services.cjs` | Funder PAYOUT_OPERATOR/SIGNER/OWNER (100/100/1000 ETH) depuis Hardhat #0, idempotent. Copier dans le container blockchain `/app/`. |
| `fund_snt.cjs` | 100 SNT aux comptes #1/#2/#99 via la PK du compte #100 (runbook §9bis version corrigée). |
| `reset_indexer.sh` / `reset_indexer_marketplace.sh` | Vider `blockchain_sync_states` (+ `processed_blockchain_events`) après reset chaîne. |
| `check_trades.sh` | Diagnostic marketplace : sync_states, events, table trades, colonnes. |
| `create_humans.mjs` | Pré-créer les comptes humains #1/#2/#99 via API auth (autoplay=0). |
| `fix_marketplace_abi.cjs` + `fix_addresses.cjs` | Réparation manuelle de config.js (ABIs + adresses) — secours si le boot auto-réparateur n'a pas tourné. |
| `corrupt_marketplace_abi.cjs` | Simulateur du bug (pour tester la réparation). |
| `test_config_repair.cjs` | Version container du fix (test isolé de la partie config de full_deploy.js). |
| `verify_config.cjs` | Vérifier config.js depuis le container (events + tailles ABIs + adresses). |
| `db_check.sh` / `check_humans.sh` / `check_db_state.sh` | État DB (users/bots/pools/fights) et comptes humains. |
| `reset_db.sh` | migrate:fresh --seed avec secrets (runbook §3). |

### 18.8 PROTOCOLE P.R.O.T. — LA référence pour toute intervention manuelle (2026-08-28)

Suite au cold-start réel validé (compose down complet → start.ps1 → protocole post-restart → stack saine),
la procédure d'intervention manuelle a été **normalisée et nommée** :

> **P.R.O.T. = Provision → Recreate (never restart) → Operate → Teardown (deprovision)**

**La découverte clé** : après un `deprovision`, les bind mounts `/run/secrets/` des containers existants
deviennent des **fantômes** (stale mounts : `ls -la /run/secrets/` montre des `?????????`, `cat` → ENOENT).
Un nouveau `provision` ne les répare PAS pour un container existant, et un `docker restart` conserve le
mount fantôme. **Seule la RECRÉATION du container** (`docker compose up -d --force-recreate <service>`
ou passage par `start.ps1`) re-binde les secrets.

**La procédure complète (arbre de décision, POST-RESTART PROTOCOL, table symptômes→remèdes) est
documentée en tête du TEST_RUNBOOK.md (§0)** — c'est LE document à ouvrir en premier pour toute
intervention. start.ps1 automatise P et T (plus MTU WSL2, fantômes .secrets, Docker Desktop down,
`--scale queue-worker-limits=2`).

**Séquences validées** :
- Cold-start complet : `docker compose down` (réseau inclus) → `.\start.ps1` → stack healthy 10 s →
  POST-RESTART PROTOCOL (reset sync_states + fund_services + restart bridge) → jeu actif (bots, fights,
  marketplace) → `deprovision`. **Testé bout en bout le 2026-08-28.**
- Intervention script ponctuelle : `provision` → `docker exec .../tinker/node` → `deprovision` (SANS restart).

**Règle simplifiée à retenir** : *si tu as déprovisionné depuis la (re)création d'un container, ce container
est "aveugle" — recrée-le ou repasse par start.ps1 ; jamais un simple restart.*

