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
> (`srv2032251`, `31.187.72.98`). Les deux peuvent déployer → verrou §4.
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
| S3 — En-têtes sécurité Caddy `app2.*` | TODO | CLOUD | — | — | `frame-ancestors` autorisant `battlepool.*`, `Referrer-Policy`… |
| S4 — Durcir le catch-all (404 au lieu de SPA pour chemins inconnus) | TODO | LOCAL | — | — | après suppression endpoints dev |
| S5 — Monitoring & backups (cron validateur < 0.2 AVAX, backup DB testé) | TODO | LOCAL | — | — | VPS |
| S6 — CI/CD (gates tests + scan secrets, images versionnées, deploy, rollback) | TODO | LOCAL | — | — | `.github/workflows/` |
| S7 — Revue de la modif « deep-link » (retrait iframe/pont) | TODO | CLOUD | — | — | audit de `44644ed` |
| S8 — Domaine réel + TLS | BLOCKED | LOCAL | — | — | bêta privée d'abord (sslip.io conservé) |
| S9 — Performance App 2 (`.pck` 67 Mo) | TODO | CLOUD | — | — | hors App 1 |

---

## 4. Verrou & déploiement

### 4.1 Fichiers chauds
- Aucun verrou actif.
- Avant d'éditer un hotspot : écrire `Verrou fichier : <fichier> par <owner> (depuis <date>)`,
  puis le retirer en fin de tâche.

### 4.2 Déploiement (pipeline COMPLET — rebuild images)
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
| Dernier déployé | `4caab5f` par LOCAL (2026-10-18) |
| Déployeurs | LOCAL, CLOUD (root VPS) |
| Verrou deploy | (aucun) |

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
