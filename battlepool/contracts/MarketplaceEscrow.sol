// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/utils/Pausable.sol";
// --- CHEMIN CORRIGÉ CI-DESSOUS ---
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol"; 

contract MarketplaceEscrow is Ownable, Pausable, ReentrancyGuard {

    // --- State Variables ---

    IERC20 public immutable sntToken;
    uint256 public nextOfferId;
    uint256 public feePercentage; // Ex: 1 = 1%

    struct Offer {
        uint256 id;
        address payable seller;
        uint256 sntAmount;
        uint256 avaxAmount;
        uint256 expiresAt;
        Status status;
    }

    enum Status { Open, Fulfilled, Cancelled }

    mapping(uint256 => Offer) public offers;

    // --- Events ---

    event OfferCreated(uint256 indexed offerId, address indexed seller, uint256 sntAmount, uint256 avaxAmount);
    event OfferFulfilled(uint256 indexed offerId, address indexed buyer);
    event OfferCancelled(uint256 indexed offerId);

    // --- Constructor ---

    constructor(address _tokenAddress, address _initialOwner) Ownable(_initialOwner) {
        sntToken = IERC20(_tokenAddress);
        nextOfferId = 1;
        feePercentage = 1; // 1% de frais sur chaque transaction réussie
    }

    // --- Core Functions ---

    /**
     * @notice Crée une offre de vente de SNT contre des AVAX.
     * @dev L'utilisateur doit d'abord `approve` ce contrat pour dépenser ses SNT.
     */
    function createOffer(uint256 _sntAmount, uint256 _avaxAmount, uint256 _durationHours) external whenNotPaused {
        require(_sntAmount > 0 && _avaxAmount > 0, "Amounts must be positive");
        
        // 1. Transfère les SNT du vendeur vers ce contrat (escrow)
        sntToken.transferFrom(msg.sender, address(this), _sntAmount);

        // 2. Crée l'offre
        offers[nextOfferId] = Offer({
            id: nextOfferId,
            seller: payable(msg.sender),
            sntAmount: _sntAmount,
            avaxAmount: _avaxAmount,
            expiresAt: block.timestamp + (_durationHours * 1 hours),
            status: Status.Open
        });

        emit OfferCreated(nextOfferId, msg.sender, _sntAmount, _avaxAmount);
        nextOfferId++;
    }

    /**
     * @notice Accepte et exécute une offre en envoyant des AVAX.
     */
    function fulfillOffer(uint256 _offerId) external payable whenNotPaused nonReentrant {
        Offer storage offer = offers[_offerId];

        require(offer.status == Status.Open, "Offer not open");
        require(block.timestamp < offer.expiresAt, "Offer expired");
        require(msg.value == offer.avaxAmount, "Incorrect AVAX amount sent");
        require(msg.sender != offer.seller, "Cannot buy your own offer");

        // 1. Marque l'offre comme remplie pour éviter les ré-entrées
        offer.status = Status.Fulfilled;

        // 2. Calcule les frais
        uint256 fee = (msg.value * feePercentage) / 100;
        uint256 sellerAmount = msg.value - fee;

        // 3. Transfère les SNT à l'acheteur
        sntToken.transfer(msg.sender, offer.sntAmount);

        // 4. Transfère les AVAX au vendeur (moins les frais)
        (bool sent,) = offer.seller.call{value: sellerAmount}("");
        require(sent, "AVAX transfer failed");

        // 5. Transfère les frais au propriétaire du contrat
        (bool feeSent,) = owner().call{value: fee}("");
        require(feeSent, "Fee transfer failed");

        emit OfferFulfilled(_offerId, msg.sender);
    }

    /**
     * @notice Annule une offre qui n'a pas encore été acceptée.
     */
    function cancelOffer(uint256 _offerId) external whenNotPaused {
        Offer storage offer = offers[_offerId];

        require(offer.seller == msg.sender, "Not your offer");
        require(offer.status == Status.Open, "Offer not open");

        // 1. Marque l'offre comme annulée
        offer.status = Status.Cancelled;

        // 2. Retourne les SNT au vendeur
        sntToken.transfer(offer.seller, offer.sntAmount);

        emit OfferCancelled(_offerId);
    }
    
    // --- Admin Functions ---
    function setFeePercentage(uint256 _newFee) external onlyOwner {
        feePercentage = _newFee;
    }

    function pause() external onlyOwner {
        _pause();
    }

    function unpause() external onlyOwner {
        _unpause();
    }
}