# Stagnant Pool Refund Implementation

## Date: 2025-11-29

## Objectif
Implémenter un mécanisme de remboursement automatique pour les pools stagnantes - si l'effectif d'une pool n'évolue pas durant plusieurs blocs (par défaut 100 blocs), le système doit :
1. Retourner l'argent des utilisateurs
2. Les sortir de la pool
3. Remettre la pool à zéro
4. Émettre un événement pour prévenir via le frontend et les différents joueurs

## Modifications du Smart Contract (Battlepool.sol)

### Nouvelles Variables d'État
```solidity
uint256 public stagnantBlockLimit = 100; // Default 100 blocks
```

### Modification de la Structure Pool
```solidity
struct Pool {
    uint256 poolId;
    uint256 baseBet;
    uint256 maxSize;
    address[] users;
    string poolSalt;
    mapping(address => bool) isUserInPool;
    uint256 lastActivityBlock; // ← NOUVEAU
}
```

### Nouveaux Événements
```solidity
event PoolStagnantRefund(uint256 indexed poolId, uint256 refundedCount, uint256 timestamp);
event StagnantBlockLimitUpdated(uint256 newLimit);
```

### Nouvelles Fonctions

#### 1. `setStagnantBlockLimit(uint256 _limit)` - Owner uniquement
Permet au propriétaire de modifier le nombre de blocs avant qu'une pool soit considérée comme stagnante.

```solidity
function setStagnantBlockLimit(uint256 _limit) external onlyOwner {
    stagnantBlockLimit = _limit;
    emit StagnantBlockLimitUpdated(_limit);
}
```

#### 2. `checkAndRefundStagnantPool(uint256 baseBet)` - Public
Vérifie si une pool est stagnante et effectue le remboursement si nécessaire.

**Conditions de vérification:**
- La pool existe (`pool.poolId != 0`)
- La pool contient des utilisateurs (`pool.users.length > 0`)
- Le nombre de blocs depuis la dernière activité dépasse la limite (`block.number > pool.lastActivityBlock + stagnantBlockLimit`)

**Actions effectuées:**
1. Itère sur tous les utilisateurs de la pool
2. Pour chaque utilisateur :
   - Nettoie le statut dans la pool (`isUserInPool = false`)
   - Libère l'utilisateur (`isUserInAnyPool = false`)
   - Supprime le CID des premoves
   - Rembourse le solde complet (`userBalances[user]`)
3. Réinitialise la pool (vide la liste des utilisateurs)
4. Émet l'événement `PoolStagnantRefund`

### Mises à Jour des Fonctions Existantes

#### `createPool`
```solidity
newPool.lastActivityBlock = block.number; // Initialize activity
```

#### `addUsersToPool` et `addSingleUserToPool`
```solidity
pool.lastActivityBlock = block.number; // Update activity
```

## Modifications Backend (Laravel)

### Nouveau Controller Method
**Fichier:** `app/Http/Controllers/PoolAutoMatchController.php`

```php
public function handleStagnantRefund(Request $request): JsonResponse
{
    $validated = $request->validate([
        'pool_id' => 'required|string',
        'refunded_count' => 'required|integer',
        'timestamp' => 'required|integer',
    ]);

    Log::info("Stagnant Pool Refund Processed", $validated);
    
    return response()->json(['message' => 'Stagnant pool refund logged']);
}
```

### Nouvelle Route API
**Fichier:** `routes/api.php`

```php
Route::prefix('internal')->middleware('auth.internal')->group(function () {
    // ... autres routes
    Route::post('/handle-stagnant-refund', [PoolAutoMatchController::class, 'handleStagnantRefund']);
});
```

## Modifications Frontend (Node.js Listener)

### Ajout du Listener d'Événement
**Fichier:** `smart_contracts/app.js`

```javascript
// 3. PoolStagnantRefund
const stagnantEvents = await gameContract.queryFilter("PoolStagnantRefund", lastBlock + 1, currentBlock);
for (const event of stagnantEvents) {
    const { args } = event;
    console.log(`🔔 [JEU] PoolStagnantRefund: poolId=${args[0]}, refunded=${args[1]}, timestamp=${args[2]}`);
    postToLaravel('/internal/handle-stagnant-refund', {
        pool_id: args[0].toString(),
        refunded_count: args[1].toString(),
        timestamp: args[2].toString()
    });
}
```

## Utilisation

### Pour l'Owner du Contrat

#### Changer la limite de stagnation (défaut: 100 blocs)
```javascript
const tx = await battlepool.setStagnantBlockLimit(200); // 200 blocs
await tx.wait();
```

### Pour N'importe Quel Utilisateur

#### Vérifier et déclencher un remboursement pour une pool stagnante
```javascript
const baseBet = ethers.parseEther("0.01"); // Example: 0.01 ETH base bet
const tx = await battlepool.checkAndRefundStagnantPool(baseBet);
await tx.wait();
```

**Note:** La transaction échouera si :
- La pool n'existe pas
- La pool est vide
- La pool n'est pas encore stagnante (pas assez de blocs écoulés)

## Flux de Données

```
Smart Contract → PoolStagnantRefund Event
       ↓
Node.js Listener (app.js) détecte l'événement
       ↓
Appel POST à Laravel: /api/internal/handle-stagnant-refund
       ↓
Backend log l'événement
       ↓
(Optionnel) Notification aux utilisateurs via WebSocket/Email
```

## Sécurité

1. **Remboursement Sécurisé:** Utilise `.call{value: amount}("")` au lieu de `.transfer()`
2. **Protection contre la Réentrance:** État modifié avant l'envoi d'ETH
3. **Vérifications Strictes:** Plusieurs `require()` avant le remboursement
4. **Authentification API:** Routes internes protégées par `auth.internal` middleware

## Tests Recommandés

1. **Test de Mise à Jour de la Limite:**
   - Appeler `setStagnantBlockLimit` en tant qu'owner
   - Vérifier que seul l'owner peut appeler cette fonction

2. **Test de Pool Stagnante:**
   - Créer une pool avec 2 utilisateurs (sur maxSize=5)
   - Attendre plus de 100 blocs (ou la limite configurée)
   - Appeler `checkAndRefundStagnantPool`
   - Vérifier que les utilisateurs sont remboursés
   - Vérifier que la pool est réinitialisée

3. **Test de Pool Active:**
   - Créer une pool
   - Ajouter un utilisateur régulièrement (avant la limite de blocs)
   - Vérifier que `checkAndRefundStagnantPool` échoue

4. **Test Backend:**
   - Simuler un événement `PoolStagnantRefund`
   - Vérifier que le listener le capte
   - Vérifier que l'API Laravel est appelée
   - Vérifier les logs

## Déploiement

⚠️ **IMPORTANT:** Pour que ces changements prennent effet :

1. **Redéployer le contrat Battlepool:**
   ```bash
   cd battlepool
   npx hardhat run scripts/deploy.js --network <votre-réseau>
   ```

2. **Mettre à jour l'adresse du contrat dans `config.js`**

3. **Mettre à jour l'ABI dans `config.js`** (copier depuis `artifacts/contracts/Battlepool.sol/Battlepool.json`)

4. **Redémarrer le serveur Node.js:**
   ```bash
   node smart_contracts/app.js
   ```

5. **Tester avec `checkAndRefundStagnantPool`**

## Fichiers Modifiés

### Smart Contract
- `battlepool/contracts/Battlepool.sol`

### Backend
- `app/Http/Controllers/PoolAutoMatchController.php`
- `routes/api.php`

### Frontend/Listener
- `smart_contracts/app.js`

### Documentation
- `STAGNANT_POOL_REFUND_IMPLEMENTATION.md` (ce fichier)
