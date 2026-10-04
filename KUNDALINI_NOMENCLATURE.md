# Kundalini Network — Nomenclature Officielle de l'Écosystème

> **Référence canonique** (single source of truth) de l'identité globale de l'écosystème.
> Toute documentation, deck investisseurs, configuration technique (Genesis Subnet-EVM, env du Bridge Node.js, `symbol()` des ERC-20) doit s'y conformer.
> Dernière mise à jour : Septembre 2026.

---

## 1. Fiche Récapitulative

| Composant | Nom Officiel | Ticker / Symbole | Rôle & Usage Technique |
| --- | --- | --- | --- |
| **Jeu** | **Spirit Fight** | — | Interface de combat (Zero-Gas UX, matchmaking temps réel). |
| **Réseau Global** | **Kundalini Network** | — | Subnet L1 Avalanche Dual-Chain. |
| **AppChain (Jeu)** | **Pingala Chain** | — | Chaîne d'exécution rapide pour les matchs et les paris. |
| **AssetChain (Finance)** | **Ida Chain** | — | Chaîne de règlement, Marketplace NFT, Staking & Real Yield. |
| **Bridge (AWM)** | **Sushumna** | — | Infrastructure Teleporter reliant Pingala et Ida. |
| **Token AppChain** | **Prana** | **`$PRANA`** | Jeton d'action, d'énergie et de mise de jeu. |
| **Token AssetChain** | **Chakra** | **`$CHAKRA`** | Gas d'Ida Chain, devise Marketplace, récompense des validateurs. |

---

## 2. Règles de nommage

1. **`$PRANA`** et **`$CHAKRA`** sont conservés tels quels : lisibilité, absence d'ambiguïté sur les exchanges, verrouillage de l'identité globale.
2. Le **jeu** se nomme **Spirit Fight** (déjà présent dans `public/portal.html` : `menu.bp.title`).
3. Le **réseau** est **Kundalini Network** (Subnet L1 Avalanche, architecture Dual-Chain).
4. **Pingala Chain** = AppChain (exécution : matchs, paris) — réseau de jeu.
5. **Ida Chain** = AssetChain (règlement, Marketplace NFT, Staking, Real Yield) — réseau finance.
6. **Sushumna** = le bridge/AWM (Avalanche Warp Messaging) reliant Pingala ↔ Ida via Teleporter.

---

## 3. Mapping Code → Nomenclature

> État actuel du dépôt : un seul token existe réellement (`SNTToken` = `"Giga Special Token"/"SNT"`), aucune trace de PRANA/CHAKRA/Kundalini/Pingala/Ida hors `Spirit Fight`.

| Concept | Identifiant actuel dans le code | Cible Nomenclature | Fichiers concernés |
| --- | --- | --- | --- |
| Token jeu (AppChain) | `SNTToken` (`"Giga Special Token"`, `"SNT"`) | **`PranaToken` (`Prana`, `$PRANA`)** | `battlepool/contracts/SNTToken.sol`, ABI `smart_contracts/config.js`, `smart_contracts/app.js` |
| Token finance (AssetChain) | *(inexistant)* | **`ChakraToken` (`Chakra`, `$CHAKRA`)** | À créer (nouveau contrat + déploiement) |
| Réseau jeu | `hardhat` / `fuji` / `mainnet` ; `FUJI_RPC_URL` | **Pingala Chain** | `battlepool/hardhat.config.js`, `.env*`, `smart_contracts/config.js` |
| Réseau finance | *(inexistant)* | **Ida Chain** | À configurer (Subnet-EVM + Genesis) |
| Bridge | bridge Node `smart_contracts/app.js` | **Sushumna** | À documenter/renommer (container `bridge`) |
| Jeu | `BATTLEPOOL` / `Rock Paper Scissors` | **Spirit Fight** | `public/index.html`, `public/manifest.json`, `portal.html`, `.env APP_NAME` |
| Réseau global | — | **Kundalini Network** | Docs, branding |

**Chaîne de dépendances à ne pas casser lors du renommage :**
- `SNTToken.sol` → `ERC20("Giga Special Token","SNT")` change de `symbol()` ⇒ **redéploiement obligatoire** + régénération de l'ABI (`smart_contracts/config.js`) + mise à jour du front (`marketplace.js`) + `.env SNT_TOKEN_ADDRESS`.
- `FUJI_RPC_URL` est référencé par ~30 scripts Node + `config.js` : renommage à faire de façon atomique (ou conserver la variable en alias).
- Le nom de jeu `BATTLEPOOL` apparaît dans PWA, `localStorage` (`BATTLEPOOL_SESSION_CLEAR`), titres ⇒ migration d'identité à planifier (le pôle Branding).

---

## 4. Configuration cible (à décliner)

### 4.1 Tokens ERC-20
```solidity
// AppChain (Pingala)
ERC20("Prana", "PRANA")
// AssetChain (Ida)
ERC20("Chakra", "CHAKRA")
```

### 4.2 Variables d'environnement (cible)
```
# Réseaux
PINGALA_RPC_URL=...        # ex. AppChain jeu (ancien FUJI_RPC_URL local 8546)
IDA_RPC_URL=...            # AssetChain finance
KUNDALINI_SUBNET_ID=...

# Tokens
PRANA_TOKEN_ADDRESS=...    # remplace SNT_TOKEN_ADDRESS
CHAKRA_TOKEN_ADDRESS=...
```

### 4.3 Genesis / Subnet-EVM
- `Pingala Chain` : Subnet-EVM AppChain (exécution matchs/paris) — **RÉALISÉE** (L1 souveraine, chainId 99999).
- `Ida Chain` : Subnet-EVM AssetChain (règlement/Marketplace/Staking) — **PHASE 2** (après beta, à la traction).
- Bridge `Sushumna` : Teleporter / AWM reliant Pingala ↔ Ida — **PHASE 2**.

> **ARCHITECTURE RETENUE (décision utilisateur) : mono-chaîne en beta.**
> Ton L1 souveraine **existante** (chainId 99999) EST **Pingala Chain**. Le WAVAX
> (wrapper AVAX, pattern WETH9) sert d'asset 1:1 ; CHAKRA reste le token de la
> **phase 2** (Ida Chain + staking + fees de bridge). Le renommage PRANA est
> **réalisé** (contrat `PranaToken`). Procédure de redéploiement :
> [`PINGALA_DEPLOY_RUNBOOK.md`](PINGALA_DEPLOY_RUNBOOK.md).

---

## 5. Statut d'implémentation

| Élément | État |
| --- | --- |
| Nom du jeu « Spirit Fight » | Déjà présent (`portal.html`) |
| Nomenclature officielle documentée | ✅ ce fichier |
| **Token `PRANA`** (renommage SNT) | ✅ **RÉALISÉ** — `PranaToken.sol` déployé sur Pingala |
| Token `CHAKRA` | ⏸️ Phase 2 (Ida Chain) |
| **Pingala Chain** | ✅ **RÉALISÉE** — L1 souveraine (chainId 99999), 4 contrats déployés |
| Ida Chain (Subnet-EVM) | ⏸️ Phase 2 |
| Bridge Sushumna | ⏸️ Phase 2 (le bridge Node applicatif tourne déjà comme app↔chaîne) |
| WAVAX (wrapper AVAX) | ✅ RÉALISÉ (contrat + tests, wrap passif 1:1) |
| Branding Kundalini / Spirit Fight | ⏳ À propager (PWA, titres, domaine) |

> ⚠️ **Décisions tranchées** : mono-chaîne en beta (Pingala) ; Hardhat local retiré du
> stack ; renommage PRANA effectué au redéploiement ; reset des données ; gas payé
> par le `payoutOperator` (zero-gas UX) ; WAVAX créé. Détails et procédure répétée :
> [`PINGALA_DEPLOY_RUNBOOK.md`](PINGALA_DEPLOY_RUNBOOK.md).
