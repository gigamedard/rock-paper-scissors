// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";

/**
 * @title WAVAX
 * @notice Wrapper canonique d'AVAX natif en ERC-20 (pattern WETH9), 1:1.
 *
 * DÉCISION DESIGN (Phase A) : WAVAX est un wrapper PASSIF, sans Ownable et sans
 * mint administratif. Le 1:1 avec l'AVAX natif est garanti par construction :
 * `balanceOf(this)` (AVAX détenu par le contrat) == `totalSupply()` (tokens émis).
 * Aucun mint arbitraire n'est possible, donc aucun pont de confiance n'est
 * nécessaire pour la beta : l'utilisateur wrappe son AVAX lui-même via
 * `deposit()` (ou un simple envoi d'AVAX au contrat, géré par `receive()`).
 *
 * Le pont réel Pingala <-> chaines externes (contrat de bridge, owner-mint etc.)
 * viendra en phase ultérieure ; il pourra s'appuyer sur ce wrapper ou exposer
 * sa propre logique, sans modifier ce contrat.
 *
 * SÉCURITÉ :
 * - deposit()/receive() : mint 1:1 avec le value reçu. Pas de réentrance possible
 *   (pas d'appel externe, uniquement _mint).
 * - withdraw()/redeem() : effects-before-interactions — balanceOf décrémenté AVANT
 *   l'envoi d'AVAX. Le pattern WETH9 utilise (bool).call{value}() et ignore
 *   volontairement le retour pour ne pas bloquer les multisigs ; ici on exige le
 *   succès explicite pour échouer proprement (pas de transfer() legacy, deprecated).
 */
contract WAVAX is ERC20 {

    // Events additionnels du pattern WETH9 (au-delà des Transfer/Approval ERC20).
    event Deposit(address indexed dst, uint256 wad);
    event Withdrawal(address indexed src, uint256 wad);

    constructor() ERC20("Wrapped AVAX", "WAVAX") {}

    /// @notice Wrappe l'AVAX natif envoyé en WAVAX (1:1).
    function deposit() public payable {
        _mint(msg.sender, msg.value);
        emit Deposit(msg.sender, msg.value);
    }

    /// @notice Dérange un montant `wad` de WAVAX et renvoie l'AVAX natif (1:1).
    function redeem(uint256 wad) public {
        withdraw(wad);
    }

    /// @notice Dérange un montant `wad` de WAVAX et renvoie l'AVAX natif (1:1).
    /// CEI : _burn AVANT l'interaction externe (envoi d'AVAX).
    function withdraw(uint256 wad) public {
        require(balanceOf(msg.sender) >= wad, "WAVAX: insufficient balance");
        _burn(msg.sender, wad); // effects d'abord
        (bool success, ) = msg.sender.call{value: wad}(""); // interaction ensuite
        require(success, "WAVAX: AVAX transfer failed");
        emit Withdrawal(msg.sender, wad);
    }

    /// @notice Tout envoi direct d'AVAX au contrat déclenche un wrap (mint 1:1).
    receive() external payable {
        deposit();
    }
}