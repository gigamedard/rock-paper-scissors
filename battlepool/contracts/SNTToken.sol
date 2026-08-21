// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract SNTToken is ERC20, Ownable {
    
    // Adresse de test Hardhat codée en dur
    address public constant INITIAL_OWNER_ADDRESS = 0x8C3229EC621644789d7F61FAa82c6d0E5F97d43D;

    /**
     * @dev Le constructeur n'a plus besoin de paramètres.
     */
    constructor() 
        ERC20("Giga Special Token", "SNT") 
        Ownable(INITIAL_OWNER_ADDRESS) // On passe l'adresse codée en dur à Ownable
    {
        // On crée 1 million de jetons pour cette adresse
        _mint(INITIAL_OWNER_ADDRESS, 1000000 * 10**18);
    }
}