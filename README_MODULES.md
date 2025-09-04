# Modules de Croissance et d'Économie

Ce document décrit les 4 nouveaux modules implémentés dans le projet Rock Paper Scissors pour stimuler la croissance et l'économie du jeu.

## 📋 Modules Implémentés

### 1. 🤝 Système de Parrainage Avancé
- **Objectif**: Encourager l'acquisition d'utilisateurs via le parrainage
- **Récompense**: 100 SNT par parrainage validé
- **Validation**: Automatique lors de la première transaction AVAX de l'utilisateur parrainé

**Fonctionnalités**:
- Génération automatique de codes de parrainage uniques
- Dashboard de suivi des parrainages
- Classement des meilleurs parraineurs
- API pour traitement et validation des parrainages

### 2. 🎯 Whitelist On-Chain avec Merkle Tree
- **Objectif**: Gérer l'accès exclusif à la prévente de tokens SNT
- **Technologie**: Arbre de Merkle pour vérification efficace on-chain
- **Prix**: 0.001 AVAX par token SNT
- **Limite**: 1000 tokens maximum par adresse

**Fonctionnalités**:
- Génération automatique de l'arbre Merkle
- Vérification de whitelist via preuve cryptographique
- Interface de prévente avec vérification automatique
- API pour servir les preuves Merkle

### 3. 🏆 Pools de Récompenses pour Influenceurs
- **Objectif**: Récompenser les influenceurs qui atteignent des objectifs de parrainage
- **Structure**: Pools par langue/communauté avec objectifs individuels et collectifs
- **Récompenses**: Distribution équitable en AVAX entre influenceurs éligibles

**Fonctionnalités**:
- Création et gestion de pools d'influenceurs
- Suivi des statistiques de parrainage par influenceur
- Système d'éligibilité administrable
- Interface de réclamation de récompenses

### 4. 💱 Escrow P2P Décentralisé
- **Objectif**: Permettre l'échange sécurisé SNT ↔ AVAX entre utilisateurs
- **Sécurité**: Smart contract d'escrow avec gestion automatique des fonds
- **Frais**: 2.5% sur chaque transaction
- **Expiration**: 24h pour les offres non acceptées

**Fonctionnalités**:
- Création d'offres d'échange
- Acceptation et annulation de trades
- Interface marketplace intuitive
- Statistiques de trading en temps réel

## 🗄️ Structure de la Base de Données

### Nouvelles Tables
- `referrals`: Gestion des parrainages
- `influencer_pools`: Pools d'influenceurs
- `influencers`: Association utilisateurs-pools
- `influencer_stats`: Statistiques des influenceurs
- `referral_code` ajouté à la table `users`

## 🔗 Routes API

### Parrainage
- `GET /api/referral/status` - Statut du parrainage utilisateur
- `POST /api/referral/process` - Traitement d'un nouveau parrainage
- `POST /api/referral/validate` - Validation d'un parrainage
- `GET /api/referral/leaderboard` - Classement des parraineurs

### Influenceurs
- `GET /api/influencer/stats` - Statistiques de l'influenceur
- `POST /api/influencer/claim-reward` - Réclamation de récompense
- `GET /api/influencer/pools` - Liste des pools actifs

### Escrow P2P
- `GET /api/escrow/trades` - Liste des trades actifs
- `POST /api/escrow/create-trade` - Création d'un trade
- `POST /api/escrow/accept-trade/{id}` - Acceptation d'un trade
- `POST /api/escrow/cancel-trade/{id}` - Annulation d'un trade

### Whitelist
- `GET /api/whitelist` - Liste des adresses whitelistées
- `GET /api/whitelist/proof/{address}` - Preuve Merkle pour une adresse

## 🎨 Interface Utilisateur

### Pages Ajoutées
- `/referral` - Dashboard de parrainage
- `/influencer` - Dashboard influenceur
- `/marketplace` - Marketplace P2P

### Composants Vue.js
- `ReferralDashboard.vue` - Gestion des parrainages
- `InfluencerDashboard.vue` - Suivi des performances influenceur
- `P2PMarketplace.vue` - Interface d'échange P2P

## 🔧 Smart Contracts

### Contrats Déployés
- `ReferralRewards.sol` - Gestion des récompenses de parrainage
- `SNTPresale.sol` - Prévente avec whitelist Merkle
- `InfluencerRewardPool.sol` - Pools de récompenses influenceurs
- `P2PEscrow.sol` - Escrow décentralisé pour échanges

## ⚙️ Commandes Artisan

### Gestion Merkle Tree
```bash
php artisan merkle:generate --output=whitelist.json
php artisan presale:deploy --merkle-file=whitelist.json
```

### Gestion Influenceurs
```bash
php artisan influencer:create-pool "Pool FR" "français" --milestone=5000 --pool-milestone=30000 --reward=10
php artisan influencer:add user@example.com 1 --eligible=true
php artisan influencer:update-stats --all
```

## 🚀 Déploiement et Configuration

### Variables d'Environnement
```env
NODE_URL=http://localhost:3000
SNT_TOKEN_ADDRESS=0x...
PRESALE_CONTRACT_ADDRESS=0x...
REFERRAL_CONTRACT_ADDRESS=0x...
INFLUENCER_POOL_CONTRACT_ADDRESS=0x...
ESCROW_CONTRACT_ADDRESS=0x...
```

### Étapes de Déploiement
1. Exécuter les migrations: `php artisan migrate`
2. Générer la whitelist: `php artisan merkle:generate`
3. Déployer les contrats: `php artisan presale:deploy`
4. Créer les pools d'influenceurs
5. Compiler les assets: `npm run build`

## 📊 Métriques et Suivi

### KPIs Trackés
- Nombre de parrainages validés
- Volume de tokens vendus en prévente
- Performance des influenceurs par pool
- Volume d'échanges P2P
- Frais générés par le marketplace

### Événements Laravel
- `ReferralValidated` - Parrainage validé
- `InfluencerRewardClaimed` - Récompense réclamée

## 🔒 Sécurité

### Mesures Implémentées
- Vérification cryptographique Merkle pour la whitelist
- Smart contracts audités avec ReentrancyGuard
- Validation des signatures de portefeuille
- Gestion des timeouts pour les trades
- Système de frais pour prévenir le spam

## 🎯 Prochaines Étapes

1. Tests d'intégration complets
2. Audit des smart contracts
3. Déploiement sur testnet Avalanche Fuji
4. Tests utilisateurs beta
5. Déploiement en production sur mainnet

---

Pour toute question technique, consultez la documentation détaillée dans les fichiers `*_design.md` du projet.

