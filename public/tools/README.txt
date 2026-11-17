Battlepool — outils de dérivation de wallets (LOCAL / OFFLINE)

⚠️  SÉCURITÉ
- Une seed phrase donne un accès TOTAL aux wallets.
- Ne jamais la partager, l'envoyer par chat, la committer ou la stocker en clair.
- Pour des fonds réels : appareil offline ou hardware wallet ; seed dédiée à la trésorerie.
- Le site ne reçoit jamais la seed : la page HTML s'exécute uniquement dans ton navigateur.

OPTION 1 — Interface HTML (recommandé, aucun Node requis)
1. Garde wallet-derivation.html ET ethers.umd.min.js dans le même dossier.
2. Ouvre wallet-derivation.html dans ton navigateur (double-clic).
3. Colle la seed, choisis l'index, clique Dériver.
4. Tu obtiens :
   - EVM (BNB / Polygon / Pingala / Avalanche C-Chain / MetaMask) : adresse, publique, privée.

OPTION 2 — Script Node.js
Prérequis : Node.js 18+.
Générer une seed (12 ou 24 mots) :
    node derive-wallets.cjs --generate --lang fr --words 12
    node derive-wallets.cjs --generate --lang en --words 24
- Version autonome (aucun npm install) :
    node derive-wallets.cjs --index 0 --lang fr   # ou --lang en
  ou
    SEED_PHRASE="mot1 mot2 ..." node derive-wallets.cjs --json --lang fr
- Version source (nécessite ethers) :
    npm install ethers
    SEED_PHRASE="..." node derive-wallets.mjs --index 0 --lang fr

Chemins :
- EVM : m/44'/60'/0'/0/{index}

Ne donne à l'on-ramp que les ADRESSES PUBLIQUES EVM (0x...).
