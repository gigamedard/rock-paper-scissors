// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * @title PranaToken
 * @notice Token natif de l'écosystème BattlePool sur Pingala Chain (chainId 99999).
 *
 * RENOMMAGE SNTToken -> PranaToken (décision utilisateur) :
 * - Le placeholder "Giga Special Token" / "SNT" est remplacé par "Prana" / "PRANA".
 * - Le renommage est effectué AU REDÉPLOIEMENT sur Pingala Chain : il s'agit d'un
 *   nouveau contrat (nouvelle adresse), pas d'un upgrade de l'ancien.
 * - OWNERSHIP (correctif audit, remplace INITIAL_OWNER_ADDRESS) : l'ancienne
 *   constante placeholder 0x8C3229EC…43D (compte de test Hardhat sans clé ni gas)
 *   est SUPPRIMÉE. Le constructeur désigne désormais msg.sender comme owner
 *   Ownable et minte 1 000 000 PRANA directement sur le deployer : l'économie
 *   PRANA devient opérable (le deployer contrôle l'offre initiale).
 *
 * NOTE BRIDGE/CONFIG : la clé `contracts.snt` dans smart_contracts/config.js est
 * CONSERVÉE comme alias technique (app.js, indexer, Web3Helper PHP l'utilisent).
 * Le contrat déployé sous cette clé sera PranaToken ; `config.snt.symbol` (ou tout
 * appel `symbol()` on-chain) renverra "PRANA". Ne pas renommer la clé sans une
 * propagation coordonnée (cf. rapport bp-blockchain Phase C/D).
 */
contract PranaToken is ERC20, Ownable {

    /**
     * @dev Constructeur sans paramètre : le DEPLOYER devient owner Ownable et
     * reçoit l'intégralité de la supply initiale (1 million de PRANA).
     * (Correctif audit : remplace le mint à INITIAL_OWNER_ADDRESS placeholder.)
     */
    constructor()
        ERC20("Prana", "PRANA")
        Ownable(msg.sender)
    {
        _mint(msg.sender, 1000000 * 10**18);
    }
}