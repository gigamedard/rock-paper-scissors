# Project Context: Monte Carlo Simulator

## Business Rules
1. **Simulation Type**: Monte Carlo for a "Battle Martingale" system.
2. **Battle Mechanics**: 
   - Costs a `current_bet`.
   - Multiple rounds (Logic depends on chosen Algorithm).
   - **Algo 1**: Standard (+/- Base per round).
   - **Algo 2**: Progressive (+/- scale per round based on depth).
   - **Algo 3**: Pondéré (+/- 2x mise au Round 2 uniquement).
   - Ends at 0 balance or max rounds.
   - **Noise Modifier**: Configurable percentage chance to obtain `+currentBet` on any round win instead of the algorithm's formula.



3. **Martingale Rules**:
   - Win (> initial cost) -> Reset to Base Bet.
   - Loss (< initial cost) -> Double Bet.
   - Cap -> `Limit of doubling` reached -> Reset.
4. **Performance**: Must handle 10,000 simulations without UI freezing.

## Tech Stack
- HTML5, CSS3, JavaScript (Modern).
- Chart.js for visualization.
- Single-file deliverable.
