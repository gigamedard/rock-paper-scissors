// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "../WAVAX.sol";

/**
 * @title WavaxRejectReceiver
 * @notice Mock utilitaire de test UNIQUEMENT : wrappe de l'AVAX en WAVAX puis
 * REFUSE tout AVAX natif entrant (fallback revert). Sert à vérifier que
 * WAVAX.withdraw() échoue proprement (`require(success, ...)`) quand le
 * destinataire ne peut pas recevoir d'AVAX, et que l'état reste cohérent (CEI :
 * le burn est annulé avec la transaction).
 */
contract WavaxRejectReceiver {
    WAVAX public immutable wavax;

    constructor(address payable _wavax) {
        wavax = WAVAX(_wavax);
    }

    /// @notice Wrappe `msg.value` AVAX en WAVAX pour ce contrat.
    function wrap() external payable {
        wavax.deposit{value: msg.value}();
    }

    /// @dev Refuse tout AVAX natif envoyé directement à ce contrat.
    fallback() external payable {
        revert("rejecting native AVAX");
    }
}