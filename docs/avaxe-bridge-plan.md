# Plan — AVAX bridgé (AVAX.e) pour jouer sur Pingala

> Branche : `feat/avaxe-bridge`. Objectif : utiliser du vrai AVAX (bridgé) comme
> devise de mise, en plus du TST natif. Contrat jeu = **BattlepoolV2** (ERC-20),
> on ne touche pas `Battlepool.sol`.

## Rappel des choix validés

1. Bridge **custodial** d'abord (relayer opéré par nous), trustless Warp/ICM ensuite.
2. Contrat **`BattlepoolV2`** séparé (ERC-20).
3. **Coexistence** TST (natif) + AVAX.e (ERC-20).
4. **Gas = TST natif** (inchangé).

## Composants

```
C-Chain (AVAX Fuji)                          Pingala (gas TST)
  AvaVault : lock AVAX  ── relayer ──▶  AVAXe : mint (ERC-20)
                                          │
                                          ▼
                                    BattlepoolV2 (ERC-20)
                                    deposit / payOut / claimAndExit en AVAX.e
```

## Phases

### P0 — Bridge AVAX ↔ AVAX.e (custodial)
- C-Chain : `AvaVault` (dépôt/verrouillage AVAX, retrait par l'opérateur).
- Pingala : `AVAXe` (ERC-20, `mint`/`burn` par l'opérateur).
- Relayer : script/service Node qui synchronise lock↔mint et burn↔unlock.

### P1 — `BattlepoolV2` (ERC-20)
- `deposit(uint256)` (`transferFrom`), `payOut`/`claimAndExit`/`batchPayOut` en `transfer`,
  `updateUserBalance` borné par `token.balanceOf(this)`, `withdrawDevFees` en jetons,
  `setToken` (timelock). Logique (pools, frais, securityCoefficient, limites, cooldown)
  **identique à Battlepool**.

### P2 — Backend
- `users.balance` / `battle_balance` = montants en **AVAX.e**.
- Indexer : écouter les `Transfer` d'AVAXe (dépôts→contrat, payouts sortants).
- Bridge payout : worker wallet dédié (secret `avaxe_payout_operator_pk`) envoie les
  payouts via `transfer` d'AVAXe.
- **Faucet** :
  - TST (gas) conservé ;
  - **NOTE** : cette L1 Pingala a été créée **autour du Fuji testnet** d'Avalanche.
    L'« AVAX » réel bridgé est donc de l'AVAX de **testnet Fuji** (pas du mainnet).
  - On fournit à la fois le **bridge AVAX.e réel (Fuji)** et un **faucet AVAX.e de test**
    pour les tests.

### P3 — Frontend
- Affichage solde AVAX.e (header + dashboard), `approve` + `deposit` ERC-20, claim → retrait AVAX.e.
- Sélecteur de devise (TST natif ou AVAX.e).

### P4 — Intégration / coexistence
- Mises en TST **et/ou** AVAX.e, tests E2E (dépôt → pool → fight → claim), runbook deploy.

### P5 — Sécurité
- Audit `BattlepoolV2` + bridge custodial ; plan de migration vers bridge trustless.

## État (2026-10-23)
- [x] Branche `feat/avaxe-bridge` créée.
- [x] `battlepool/contracts/AVAXe.sol` (ERC-20, mint/burn opérateur).
- [x] `battlepool/contracts/BattlepoolV2.sol` (port ERC-20 complet).
- [ ] P0 bridge (AvaVault + relayer).
- [ ] P2 backend (indexer, payout ERC-20, faucet AVAX.e).
- [ ] P3 frontend.
- [ ] Déploiement + tests.
