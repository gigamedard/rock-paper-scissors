# PoC — Core Wallet : « claim » + fee sponsorship sur mauvaise chaîne

> **Diagnostic à but de divulgation responsable.** Cette PoC réalise **une seule
> tentative** (bouton verrouillé après le premier clic) et ne contient **aucune
> boucle d'accumulation**. Elle sert à produire des preuves reproductibles pour un
> rapport de sécurité, pas à extraire des fonds.

---

## 1. Objet

Le comportement suspecté est le suivant :

1. La dApp déclenche une transaction de « claim ».
2. Le wallet (Core) **ne bascule pas** sur la chaîne attendue (Pingala 99999) et
   reste sur **Avalanche C-Chain** (43114).
3. Core propose de **sponsoriser** les frais.
4. Au lieu de neutraliser/débiter les frais, le montant serait **crédité** en AVAX
   à l'utilisateur — ce qui, répété, ferait croître sa balance sans dépense.

La PoC isole **le point de déclenchement de la transaction** et instrumente :

- la **chaîne active** du wallet **avant** et **après** la transaction ;
- les **soldes** (wallet, C-Chain via RPC, Pingala via RPC) **avant** et **après** ;
- la chaîne sur laquelle la transaction a **réellement été minée** (via les RPC,
  indépendamment du wallet) ;
- les **frais réels** (`gasUsed × effectiveGasPrice`) et les **deltas** de solde.

Le contrat (`ClaimPoc.sol`) est volontairement **trivial** (`function claim() {}`)
pour écarter toute logique métier : si un delta anormal apparaît, il vient du
wallet, pas du contrat.

---

## 2. Prérequis

- **Core Wallet** (extension navigateur de préférence ; la version mobile via
  WalletConnect n'expose pas `window.ethereum` et n'est pas couverte par cette
  PoC minimaliste).
- Un compte avec **un peu d'AVAX sur Avalanche C-Chain** (pour observer le
  comportement des frais ; quelques centimes suffisent).
- Le **RPC Pingala** :
  `https://31.187.72.98.sslip.io/ext/bc/2bU2988XvYbG4z85k39QxhDNomHWREnTKzYWLViU1RMCeSwEea/rpc`
- Un moyen de **déployer `ClaimPoc.sol` sur Pingala** (Remix ou Hardhat).
- Un serveur statique local (ex. `python3 -m http.server`).

> ⚠️ Réseau **de test** uniquement. N'utilise pas de fonds réels destinés à
> rester sur le compte.

---

## 3. Déploiement du contrat (Pingala 99999)

### Option A — Remix
1. Ouvrir https://remix.ethereum.org, créer `ClaimPoc.sol` avec le contenu fourni.
2. Compiler (Solidity ≥ 0.8.20).
3. Dans « Deploy & Run », choisir **Injected Provider** (Core), et vérifier que le
   réseau est **Pingala Chain (99999)**.
4. Déployer, puis **copier l'adresse** du contrat.

### Option B — Hardhat
```bash
npm i -D hardhat
npx hardhat init
# config réseau "pingala" -> chainId 99999, rpc = RPC Pingala
npx hardhat run scripts/deploy.js --network pingala
```

Ensuite, renseigner l'adresse dans `index.html` :
```js
const CLAIM_CONTRACT = '0x...'; // adresse déployée sur Pingala
```

---

## 4. Lancer la PoC

```bash
cd poc/core-fee-sponsorship
python3 -m http.server 5173
```

Ouvrir **http://localhost:5173** dans le navigateur où l'extension Core est active.

---

## 5. Procédure de reproduction (pas à pas)

1. **Connecter le wallet** (bouton 1). La page affiche la chaîne active et les
   soldes (wallet + C-Chain + Pingala).
2. **Vérifier l'état initial** : noter la chaîne active. Pour reproduire le cas
   suspect, le wallet doit être sur **Avalanche C-Chain** (43114).
3. **Cliquer « Claim (une seule fois) »** (bouton 3).
   - Core s'ouvre. **Observer** : bascule-t-il sur Pingala, ou reste-t-il sur
     C-Chain ? Propose-t-il de **sponsoriser** les frais ?
4. **Lire l'analyse** affichée :
   - chaîne **avant** / **après** ;
   - chaîne sur laquelle la tx a été **minée** (C-Chain ou Pingala) ;
   - `status` du reçu et **frais réels** ;
   - **Δ solde C-Chain** et **Δ solde wallet**.
5. **Exporter les preuves** (bouton 4) → `core-claim-sponsorship-evidence.json`.

> La PoC **ne force pas** le réseau (`wallet_switchEthereumChain` n'est jamais
> appelé). C'est volontaire : c'est précisément le comportement du wallet que
> l'on veut observer. (Le fait que la tx « claim » parte sur C-Chain est donc
> attendu si le wallet y est ; le point à prouver est le **crédit de frais**.)

---

## 6. Ce que l'on observe / comment l'interpréter

- **Normal (attendu)** : la tx est minée sur C-Chain, le wallet paie les frais
  (`gasUsed × effectiveGasPrice`) → **Δ C-Chain négatif** ≈ `-frais`.
- **Anomalie suspectée** : la tx est minée (status 1) sur C-Chain et
  **Δ C-Chain positif** (≈ +0,0001 AVAX par tentative) alors que des frais ont
  été présentés comme sponsorisés → le crédit est inversé.
- La colonne « minée sur » et les frais calculés via les **RPC** rendent la preuve
  indépendante du wallet.

---

## 7. Preuves à joindre au rapport

1. Le **JSON exporté** (chaîne avant/après, tx hash, statut, frais, deltas).
2. Des **captures d'écran** :
   - la page PoC avant/après ;
   - la fenêtre Core (réseau affiché, éventuelle proposition de sponsorship) ;
3. L'**historique des crédits entrants** de l'adresse sur C-Chain (explorateur /
   API), montrant des crédits répétés (~0,0001 AVAX) et l'adresse du sponsor.
   ⚠️ Une transaction **sortante** (ex. un transfert de 0,0065 AVAX) prouve
   seulement que le wallet détenait de l'AVAX : elle **ne prouve pas** le crédit
   de sponsorship. Il faut les **entrées**.

---

## 8. Divulgation responsable

- **Ne pas répéter** l'opération pour accumuler des fonds.
- **Ne pas déplacer** les fonds éventuellement crédités (cela pourrait être
  interprété comme une exploitation/abus).
- Contacter la sécurité d'**Ava Labs** (programme de bug bounty / canal sécurité
  officiel) avec la PoC, les logs et les preuves ci-dessus.
- Respecter un embargo de divulgation (ne pas publier avant correctif).

---

## 9. Limites assumées de cette PoC

- Cible le **provider injecté** (Core extension / MetaMask). Le comportement peut
  différer en WalletConnect mobile.
- Le « fee sponsorship » est une fonctionnalité du **wallet** : la dApp ne peut pas
  le déclencher artificiellement ; elle peut seulement **observer** ce que Core
  propose et ce que les soldes deviennent.
- La PoC est **single-shot** par conception : aucune boucle, aucun script
  d'accumulation. La répétabilité doit être démontrée par des preuves issues de
  l'historique on-chain, pas par un outil d'exploitation.
