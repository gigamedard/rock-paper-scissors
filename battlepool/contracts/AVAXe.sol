// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/token/ERC20/ERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

/**
 * AVAXe — jeton ERC-20 représentant de l'AVAX bridgé (C-Chain → Pingala).
 *
 * - 18 décimales, 1:1 avec l'AVAX verrouillé côté C-Chain.
 * - `mint` / `burn` réservés à l'owner = l'opérateur du bridge (custodial).
 *   On pourra ensuite remplacer l'owner par un contrat bridge trustless (Warp/ICM).
 *
 * NOTE (P2) : Pingala est une L1 créée AUTOUR DU FUJI TESTNET d'Avalanche.
 *   L'« AVAX » bridgé est donc de l'AVAX de testnet Fuji (pas du mainnet).
 */
contract AVAXe is ERC20, Ownable {
    constructor() ERC20("Wrapped AVAX", "AVAXe") Ownable(msg.sender) {}

    /// @dev Minte de l'AVAXe (appelé par l'opérateur bridge après un dépôt AVAX C-Chain).
    function mint(address to, uint256 amount) external onlyOwner {
        _mint(to, amount);
    }

    /// @dev Brûle de l'AVAXe (appelé par l'opérateur bridge avant un retrait AVAX C-Chain).
    function burn(address from, uint256 amount) external onlyOwner {
        _burn(from, amount);
    }
}
