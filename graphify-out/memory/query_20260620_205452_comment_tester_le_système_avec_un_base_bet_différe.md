---
type: "query"
date: "2026-06-20T20:54:52.810821+00:00"
question: "Comment tester le système avec un base bet différent pour les bots ?"
contributor: "graphify"
source_nodes: ["scratch/test_bots_bet.ps1", "app/Services/InternalPoolService.php", "app/Models/User.php", "battlepool/contracts/Battlepool.sol", "smart_contracts/run_batch_processor.js"]
---

# Q: Comment tester le système avec un base bet différent pour les bots ?

## Answer

Utiliser le script unifié .\scratch\test_bots_bet.ps1 -BaseBet <montant>. Ce script met à jour tous les bots dans la base de données via Tinker, et relance l'instance de run_batch_processor.js en lui passant la variable d'environnement BASE_BET. Côté backend, User.php autorise désormais des limites jusqu'à 100 ETH, et Battlepool.sol a vu son defaultMaxBaseBet ajusté. InternalPoolService.php conserve également dynamiquement le base_bet des bots.

## Source Nodes

- scratch/test_bots_bet.ps1
- app/Services/InternalPoolService.php
- app/Models/User.php
- battlepool/contracts/Battlepool.sol
- smart_contracts/run_batch_processor.js