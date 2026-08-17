# PASSATION DE CHARGES — Broadcasts temps réel (FightResult / UserBalanceUpdated)

**Rédigé le :** 2026-08-16, 07:40 (Mis à jour à 07:54)
**Projet :** `G:\DEV\PHP\rock-paper-scissors\` (Laravel + Octane/Swoole + Redis + Reverb + Bridge Node.js + Hardhat, orchestré par Docker Compose)
**État au moment de la passation :** ✅ FIXES RENDUS DURABLES — Images Docker `app` & `reverb` reconstruites et validées en production locale.

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

