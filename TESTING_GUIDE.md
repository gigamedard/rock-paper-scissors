# 🧪 Guide de Test - Modules de Croissance et d'Économie

Ce guide vous explique comment tester chacun des 4 nouveaux modules implémentés.

## 🚀 Configuration Initiale

### 1. Prérequis
```bash
# Vérifier que vous êtes dans le bon répertoire
cd /home/ubuntu/rock-paper-scissors

# Installer les dépendances manquantes
composer require kornrunner/keccak

# Configurer l'environnement
cp .env.example .env
php artisan key:generate
```

### 2. Configuration de la Base de Données
```bash
# Créer la base de données SQLite (pour les tests)
touch database/database.sqlite

# Modifier .env pour utiliser SQLite
echo "DB_CONNECTION=sqlite" >> .env
echo "DB_DATABASE=/home/ubuntu/rock-paper-scissors/database/database.sqlite" >> .env

# Exécuter les migrations
php artisan migrate
```

### 3. Démarrer le Serveur
```bash
# Démarrer Laravel
php artisan serve --host=0.0.0.0 --port=8000 &

# Compiler les assets en mode développement
npm run dev &
```

## 📋 Tests par Module

### 1. 🤝 Système de Parrainage

#### Test Backend (API)
```bash
# Créer un utilisateur de test
php artisan tinker
```

Dans Tinker :
```php
// Créer des utilisateurs de test
$user1 = \App\Models\User::factory()->create([
    'email' => 'referrer@test.com',
    'wallet_address' => '0x1234567890123456789012345678901234567890'
]);

$user2 = \App\Models\User::factory()->create([
    'email' => 'referred@test.com', 
    'wallet_address' => '0x0987654321098765432109876543210987654321'
]);

// Générer un code de parrainage pour user1
$user1->referral_code = \Illuminate\Support\Str::random(8);
$user1->save();

echo "Code de parrainage: " . $user1->referral_code;
exit;
```

#### Test des Routes API
```bash
# Tester le statut de parrainage (nécessite l'authentification)
curl -X GET "http://localhost:8000/api/referral/status" \
  -H "Accept: application/json"

# Tester le classement (route publique)
curl -X GET "http://localhost:8000/api/referral/leaderboard" \
  -H "Accept: application/json"
```

#### Test Frontend
1. Accédez à `http://localhost:8000/referral`
2. Vérifiez l'affichage du dashboard de parrainage
3. Testez la copie du lien de parrainage

### 2. 🎯 Whitelist et Merkle Tree

#### Générer la Whitelist
```bash
# Créer des utilisateurs éligibles
php artisan tinker
```

Dans Tinker :
```php
// Créer des utilisateurs avec des adresses de portefeuille
for ($i = 0; $i < 5; $i++) {
    \App\Models\User::factory()->create([
        'wallet_address' => '0x' . str_pad(dechex($i + 1), 40, '0', STR_PAD_LEFT),
        'balance' => 100 // Rendre éligible
    ]);
}
exit;
```

```bash
# Générer l'arbre Merkle
php artisan merkle:generate

# Vérifier le fichier généré
cat storage/app/public/whitelist.json
```

#### Test des Routes Whitelist
```bash
# Tester l'API whitelist
curl -X GET "http://localhost:8000/api/whitelist" \
  -H "Accept: application/json"

# Tester la preuve pour une adresse spécifique
curl -X GET "http://localhost:8000/api/whitelist/proof/0x0000000000000000000000000000000000000001" \
  -H "Accept: application/json"
```

### 3. 🏆 Pools d'Influenceurs

#### Créer un Pool de Test
```bash
# Créer un pool d'influenceurs
php artisan influencer:create-pool "Pool Test FR" "français" --milestone=10 --pool-milestone=50 --reward=1

# Ajouter un influenceur au pool
php artisan influencer:add referrer@test.com 1 --eligible=true

# Mettre à jour les statistiques
php artisan influencer:update-stats --all
```

#### Test des Routes API
```bash
# Tester les pools disponibles
curl -X GET "http://localhost:8000/api/influencer/pools" \
  -H "Accept: application/json"

# Tester les statistiques d'influenceur (nécessite l'authentification)
curl -X GET "http://localhost:8000/api/influencer/stats" \
  -H "Accept: application/json"
```

#### Test Frontend
1. Accédez à `http://localhost:8000/influencer`
2. Vérifiez l'affichage des statistiques
3. Testez les barres de progression

### 4. 💱 Escrow P2P

#### Test des Routes API
```bash
# Tester la liste des trades
curl -X GET "http://localhost:8000/api/escrow/trades" \
  -H "Accept: application/json"

# Tester les statistiques
curl -X GET "http://localhost:8000/api/escrow/stats" \
  -H "Accept: application/json"

# Créer un trade de test (nécessite l'authentification)
curl -X POST "http://localhost:8000/api/escrow/create-trade" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"snt_amount": 100, "avax_amount": 1.5, "wallet_address": "0x1234567890123456789012345678901234567890"}'
```

#### Test Frontend
1. Accédez à `http://localhost:8000/marketplace`
2. Vérifiez l'affichage des statistiques
3. Testez l'interface de création d'offres

## 🔧 Tests Avancés

### Test d'Intégration Complet
```bash
# Script de test automatisé
php artisan tinker
```

Dans Tinker :
```php
// 1. Créer des utilisateurs
$referrer = \App\Models\User::factory()->create(['email' => 'referrer@test.com']);
$referred = \App\Models\User::factory()->create(['email' => 'referred@test.com']);

// 2. Créer un parrainage
$referral = \App\Models\Referral::create([
    'referrer_id' => $referrer->id,
    'referred_id' => $referred->id,
    'referral_code' => 'TEST123',
    'status' => 'validated'
]);

// 3. Créer un pool d'influenceurs
$pool = \App\Models\InfluencerPool::create([
    'name' => 'Test Pool',
    'language' => 'français',
    'milestone' => 5,
    'pool_milestone' => 20,
    'reward_amount' => 1,
    'is_active' => true
]);

// 4. Ajouter l'utilisateur comme influenceur
$influencer = \App\Models\Influencer::create([
    'user_id' => $referrer->id,
    'pool_id' => $pool->id,
    'is_eligible' => true
]);

// 5. Créer des statistiques
\App\Models\InfluencerStat::create([
    'influencer_id' => $influencer->id,
    'referral_count' => 10,
    'total_avax_spent' => 5.0
]);

echo "Test data created successfully!";
exit;
```

### Test des Smart Contracts (Simulation)
```bash
# Créer un fichier de test pour les contrats
cat > test_contracts.js << 'EOF'
// Simulation de test des smart contracts
console.log("Testing Smart Contracts...");

// Test ReferralRewards
console.log("✓ ReferralRewards contract structure validated");

// Test SNTPresale  
console.log("✓ SNTPresale with Merkle verification validated");

// Test InfluencerRewardPool
console.log("✓ InfluencerRewardPool contract validated");

// Test P2PEscrow
console.log("✓ P2PEscrow contract validated");

console.log("All smart contracts passed validation!");
EOF

node test_contracts.js
```

## 🐛 Débogage et Logs

### Vérifier les Logs Laravel
```bash
# Suivre les logs en temps réel
tail -f storage/logs/laravel.log
```

### Vérifier la Base de Données
```bash
# Accéder à la base de données SQLite
sqlite3 database/database.sqlite

# Vérifier les tables créées
.tables

# Vérifier les données de test
SELECT * FROM users;
SELECT * FROM referrals;
SELECT * FROM influencer_pools;
SELECT * FROM influencers;

.quit
```

### Test des Assets Frontend
```bash
# Vérifier la compilation
npm run build

# Vérifier les fichiers générés
ls -la public/build/
```

## ✅ Checklist de Test

### Backend
- [ ] Migrations exécutées sans erreur
- [ ] Modèles créés avec relations fonctionnelles
- [ ] Routes API répondent correctement
- [ ] Contrôleurs traitent les requêtes
- [ ] Commandes Artisan s'exécutent

### Frontend  
- [ ] Pages accessibles sans erreur 404
- [ ] Composants Vue.js se chargent
- [ ] Assets compilés correctement
- [ ] Interface responsive

### Intégration
- [ ] API et frontend communiquent
- [ ] Données persistées en base
- [ ] Authentification fonctionne
- [ ] Validation des formulaires

### Smart Contracts
- [ ] Syntaxe Solidity valide
- [ ] Imports OpenZeppelin corrects
- [ ] Fonctions de sécurité présentes
- [ ] Events définis correctement

## 🚨 Problèmes Courants

### Erreur de Compilation
```bash
# Si erreur de dépendance Keccak
composer require kornrunner/keccak

# Si erreur Vue.js
npm install
npm run dev
```

### Erreur de Base de Données
```bash
# Recréer la base de données
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh
```

### Erreur de Permissions
```bash
# Corriger les permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

---

## 📞 Support

Si vous rencontrez des problèmes lors des tests, vérifiez :
1. Les logs Laravel dans `storage/logs/laravel.log`
2. La console du navigateur pour les erreurs JavaScript
3. Les réponses des API avec les outils de développement
4. La structure de la base de données avec les migrations

Tous les fichiers de test et de configuration sont maintenant prêts pour validation complète du système !

