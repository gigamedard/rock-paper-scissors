# Mesure — temps de session (security=100, T=1.2, frais 0.5 %)

**Date** : 2026-11-11 · **Moteur** : full in-memory (`sim:memory`, image `engine37`),
cohorte **1000 bots** remise à plat (`bet_amount = initial_base_bet = 0.0004`).
**Durée du run** : ~66 min · **Sessions terminées** : **6421**.

## Configuration testée

| Paramètre | Valeur |
|---|---|
| Contrat `BattlepoolV2.feeBasisPoints` | **50** (0,5 %) — tx `0x644d9229…a60bc0` |
| `GameSetting.smart_contract_fee_percentage` | **0.5** |
| `BANKROLL_SESSION_TARGET_Q` | **1.2** |
| `BANKROLL_SECURITY_COEFFICIENT` | **100** |
| `BANKROLL_FEE_RATE` | **0.005** |
| `REFUND_PCT` | **0.15** (calibré, voir viabilité) |
| `baseBet` | 0.0004 |
| `C = baseBet × security` | 0.04 |
| `X = (1+t+φ·T)/t` | 6.03 |
| `R = C·X` / `B = C+R` | 0.2412 / **0.2812** |
| Seuil effectif session | `T·(1+φ) = 1.206` |

Théorie : `P(win par tentative) = 1/1.206 = 82.9 %` ;
`P(double échec) = (1−1/1.206)² = 2.92 %`.

## Résultats globaux

- Sessions gagnées : **6261 (97.5 %)**
- Double échec (perdue) : **160 (2.49 %)**
- (Le taux de perte observé est sous-estimé : les pertes sont longues → censure à droite.)

### Sessions GAGNÉES

| Statistique | Pools | Temps (in-memory, 1000 bots) |
|---|---|---|
| moyenne | 777 | **7.40 min** (censuré à droite — voir note) |
| médiane | **338** | **3.52 min** |
| p25 / p75 | 137 / 992 | 1.39 / 9.83 min |
| p90 | 2191 | **20.2 min** |
| p99 | 4627 | 43.1 min |
| max observé | 9044 | 63.8 min |

### Sessions PERDUES (double échec)

| Statistique | Pools | Temps (in-memory, 1000 bots) |
|---|---|---|
| moyenne | 3331 | **20.8 min** |
| médiane | **3119** | **17.7 min** |
| p25 / p75 | 2194 / 4289 | 12.8 / 27.5 min |
| p90 | 5269 | **37.0 min** |
| p99 | 7055 | 48.9 min |
| max observé | 8870 | 50.4 min |

> **Note censure** : la moyenne des gains augmente encore avec la durée du run
> (1.2 min sur les 500 premières sessions → 7.4 min sur 6261). Le maximum observé
> (63.8 min) est proche de la durée du run (~66 min) : la vraie moyenne est donc
> **> 7.4 min**. La **médiane (3.5 min)** et les percentiles sont stables.
> La distribution est à **queue lourde**, comme déjà mesuré.

## Interprétation

1. **Sessions jouables** : à `security=100`, la session gagnée médiane est de
   **~3,5 min** (p90 ~20 min). C'est compatible avec un vrai joueur.
2. **Pertes plus longues** : un double échec dure **~18 min médian** (p90 ~37 min).
   Le mécanisme de remboursement (file FIFO) reste le filet pratique.
3. **Écart moteur DB vs in-memory** : le moteur DB (`game:engine`, 1000 bots) traite
   ~**4–6 pools/s au total**, soit ~**0,05–0,08 pool/s/joueur**. Avec 338 pools médians,
   une session gagnée prendrait **~1,1–1,9 h** sur le moteur DB, contre ~3,5 min en
   in-memory. ⇒ Pour la parité bots/humains, les humains doivent passer sur le
   **moteur in-memory + outbox** (P2.1 live), pas rester sur le moteur DB.
4. **Viabilité du remboursement** : couverture ≈ `φ·T / q` = `0.006 / 0.0292 = 20.6 %`.
   `REFUND_PCT=0.15` reste sous la couverture (marge de sécurité).

## Reproduction

```sh
# 1) config (VPS /opt/battlepool/.env.staging)
BANKROLL_SESSION_TARGET_Q=1.2
BANKROLL_SECURITY_COEFFICIENT=100
BANKROLL_FEE_RATE=0.005
REFUND_PCT=0.15
# GameSetting = 0.5 ; contrat setFeeBasisPoints(50)

# 2) cohorte propre (bots autoplay) : bet_amount = initial_base_bet, attempt=0

# 3) arrêter engine + batch-processor, puis :
docker compose -f docker-compose.prod.yml up -d --no-deps --force-recreate sim-memory
# les sessions terminées sont loggées dans /var/www/html/storage/app/sessions.csv
# format : outcome(W/F),pools,seconds
```

Le CSV brut est conservé : `tests/measurements/sessions_security100_T1.2_fee0.5.csv`.
