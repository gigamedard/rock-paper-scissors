// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

/**
 * ClaimPoc — contrat VOLONTAIREMENT trivial.
 *
 * Objectif : isoler le comportement du WALLET (Core) autour du déclenchement
 * d'une transaction de "claim". La fonction ne fait STRICTEMENT RIEN : aucun
 * transfert, aucun stockage, aucune logique métier.
 *
 * ⚠️  Ce contrat ne doit être déployé QUE sur un réseau de test / la L1 de test
 *     (Pingala Chain, chainId 99999). Ne l'utilise pas sur un réseau où des
 *     fonds réels seraient en jeu.
 *
 * Sélecteur utilisé par la dApp PoC : claim() = 0x4e71d92d
 */
contract ClaimPoc {
    // Fonction de claim vide : sert uniquement de cible à la transaction.
    function claim() external {}
}
