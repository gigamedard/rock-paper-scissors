# RUNBOOK — Déploiement des contrats sur Pingala Chain (L1 souveraine)

> **Référence de connaissance** — comment redéployer les contrats BattlePool sur
> Pingala (chainId **99999**) de façon sûre et répétée.
> Ce document est né de la migration réelle Hardhat → Pingala du 2026-10-04
> (3 itérations QA, verdict **APPROUVÉ**). Chaque règle ci-dessous a causé ou
> évité un incident réel.
>
> Prérequis de lecture : `.agents/ORCHESTRATOR.md` (protocole), `.agents/AGENTS.md` (règles d'or).

---

## 1. Fiche réseau — Pingala Chain

| Paramètre | Valeur |
|---|---|
| Nom | **Pingala Chain** (L1 Avalanche souveraine, subnet-evm v1.15.1) |
| Chain ID | **99999** (`0x1869f`) |
| RPC | `https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc` |
| Symbole natif (gas) | **TST** (testnet — 25 gwei baseFee, EIP-1559 OK) |
| Explorer | **Aucun** — pas de vérification de contrat possible |
| Token de jeu | **PRANA** (ERC-20) — alias technique `snt` côté bridge/ABI |
| Token wrap AVAX | **WAVAX** (ERC-20 passif, pattern WETH9) |

### Contrats déployés (ordre nonce — à ne jamais modifier)

| # | Contrat | Adresse | Tx hash (tronqué) |
|---|---|---|---|
| 1 | Battlepool | `0x0790C2e42DB6A97cBE5Ba0DeD6F912868b0f62EF` | `0x2a3349f6…` |
| 2 | PranaToken | `0x38ef4A4D6cd01949123bF1f8EB73B0ac0D09903B` | `0xa9915f14…` |
| 3 | MarketplaceEscrow | `0x6C12875a81356d6b3042737C8eb7AAE9B4a088ac` | `0x6fdc888a…` |
| 4 | WAVAX | `0x0a324396FB40C9cf10f665bFDb21FFceBe66CfD4` | `0x714f02b9…` |

Paramètres on-chain vérifiés : `feeBasisPoints=250` (2.5 %) · `securityCoefficient=1000` ·
`defaultMaxBaseBet=100` · `defaultMaxQ=2.0` · `initializeRoles(owner, signer, payoutOperator, devWallet)` — **4 arguments obligatoires**.

### Wallets de rôle (secrets chiffrés `.secrets/*.enc` — JAMAIS en clair)

| Rôle | Adresse | Balance à la migration |
|---|---|---|
| Owner / deployer | `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` | ~8.86 TST |
| SIGNER | `0xBe24F203E4e35A79E242d77c3710cE608C16a3De` | 0.2 TST |
| PAYOUT_OPERATOR | `0x9A60327ce58A94a411987119047A75A8F1076663` | 0.5 TST (+100 000 PRANA) |
| DEV (fee collector) | `0x153d0C405a415F3E01188F46dAA80D1fcb2C2166` | 0.2 TST (+50 000 PRANA) |
| MARKETPLACE | `0xaa5F3C4B29599083245B3D7F9C90842dd8A12cc5` | — (récepteur P2P) |

> Registre complet + tx hashes : `battlepool/pingala_deployments.json` (0 clé privée, scanné).

---

## 2. Procédure de déploiement (répétable, dans l'ordre)

```powershell
cd battlepool

# 0. Pré-requis : la clé deployer est dans battlepool/.env (GITIGNORED — vérifier AVANT):
#      PINGALA_DEPLOYER_PK=0x…       (et RIEN d'autre de sensible)
git check-ignore battlepool/.env          # DOIT afficher le fichier (il est ignoré)

# 1. PRÉFLIGHT — valide clé↔adresse, RPC joignable, budget gas suffisant.
node scripts/preflight_pingala.js          # exit 2 = STOP, déploie rien

# 2. DÉPLOIEMENT — séquentiel, Battlepool → params → roles → Prana → Escrow → WAVAX
npx hardhat run full_deploy_pingala.js --network pingala

# 3. VÉRIFICATION on-chain (lecture seule) — fee, K, rôles, supply, invariants
npx hardhat run scripts/verify_pingala.js --network pingala

# 4. TESTS unitaires (réseau Hardhat in-process, pas la L1)
npx hardhat test                           # 50 passing attendus

# 5. PROPAGATION DE LA CONFIG (fait automatiquement par le deploy script)
#    - smart_contracts/config.js  : adresses + ABIs (clés snt/wavax/marketplace/game)
#    - .env.staging               : adresses publiques (JAMAIS de PK)

# 6. BRIDGE : config.js + app.js sont MONTÉS en volume → un restart suffit
docker restart rock-paper-scissors-bridge-1
# MAIS si .env.staging a changé → recréation obligatoire (env rechargée):
node ../secrets-manager.mjs provision
docker compose up -d --force-recreate bridge
node ../secrets-manager.mjs deprovision
```

### Après un déploiement : checklist de mise en service

- [ ] `pingala_deployments.json` mis à jour (adresses + tx hashes, scan anti-PK).
- [ ] `smart_contracts/config.js` : 4 adresses à jour + ABIs synchronisés (vérifier `node --check`).
- [ ] `.env.staging` : adresses publiques à jour (0 clé privée).
- [ ] `.env.staging.example` (tracké) à jour pour la documentation.
- [ ] **Wallets de rôle fundés** (deployer → signer/payoutOp/dev). Piège §17.1 : un wallet custom n'est **jamais** pré-fundé.
- [ ] **PRANA distribué** : payoutOperator (récepteur des ventes de cartes) + dev.
- [ ] `docker restart bridge` (ou recreate si env) → logs : chainId **dynamique 99999**, ABIs validées, indexer sur les 4 contrats.
- [ ] Cache Laravel vidé (`Cache::forget('game_config')` — TTL 300 s sinon l'ancien shape reste servi).
- [ ] MetaMask des joueurs : **Reset Account** (Paramètres → Avancé → Effacer l'activité) après tout déploiement.

---

## 3. ERREURS RÉELLEMENT RENCONTRÉES → SOLUTIONS (à ne pas re-reproduire)

### 3.1 Les clés privées
| ❌ Erreur commise | ✅ La règle |
|---|---|
| Clé du deployer **collée en clair dans une conversation** | Une clé partagée est une clé compromise : elle vit UNIQUEMENT dans `battlepool/.env` (gitignored) ou un secret Docker. Après usage, **la rotation est obligatoire**. |
| `INITIAL_OWNER_ADDRESS` **codée en dur** dans `SNTToken.sol` → 1M PRANA mintés sur une adresse sans clé ni gas | Le constructeur doit prendre l'owner **du contexte** (`Ownable(msg.sender)`) — jamais une constante. Corrigé par re-déploiement v2. |
| `marketplace_wallet_pk` = compte **public Hardhat #1** (clé connue de tous) | Un secret = une clé générée aléatoirement et dédiée. Le scan (`0x[0-9a-fA-F]{60,}`) doit ne trouver que des tx hashes. |
| PK dans un heredoc PowerShell visible / affichée | Ne jamais `cat`/afficher une PK. Dérive l'**adresse** via ethers et n'affiche qu'elle. |

### 3.2 Le gas et les adresses
| ❌ Erreur | ✅ Solution |
|---|---|
| `gasLimit 10M > block gas limit 8M` (subnet-evm) → tx rejetée | Estimer dynamiquement + marge, **plafonné au block limit lu on-chain**, STOP si ≥. |
| Wallets de rôle non fundés → tx `Sender doesn't have enough funds` | Funder depuis le deployer AVANT les déploiements (0.2–0.5 TST/wallet suffit). |
| Ordre de déploiement qui décale les adresses des contrats consommés | Déployer le **nouveau contrat (WAVAX) en DERNIER** — l'ordre nonce détermine les adresses (pas de déterminisme Hardhat ici). |
| `MarketplaceEscrow` lié à un token via **`sntToken` immutable** (aucun setter) | Changer le token = **re-déployer le marketplace**. Prévoir la séquence : Prana d'abord, Marketplace ensuite, et vérifier `nextOfferId == 1` (aucun fonds abandonné) avant. |
| Déploiement lancé sans réseau valide | `preflight_pingala.js` avant TOUT (garde anti-fausse chaîne + anti-mismatch clé/adresse). |

### 3.3 La config et le cache
| ❌ Erreur | ✅ Solution |
|---|---|
| `CHAIN_ID` utilisé sans être **importé** dans `app.js` → `ReferenceError` au runtime (syntaxe OK, crash à l'appel) | `node --check` ne voit pas les identifiers non résolus : **tester l'endpoint réel** (HTTP 200 + payload) après chaque modification du bridge. |
| `get-game-config` qui n'expose pas `wavax` → le front ne reçoit pas le wrapper | Exposer **tous** les contrats consommés par le front dans la réponse du bridge. |
| Cache Laravel `game_config` **TTL 300 s** : après resync des adresses, l'**ancien** shape continue d'être servi 5 min | `Cache::forget('game_config')` après tout changement d'adresse — sinon le front appelle des contrats morts. |
| Clé `contracts.snt` renommée (casserait ~25 scripts + indexer + PHP) | Le **nom technique** (`snt`) reste un alias — seul le **contenu** change (adresse du PranaToken). Le symbole ERC20 renvoyé par le contrat est PRANA. |
| ABI `MarketplaceEscrow.sntToken()`/`sntAmount` renommés | Idem : garder les identifiants ABI, renommer seulement les libellés UI. |

### 3.4 Le tooling Windows (récurrence — voir aussi `.agents/AGENTS.md` §7)
| ❌ Erreur | ✅ Solution |
|---|---|
| `docker cp` → `Error response from daemon: Could not find the file …` (bug Windows) | Passer par `docker exec -i <ctr> sh -c "cat > /tmp/x.sh"` + `Get-Content | docker exec -i` (flux stdin octet-exact). |
| `Set-Content`/pipeline PowerShell → bundle **corrompu** (BOM, CRLF, hash ≠) | JAMAIS de transfert de binaire via un pipe PowerShell : redéployer via **rebuild d'image** (`npm run build` dans le Dockerfile) = seule voie fiable. |
| `tail -2` / `2>&1` dans un script sh BusyBox → « Bad fd number » | Scripts sh : pas de redirections de fd, pas de `tail`. Un `sh -c "... " && echo done`. |
| Here-string PowerShell → `--force\n` (option inexistante) | Le pipe PowerShell injecte des CRLF : écrire dans un **fichier** via l'outil Write, puis le pousser en flux. |
| `docker cp` silencieusement ignoré (sortie masquée par `Out-Null`) | Ne jamais masquer la sortie d'un docker cp : vérifier par hash. |
| `$()` interprété par PowerShell dans une chaîne double-quote | Échapper, ou écrire le script dans un fichier (§7 AGENTS.md). |

### 3.5 Le processus
| ❌ Erreur | ✅ Solution |
|---|---|
| Subagent qui « invente » un état | Exiger des **preuves brutes** (commandes + sorties) ; l'auditeur QA re-vérifie par ses propres mesures, pas sur déclaration. |
| Auditeur basé sur un grep du bundle (noms minifiés) → faux positif | Sur un bundle minifié, la preuve = **exécution runtime** (DOM shim) + absence de `identifiers morts` en clair. |
| Déploiement sans préflight | `preflight` = garde anti-fausse chaîne, anti-mismatch clé/adresse, anti-budget — **non optionnel**. |
| `.secrets` laissé en plaintext après opérations | `provision` avant → `deprovision` après — **chaque fois**, sans exception (§5 AGENTS.md). |

### 3.6 Wallet mobile (session réelle du 2026-10-05 — 3 causes empilées)

Régression vécue : le toast « project ID missing » est **revenu après** sa correction. Cause : rebuild lancé **à la main sur le VPS** sans exporter les `VITE_*` → ARG vide → bundle sans projectId. Trois causes distinctes, dans l'ordre d'apparition :

| ❌ Erreur (avec la preuve mesurée) | ✅ Solution |
|---|---|
| **`.env` exclu du build** (`.dockerignore`) → `VITE_WALLETCONNECT_PROJECT_ID` = undefined → AppKit toast « project ID missing » | Passer les valeurs **NON secretes** via `ARG`/`ENV` du Dockerfile (`deploy/Dockerfile.prod`) + compose `build.args` — le `.env` **reste exclu** du contexte. |
| **`.env.staging` en CRLF Windows** (106 CR/106 LF mesurés) → le parser d'env du build Vite colle un `\r` à la VALEUR : `projectId="e46…95\r"` = **33 chars** → **403 de toutes les API Reown** (« projectId must be 32 characters ») → **la modal charge une liste vide indéfiniment** | 1) `.env.staging` en **LF pur** ; 2) `sed -i 's/\r$//'` du `.env.staging` au deploy ; 3) `.trim()` à la lecture dans `web3-core.js`. Preuve : le bundle servi contenait `\r` littéral après le projectId, et les URLs 403 finissaient par `%0D`. |
| **Cache Docker BuildKit** : `COPY . .` + `npm run build` restés **CACHED** entre deux builds rapprochés → l'image du bon SHA servait le **bundle d'avant** (le fix namespaces invisible) | `docker compose build **--no-cache** app` quand un bundle change de contenu — et **vérifier le contenu du bundle servi** (grep) avant de déclarer le déploiement réussi. |
| **Rebuild manuel sans les build args** (la régression) | **JAMAIS** de `docker compose build` à la main sur le VPS : tout rebuild passe par `deploy/rebuild_app_args.sh` (lit les `VITE_*`, **abort si projectId ≠ 32 chars**, build, recreate, **vérifie le projectId dans le bundle du container**, health). |
| Modal AppKit mobile : les `customWallets` jamais rendus (au clic mobile, AppKit route la vue **All Wallets** = registre Explorer filtré par `chains=eip155:99999` + `supports_wc`) → Core Wallet absent de la liste, déeplink impossible | **Bypasser la modal sur mobile** : `connectWalletMobile()` → `provider.connect({optionalNamespaces: WC_NAMESPACES})` → hook standard **`display_uri`** → redirection `https://core.app/wc?uri=<encoded>` (universal link iOS fiable). Desktop garde la modal + QR. |
| Session WC rejetée par Core : toast « Connection failed » → détail **« network not specified »** | La proposition de session **sans namespaces** n'a aucun réseau déclaré. Passer `namespaces` à la shape exacte AppKit : `{eip155: {chains:['eip155:99999'], methods:[…17], events:[accountsChanged, chainChanged], rpcMap:{99999:<rpc>}}}` (copie de `WcHelpersUtil.createNamespaces`). **Précision 2026-10-05 (preuve relais)** : `sign-client` 2.23.9 retire le `rpcMap` du fil (il n'existe même pas dans `ProposalTypes.Struct`), et `universal-provider` **ne remplit jamais `requiredNamespaces`** (`setNamespaces` ne touche que `this.optionalNamespaces`) — le provider officiel `EthereumProvider` wagmi produit **la même proposition** que la nôtre (`requiredNamespaces: {}` + chaînes en `optional`). L'erreur est donc levée **par Core Wallet lui-même** (chaîne 99999 inconnue de son interne, validation absente de tous les SDK dist que nous embarquons — « network not specified » n'apparaît nulle part dans @walletconnect/* ni @reown/*). La proposition est conforme ; le blocage est le support de la chaîne par Core. |
| **Le wrapper de deploy déploie `HEAD` du VPS**, pas le SHA demandé (résolu HEAD local ≠ HEAD VPS) | Toujours passer le **SHA explicite** au wrapper (`-GitSha`), jamais « HEAD » — sinon le deploy pinne le SHA d'origine du clone. |
| Diagnostic E2E trompeur : le clic « ne fait rien » | Deux faux positifs l'un derrière l'autre : **l'overlay de sélection de langue** (z-index 999999, plein écran, injecté par `i18n.js` si `user_locale` absent — il absorbait tous les clics dans le navigateur headless) puis **la modal n'appelle aucune requête** tant qu'il est là. En E2E : **simuler le choix de langue** (clic du 1ᵉʳ drapeau) AVANT le clic wallet, et supprimer `window.ethereum` pour le chemin mobile. |
| **« network not specified » CAUSE CONFIRMÉE par test utilisateur** (2026-10-05) : connexion IMPOSSIBLE sans geste préalable, puis **réussie après ajout manuel du réseau Pingala dans Core** (⚙️ → Networks → Add Network, RPC Pingala + chainId 99999 + symbole TST) | Core ne valide une session WC que si la chaîne est connue de son interne ; la proposition WC est conforme (preuve relais ci-dessus). Piste d'amélioration : enrôlement guidé côté dApp (page d'aide avec les valeurs à copier), ou `sessionProperties`… reste à mesurer. |
| **Après approbation dans Core, l'utilisateur reste sur core.app** (au lieu de revenir sur la dApp) | **`metadata.redirect.universal` manquant** dans `APPKIT_METADATA` → Core n'a aucune URL de retour dApp et expose son propre homepage. Fix : `redirect: { universal: window.location.origin + '/' }` dans la metadata WC (mécanisme v2 officiel). |
| **Journal utilisateur du 2026-10-06 (SUCCÈS, commit bd16659)** — 6 bugs révélés par le journal visuel 🐞 puis corrigés : (1) URI de pairing **zombie** réutilisée d'un flux précédent par le poll → reset de `_lastWcUri` au début de chaque flux ; (2) **fuite de listeners** (11×disconnect, 5×display_uri) → suppression du `once('disconnect')` par clic ; (3) **re-clic destructeur** : le 2ᵉ appel `resetProvider()` tuait la session fraîchement approuvée → réutilisation de session WC active avec `getWcSessionAddress()` ; (4) **comparaison de chaîne hex/décimal** (« 99999 ≠ 0x1869f ») → normalisation parseInt/Number ; (5) **personal_sign invisible** (arrive dans Core pendant que l'utilisateur regarde Safari) → `reopenWalletForSigning()` juste avant `signMessage` ; (6) `console.info` + toasts module invisibles au journal → capture ×4 niveaux + hook `_bpDebugLogHook` dans toast.js | **FLUX FINAL VALIDÉ** (iPhone iOS 18.7) : clic Connect → deeplink native → Core → approuver (~30 s) → session capturée par le poll → **Core se ré-ouvre** avec la demande de signature → signer → `✅ Authentifié` + Echo connecté. Aucun fallback automatique : le scheme natif marche toujours. |
| Cosmétique résiduel (non bloquant) : `[Referral]`/`[Marketplace] Erreur chargement stats: {}` au chargement | Ces appels API passent AVANT authentification (401 attendus) → silenciables ou conditionnés à l'état auth (à faire lors d'une passe de propreté). |

---

## 4. CE QU'IL NE FAUT JAMAIS FAIRE (synthèse anti-régression)

1. **Jamais** de clé privée dans un fichier tracké, un commit, un log ou une conversation.
2. **Jamais** de `INITIAL_OWNER_ADDRESS`/adresse codée en dur dans un contrat — owner = `msg.sender` ou un paramètre.
3. **Jamais** de `block.chainid == 31337` hardcodé : la chaîne cible est **99999** (le guard du triggerPoolEmitted reste test-only).
4. **Jamais** de `public $queue` dans un Job (§2 AGENTS.md).
5. **Jamais** de `docker cp` pour du code applicatif (le code vit dans l'image — §3 AGENTS.md) : modifier la source → `npm run build` → `docker compose build app` → `up --force-recreate`.
6. **Jamais** de transfert binaire via un pipe PowerShell (corruption CRLF/BOM).
7. **Jamais** renommer les clés techniques (`snt`, `sntToken`, clés API `*_snt_*`) — renommer les libellés d'affichage seulement.
8. **Jamais** oublier `Cache::forget('game_config')` après un changement d'adresse.
9. **Jamais** déployer un contrat nouveau **avant** les contrats consommés (l'ordre nonce décale les adresses).
10. **Jamais** afficher un lien d'explorateur (Pingala n'en a pas) — conditionner sur `blockExplorerUrls`.
11. **Jamais** de rebuild Docker **à la main sur le VPS** : tout rebuild passe par `deploy/rebuild_app_args.sh` (build args VITE_* + garde projectId 32 chars + vérification du bundle).
12. **Jamais** déclarer un déploiement frontend « réussi » sans **grep le bundle servi** (pas seulement le build local) — le cache BuildKit peut servir une couche périmée.
13. **Jamais** de fichier `.env*` en fins de ligne CRLF (le `\r` colle à la valeur — cf. §3.6) : tout `.env` écrit est converti en LF.
14. **Jamais** supposer qu'une option passée à `universal-provider.connect()` remplit `requiredNamespaces` : mesure dist — `setNamespaces()` ne touche que `this.optionalNamespaces`, `pair()` envoie `requiredNamespaces: this.namespaces` (= `{}` en première connexion). Une proposition avec requiredNamespaces vide est **conforme** (le provider officiel en fait autant).
15. **Jamais** considérer qu'un wallet (Core, Trust…) apparaîtra dans la modal AppKit mobile via le registre : il faut le **deeplink direct** (`display_uri`), la modal ne rend pas les `customWallets` sur mobile.
16. **Jamais** de fallback automatique universl-link après une navigation deeplink : iOS Safari gèle les timers en arrière-plan (ils repartent au retour) → le fallback tire APRÈS l'approbation et écrase la dApp avec la page 404 core.app. Le scheme natif `core://wc?uri=` marche seul.
17. **Jamais** ré-émettre une `session_propose` sur une URI stockée d'un flux précédent (`_lastWcUri` doit être reset au début de chaque flux — URI zombie = session jamais posée côté wallet).
18. **Toujours** ré-ouvrir le wallet (deeplink natif) juste avant une signature sur mobile WC : le `personal_sign` arrive dans l'app wallet pendant que l'utilisateur regarde Safari → invisible → boucle de re-clic.
19. **Toujours** normaliser les chainId avant comparaison (`eth_chainId` peut retourner hex OU décimal selon le provider WC).
20. **Jamais** `resetProvider()` aveugle dans connectWallet : si une session WC existe déjà, la réutiliser (`getWcSessionAddress()`), sinon on tue la session approuvée et l'utilisateur boucle sur « pas connecté ».
21. **Journal visuel mobile** (`resources/js/modules/debug-log.js`, bouton 🐞) : indispensable au debug à distance iPhone — capture toasts/console ×4 niveaux/erreurs JS/état WC + copie presse-papiers. À retirer en prod finale, conserver jusqu'ici.

---

## 5. CE QU'IL FAUT TOUJOURS FAIRE (répétition de déploiement)

1. **Rotation de clé** : nouvelle clé deployer par cycle de déploiement (`battlepool/.env` gitignored).
2. **Préflight** avant tout déploiement.
3. **Séquentiel strict** : une tx à la fois, `await tx.wait()` — sur une vraie chaîne, les nonces ne pardonnent pas.
4. **Budget gas** : vérifier balance avant/après chaque deploy ; STOP si < 1 TST de réserve.
5. **Verify on-chain** après déploiement (fee, K, rôles, supply, invariants).
6. **Re-run des tests** (`npx hardhat test` — réseau in-process, la L1 n'est pas touchée).
7. **Scan anti-PK** avant chaque commit : `git diff --cached` + grep `0x[0-9a-fA-F]{60,}` → seuls les tx hashes (66) sont légitimes ; une adresse fait 42 car.
8. **Provision/deprovision** des secrets à chaque opération container.
9. **Re-vérification du bundle servi** (pas seulement du build local) : c'est le bundle exécuté par le navigateur qui fait foi. **Vérifier à chaque fois les contenus critiques** : projectId (1 hit, pas de `\r`), `eip155:99999`, `optionalNamespaces` (≥2 hits), et le SHA d'image réellement en service (`docker ps` → Image = le SHA demandé).
10. **Metamask : Reset Account** après tout déploiement (nonce).
11. **Le SHA explicite** au wrapper de deploy (`-GitSha`), jamais `HEAD`.
12. **En E2E mobile** : simuler le choix de langue avant le clic wallet (l'overlay i18n bloque tous les clics) + `delete window.ethereum` pour le chemin mobile.

---

## 5bis. REBUILD D'IMAGE DEPUIS LE VPS (procédure contrôlée)

Un rebuild de l'image `app` **sans passer par le wrapper** (ex. après un hotfix local vérifié sur le VPS) utilisera UNIQUEMENT `deploy/rebuild_app_args.sh` :

```bash
ssh -i deploy/id_ed25519_deploy root@31.187.72.98
cd /opt/battlepool
# il lit VITE_* de .env.staging, ABORT si projectId != 32 chars,
# build --no-cache app, recreate, VÉRIFIE le projectId dans le bundle, health
sh /tmp/rebuild_app_args.sh   # (poussé via base64 depuis deploy/)
```
Garde-fous intégrés :projectId absent/≠32 chars → **exit 1 AVANT le build** ; bundle sans projectId après le build → **exit 1**.

---

## 6. Commandes de diagnostic utiles (recette éprouvée)

```powershell
# ChainId + bloc (RPC public)
$rpc = "https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc"
(Invoke-WebRequest -Uri $rpc -Method Post -Body '{"jsonrpc":"2.0","method":"eth_chainId","params":[],"id":1}' -ContentType "application/json" -UseBasicParsing).Content

# Logs bridge (chainId, ABIs, indexer)
docker logs rock-paper-scissors-bridge-1 --tail 20

# Endpoint config (via Laravel — le chemin réel du front)
Invoke-WebRequest http://127.0.0.1:8001/api/artefacts

# Endpoints publics
Invoke-WebRequest http://127.0.0.1:8001/api/stats/public
Invoke-WebRequest http://127.0.0.1:8001/api/health

# Secrets (workflow obligatoire)
node secrets-manager.mjs provision    # avant
node secrets-manager.mjs deprovision  # après
```

---

## 7. Travaux restés ouverts (non bloquants, à planifier)

| # | Ouverture | Pôle |
|---|---|---|
| 1 | `start.ps1` : retirer les références au service `blockchain` + attendre la santé de la chaîne externe | devops |
| 2 | `/rpc/` dans `proxy/nginx.conf` : route morte (Hardhat 8546 n'existe plus) — brancher le RPC Pingala OU retirer | devops/frontend |
| 3 | `/admin/contract-stats` du bridge sans garde `x-internal-secret` | bridge/api-security |
| 4 | `public/js/config.js` : `HARDHAT_RPC 127.0.0.1:8546` + mock wallet #0 (fichier hors bundle Vite) | frontend |
| 5 | `game.js:13` : fallback d'adresse Hardhat (`0x5FbDB…`) inutile (écrasé par `/api/artefacts`) | frontend |
| 6 | Anciennes adresses v1 = du code on-chain abandonné (fonds figés admis) — documenter plutôt que tenter un recovery | blockchain |
| 7 | **Refill des wallets de rôle** (0.2/0.5 TST) au fil des payouts — prévoir un job de monitoring | devops/payment |
| 8 | Reliques à purger du repo : `smart_contracts/exploit_*.cjs`, `blockchain_logs.txt`, `scratch/test_sign.cjs` | devops |
| 9 | Re-déploiement v3 éventuel : le point 1 de la liste est le plus rentable (start.ps1 cassé au prochain démarrage) | devops |

> **Rappel de mission** : ce runbook est la connaissance destinée aux **redéploiements futurs
> sur la même chaîne**. Toute nouvelle procédure d'exception découvrira de nouveaux pièges
> — ils doivent être ajoutés aux §3 (erreur→solution) et §4/§5 (interdits/obligations).