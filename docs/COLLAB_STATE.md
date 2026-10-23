# COLLAB STATE — App 1 « BattlePool » (rock-paper-scissors)

> **Tableau noir partagé** entre l'agent **LOCAL** (poste Windows) et l'agent **CLOUD**
> (`root@srv2032251`, VPS `31.187.72.98`). Règles : petits commits ; **jamais deux agents
> en même temps** sur ce fichier ; journal de passation **append-only** dans `docs/handoffs/`.

---

## 1. Ancrages (source de vérité — ne pas diverger)

| Élément | Valeur |
|---|---|
| Remote | `github.com/gigamedard/rock-paper-scissors` |
| Branche d'intégration | `master` (PR uniquement) |
| Branche de travail courante | `feat/app1-deeplink-app2` |
| SHA déployé prod | `4caab5f` |
| URL prod | `https://battlepool.31.187.72.98.sslip.io/` |
| VPS | `/opt/battlepool` (git clone géré par `run_deploy.ps1`) |
| Chaîne | Pingala **99999** (`0x1869f`), symbole **TST** |
| Contrats (Pingala) | `Battlepool` `0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF` · `PranaToken` `0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B` · `MarketplaceEscrow` `0x6C12875a81356d6b3042737C8eb7AAE9B4a088ac` · `WAVAX` `0x0a324396FB40C9cf10f665bFDb21FFceBe66CfD4` |
| Owner / deployer | `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` |
| Déploiement | `deploy/run_deploy.ps1 -GitSha <sha>` ; secrets `node secrets-manager.mjs provision` puis `deprovision` |
| Référence adresses | `battlepool/pingala_deployments.json` |

> **Agents** : LOCAL = poste Windows (déploie via SSH) · CLOUD = **root sur le VPS**
> (`srv2032251`, `31.187.72.98`, session locale, docker OK).
> **Déploiement = LOCAL uniquement** : le CLOUD n'a pas `deploy/run_deploy.ps1`,
> `secrets-manager.mjs` ni la clé `deploy/id_ed25519_deploy` (gitignored, absents de son
> workspace) → il **ne déploie pas** ; il prépare/pousse une branche, LOCAL exécute le pipeline.
>
> **Note VPS** : `/opt/battlepool` est un clone git ; `run_deploy.ps1` y fait
> `fetch` puis checkout du SHA (la branche `master` du VPS est **épinglée** au SHA déployé,
> pas à `origin/master`). Le code applicatif **n'est pas monté en volume** : modifier la
> source, puis rebuild via le pipeline.

---

## 2. Propriété des zones (anti-conflit)

| Zone | Owner | Fichiers |
|---|---|---|
| Front | LOCAL | `resources/js/**` (dont `web3/`), `public/index.html`, `public/js/config.js`, `public/portal.html` |
| Backend | LOCAL | `app/**`, `routes/**`, `config/**` |
| Contrats | LOCAL | `smart_contracts/**`, `battlepool/**` |
| Deploy / infra | LOCAL | `deploy/**`, `docker-compose.prod.yml`, `Caddyfile.battlepool.snippet`, `proxy/nginx.conf` |
| Ops VPS / DB / `.env` / Caddy | LOCAL | `/opt/battlepool` (hors repo) |
| Docs | PARTAGÉ | `docs/*`, `*.md` |

> **Hotspots** (jamais les deux agents en même temps) : `public/index.html`,
> `resources/js/app.js`, `resources/js/web3/web3-core.js`, `routes/api.php`,
> `deploy/deploy_vps.sh`.
>
> Le CLOUD a récemment livré du front (deep-link App2, manifest PWA). Toute prise de
> zone par le CLOUD se **déclare dans §3** + **verrou §4** avant édition.

---

## 3. Tâches

| ID | Statut | Owner | Branche | Commit | Note |
|---|---|---|---|---|---|
| C0 — Mettre en place le protocole LOCAL ↔ CLOUD | DONE | LOCAL | `docs/app1-collab-protocol` | (ce commit) | `docs/COLLAB_STATE.md` + `AGENT_ONBOARDING_APP1.md` + handoff |
| S1 — Quick wins sécurité prod | DONE | LOCAL | `feat/app1-deeplink-app2` | `4caab5f` | endpoints dev/test supprimés, `/api/debug-referral` retiré, debug 🐞 dev-only, `LOG_LEVEL=warning`, en-têtes Caddy |
| S2 — Rotation clé deployer (owner contrats) | TODO | LOCAL | — | — | owner `0xD629…` ; `queueTransferOwnership` + timelock — mission blockchain |
| S3 — En-têtes sécurité Caddy `app2.*` | HORS APP1 | (App 2) | — | — | traité dans le repo App2 ; **interdit §5 côté App 1** |
| S4 — Durcir le catch-all (404 au lieu de SPA pour chemins inconnus) | TODO | LOCAL | — | — | après suppression endpoints dev |
| S5 — Monitoring & backups (cron validateur < 0.2 AVAX, backup DB testé) | TODO | LOCAL | — | — | VPS |
| S6 — CI/CD (gates tests + scan secrets, images versionnées, deploy, rollback) | TODO | LOCAL | — | — | `.github/workflows/` |
| S7 — Revue de la modif « deep-link » (retrait iframe/pont) | IN PROGRESS | CLOUD | `feat/app1-deeplink-app2` | — | audit lecture seule de `44644ed` |
| S8 — Domaine réel + TLS | BLOCKED | LOCAL | — | — | bêta privée d'abord (sslip.io conservé) |
| S9 — Performance App 2 (`.pck` 67 Mo) | HORS APP1 | (App 2) | — | — | traité dans le repo App2 ; **interdit §5 côté App 1** |
| S10 — Activer le faucet airdrop 50 TST (ops VPS) | DONE | CLOUD | `docs/cloud-faucet-2026-10-18` | (doc) | secret `faucet_wallet_pk` monté (compose VPS), bridge recréé, faucet financé 1000 TST, E2E validé +50 TST. Voir `docs/handoffs/2026-10-18-cloud.md` |

---

## 4. Verrou & déploiement

### 4.1 Fichiers chauds
- Aucun verrou actif.
- Avant d'éditer un hotspot : écrire `Verrou fichier : <fichier> par <owner> (depuis <date>)`,
  puis le retirer en fin de tâche.

### 4.2 Déploiement (pipeline COMPLET — rebuild images)
- **Déployeur = LOCAL uniquement** (le CLOUD n'a pas l'outillage : `run_deploy.ps1`,
  `secrets-manager`, clé `deploy/`). Le CLOUD prépare/pousse la branche ; LOCAL déploie.
- **Un seul déployeur à la fois** : écrire `Verrou deploy : <owner> (depuis <date>)` avant
  toute opération VPS, puis retirer après.
- Déploiement = `node secrets-manager.mjs provision` → `powershell deploy\run_deploy.ps1 -GitSha <sha>`
  → `node secrets-manager.mjs deprovision`. Jamais de déploiement « au passage ».
- **Jamais** de déploiement concurrent (build/up/secrets simultanés = panne).
- Après déploiement : renseigner `Dernier déployé : <sha> par <owner> (<date>)`.
- **Ne JAMAIS `docker cp`** (perdu au recreate) : modifier la **source**, puis rebuild via
  `run_deploy.ps1`.

| Déploiement | Valeur |
|---|---|
| Dernier déployé | `f9daa6f` par CLOUD (2026-10-23) |
| Déployeurs | **LOCAL (pipeline canonique `run_deploy.ps1`)** ; CLOUD sur mandat explicite via `docker build`/`compose` sur le VPS |
| Verrou deploy | (aucun) |

> **Note déploiement `d9ef144` (2026-10-18, CLOUD, mission explicite opérateur)** : app
> reconstruite (`battlepool-app:d9ef144`, fix `auth.js` compte actif) et recréée
> (`up -d --no-deps app`) ; `reverb` recréé (`4caab5f`). Bridge/faucet non touchés (secret
> `faucet_wallet_pk` intact). `/opt/battlepool` épinglé à `d9ef144`. Tous conteneurs healthy,
> `/api/health` OK, bundle servi contient le fix.

> **Note faucet (2026-10-18, CLOUD, S10)** : `faucet_wallet_pk` a été ajouté au
> `docker-compose.prod.yml` **du VPS** (service `bridge` + section `secrets`) puis le
> conteneur `bridge` recréé, **sans changement de SHA** (`4caab5f`, pas de rebuild d'image).
> Ce compose est **gitignored et hors zone CLOUD** → **LOCAL doit conserver ce bloc à chaque
> déploiement**, sinon le secret n'est plus monté et `/faucet` renvoie `503`.

---

## 5. Périmètre & sécurité (CLOUD = root sur le VPS)

Les interdits sont **procéduraux** (le CLOUD est root).

**Autorisé** (avec mandat de mission) :
- édition source + `deploy/run_deploy.ps1` ; `scp` des fichiers non buildés ;
- `docker logs/ps`, lecture des health.

**Interdit (absolu)** :
- `avalanchego`, `signature-aggregator`, conteneurs `web3_combat-*`, `/opt/web3-combat`,
  blocs Caddy L1/App2.
- Opérations destructives DB (`truncate`, `migrate:fresh`, `DROP`) sans mandat explicite + backup.
- Copier/exposer `.env.staging`, `.secrets/`, clés privées, `battlepool/.env`.
- Committer `scratch/`, `graphify-out/`, `deploy/`, `proxy/certs/`.

---

## 6. Dernière passation

- Voir `docs/handoffs/2026-10-18-local.md`.
