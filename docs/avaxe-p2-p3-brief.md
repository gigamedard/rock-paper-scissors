# Brief P2/P3 — intégrer AVAXe dans le jeu (sans clé de déploiement)

> Branche : `feat/avaxe-bridge`. Contrats **déjà déployés** sur Pingala (99999).
> P1 validé (mint → approve → deposit → claim par signature OK).
> Ce brief couvre **P2 (backend)** et **P3 (frontend)**. **Ne nécessite pas** la clé
> de déploiement. P0 (bridge AvaVault + relayer) est une mission séparée.

## 0. Adresses déployées (source de vérité : `battlepool/avaxe_deployments.json`)

| Élément | Adresse |
|---|---|
| AVAXe (ERC-20, 18 déc.) | `0x9d8AA35058493Aa826be143B568BCfA8E2F537F7` |
| BattlepoolV2 | `0xb628C8dE0ed6c1192dEB4C4C336fe3595370385E` |
| owner/dev | `0xD62909EAD1cbE35d5A7BD145f00530Fd574763Ee` |
| signer | `0xBe24F203E4e35A79E242d77c3710cE608C16a3De` |
| payoutOperator | `0x9A60327ce58A94a411987119047A75A8F1076663` |

Paramètres V2 : `securityCoefficient=1000`, `feeBasisPoints=250` (2.5%), `maxBaseBet=100`,
`maxQ=2.0`. Gas = **TST natif** (inchangé).

## 1. Rappel contrat V2 (ce qui change vs Battlepool)

- Dépôt : `deposit(uint256 amount)` **non-payable** → `transferFrom` (l'utilisateur doit
  `approve` AVAXe à V2 au préalable).
- Enrôlement : `submitPremoveCID(uint256 baseBet, string cid, uint256 amount)` (au lieu de payable).
- Payouts : `payOut(address user, uint256 amount)` / `batchPayOut` / `claimAndExit(...)` →
  `IERC20(token).transfer(...)`.
- `updateUserBalance(address,uint256)` borné par `token.balanceOf(V2)`.
- **Événements identiques** à Battlepool (`DepositReceived`, `PoolEmitted`, `PayoutProcessed`,
  `PlayerClaimed`, `UserBalanceUpdated`) → l'indexer existant peut les parser avec la même ABI.
- `claimAndExit` signe toujours `keccak256(abi.encodePacked(user, amount, nonce, address(V2), chainid, deadline))`.

## 2. P2 — Backend

### 2.1 Configuration
- Ajouter la config du **contrat de jeu actif** et de la **devise** :
  - `GAME_CONTRACT_V2=0xb628C8dE0ed6c1192dEB4C4C336fe3595370385E`
  - `GAME_TOKEN_AVAXE=0x9d8AA35058493Aa826be143B568BCfA8E2F537F7`
  - `GAME_CURRENCY=avaxe` (ou `tst`)
- Recommandation P2 (simple, sans migration DB) : **bascule globale** via `game_settings`
  (`active_game_contract`, `active_game_currency`). La coexistence par pool est un lot ultérieur.

### 2.2 Indexer (`smart_contracts/indexer/`)
- Écouter les événements sur **V2** (même noms qu'actuellement) et alimenter la DB comme
  pour Battlepool : `DepositReceived` → `users.balance` ; `PayoutProcessed`/`PlayerClaimed`
  → effacer `payout_signature` ; `UserBalanceUpdated` → synchro.
- L'ABI de parsing peut rester `contracts.game.abi` **si** les signatures d'événements sont
  identiques (c'est le cas). Sinon ajouter `contracts.gameV2.abi`.
- ⚠️ Ne PAS indexer les `Transfer` AVAXe comme des dépôts : les dépôts sont déjà couverts par
  `DepositReceived` (émis par V2). Le `Transfer` ne sert qu'au suivi du token lui-même.

### 2.3 Payout (bridge `smart_contracts/app.js`)
- `/sendPayment` : appeler `V2.payOut(wallet, amount)` (ERC-20) **au lieu de** `contract.payOut`
  (natif). Faire d'abord `V2.updateUserBalance(wallet, amount)` (sync), comme aujourd'hui.
- La clé `PAYOUT_OPERATOR_PK` (0x9A60…) est déjà `payoutOperator` de V2 → **aucune nouvelle clé**.
- `/sendBatchPayment` : `V2.batchPayOut(...)`.
- `/setUserLimits`, `/setUserNextSessionTime`, `/pool/validate`, `/pool/invalidate`,
  `/sendPoolCID`, `/sendSessionCID` : pointer sur V2 (mêmes signatures).

### 2.4 Signature de claim (`/generate-signature`)
- Signer avec `SIGNER_WALLET_PK` (0xBe24…) le hash incluant **l'adresse V2** :
  `keccak256(abi.encodePacked(user, amount, nonce, V2_ADDRESS, chainId=99999, deadline))`.
- Le frontend appellera `V2.claimAndExit(amount, deadline, signature)`.

### 2.5 Faucet
- **TST (gas)** : inchangé (faucet existant).
- **AVAXe (test)** : endpoint `POST /api/user/avaxe-faucet` (montant configurable, ex. 5 AVAXe,
  limité aux utilisateurs authentifiés, une fois ou rate-limit).
  - **Contrainte** : `AVAXe.mint` est `onlyOwner` (owner = 0xD629, clé froide). Deux options :
    - (recommandé) ajouter un rôle **`minter`** à AVAXe (owner règle un hot key bridge/faucet),
      ou redéployer AVAXe avec `setMinter` ;
    - (dépannage test) le bridge/faucet minte avec la clé owner — à éviter en prod.
- Le **bridge AVAX.e réel (Fuji)** reste P0 (relayer). Le faucet test est indépendant.

### 2.6 DB
- Aucun changement de schéma si bascule globale (les `balance`/`battle_balance` deviennent des
  montants AVAXe).
- Si coexistence plus tard : ajouter `game_currency` + `contract_address` sur `pools`/`fights`.

## 3. P3 — Frontend

### 3.1 Adresses
- Lire V2 + AVAXe depuis `/api/artefacts` (ajouter les champs côté bridge `get-game-config`),
  pas de hardcode.

### 3.2 Dépôt / enrôlement
- `await token.approve(V2, parseUnits(amount))` puis `V2.deposit(amount)` (dépôt simple)
  ou `V2.submitPremoveCID(baseBet, cid, amount)` pour lancer une session.
- Le calcul du stake affiché (`securityCoefficient`, frais) est inchangé — en AVAXe.

### 3.3 Affichage
- Header/dashboard : solde **AVAXe** (jeu) + **TST** (gas). Distinguer clairement les deux.
- `wallet_watchAsset` pour ajouter AVAXe (symbol `AVAXe`, decimals 18).

### 3.4 Claim
- `V2.claimAndExit(amount, deadline, signature)` (le réseau Pingala est déjà forcé par le flux
  de transaction existant). Idéalement réutiliser le fix « forcer Pingala avant la tx ».

### 3.5 i18n
- Ajouter les libellés AVAXe/TST (×6 langues) et l'action « Ajouter AVAXe au wallet ».

## 4. Tests

1. **On-chain headless** (déjà fait pour P1) : mint/approve/deposit/claim par signature.
2. **Backend** : après branchement indexer/payout sur V2, rejouer le flux avec un wallet de test
   (dépôt AVAXe → pool → fight → payout/claim) et vérifier `users.balance` + tx.
3. **Headless browser** (P3) : Playwright sur le bundle servi — connexion, approve+deposit,
   affichage solde, claim (comme les tests précédents).

## 5. Critères d'acceptation

- Un wallet avec de l'AVAXe peut lancer une session V2 (dépôt via `approve`+`deposit`).
- Les combats/pools fonctionnent avec les soldes en AVAXe (indexer OK).
- Le payout (bot) et le claim (humain, signature) transfèrent de l'AVAXe.
- Le faucet TST (gas) et le faucet AVAXe (test) fonctionnent.
- Aucune régression sur la voie TST natif tant que la coexistence n'est pas finalisée.

## 6. Décisions ouvertes

1. Bascule **globale** (recommandée pour P2) vs coexistence par pool.
2. Faucet AVAXe : ajouter un **rôle minter** (recommandé) vs owner key dans le bridge.
3. P0 (bridge Fuji réel) : calendrier séparé (relayer + AvaVault).
