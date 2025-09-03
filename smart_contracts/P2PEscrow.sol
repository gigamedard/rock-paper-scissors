// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/access/Ownable.sol";

contract P2PEscrow is ReentrancyGuard, Ownable {
    IERC20 public sntToken;
    
    struct Trade {
        address seller;
        uint256 sntAmount;
        uint256 avaxAmount;
        bool isActive;
        bool isCompleted;
        uint256 createdAt;
    }
    
    mapping(uint256 => Trade) public trades;
    uint256 public tradeCounter;
    uint256 public constant TRADE_TIMEOUT = 24 hours;
    uint256 public feePercentage = 250; // 2.5% fee (250 basis points)
    
    event TradeCreated(
        uint256 indexed tradeId,
        address indexed seller,
        uint256 sntAmount,
        uint256 avaxAmount
    );
    
    event TradeAccepted(
        uint256 indexed tradeId,
        address indexed buyer,
        address indexed seller,
        uint256 sntAmount,
        uint256 avaxAmount
    );
    
    event TradeCancelled(uint256 indexed tradeId, address indexed seller);
    event FeeUpdated(uint256 newFeePercentage);
    
    constructor(address _sntTokenAddress) {
        sntToken = IERC20(_sntTokenAddress);
    }
    
    /**
     * @dev Create a new trade offer
     * @param sntAmount Amount of SNT tokens to sell
     * @param avaxAmount Amount of AVAX requested
     */
    function createTrade(uint256 sntAmount, uint256 avaxAmount) external nonReentrant {
        require(sntAmount > 0, "SNT amount must be greater than 0");
        require(avaxAmount > 0, "AVAX amount must be greater than 0");
        
        // Transfer SNT tokens to escrow
        require(
            sntToken.transferFrom(msg.sender, address(this), sntAmount),
            "SNT transfer failed"
        );
        
        uint256 tradeId = tradeCounter++;
        trades[tradeId] = Trade({
            seller: msg.sender,
            sntAmount: sntAmount,
            avaxAmount: avaxAmount,
            isActive: true,
            isCompleted: false,
            createdAt: block.timestamp
        });
        
        emit TradeCreated(tradeId, msg.sender, sntAmount, avaxAmount);
    }
    
    /**
     * @dev Accept an existing trade
     * @param tradeId ID of the trade to accept
     */
    function acceptTrade(uint256 tradeId) external payable nonReentrant {
        Trade storage trade = trades[tradeId];
        
        require(trade.isActive, "Trade is not active");
        require(!trade.isCompleted, "Trade already completed");
        require(msg.sender != trade.seller, "Cannot accept your own trade");
        require(msg.value >= trade.avaxAmount, "Insufficient AVAX sent");
        
        // Calculate fees
        uint256 avaxFee = (trade.avaxAmount * feePercentage) / 10000;
        uint256 sntFee = (trade.sntAmount * feePercentage) / 10000;
        
        uint256 sellerAvaxAmount = trade.avaxAmount - avaxFee;
        uint256 buyerSntAmount = trade.sntAmount - sntFee;
        
        // Mark trade as completed
        trade.isActive = false;
        trade.isCompleted = true;
        
        // Transfer AVAX to seller (minus fee)
        payable(trade.seller).transfer(sellerAvaxAmount);
        
        // Transfer SNT to buyer (minus fee)
        require(sntToken.transfer(msg.sender, buyerSntAmount), "SNT transfer to buyer failed");
        
        // Keep fees in contract (can be withdrawn by owner)
        
        // Refund excess AVAX to buyer
        if (msg.value > trade.avaxAmount) {
            payable(msg.sender).transfer(msg.value - trade.avaxAmount);
        }
        
        emit TradeAccepted(tradeId, msg.sender, trade.seller, trade.sntAmount, trade.avaxAmount);
    }
    
    /**
     * @dev Cancel an active trade
     * @param tradeId ID of the trade to cancel
     */
    function cancelTrade(uint256 tradeId) external nonReentrant {
        Trade storage trade = trades[tradeId];
        
        require(trade.seller == msg.sender, "Only seller can cancel");
        require(trade.isActive, "Trade is not active");
        require(!trade.isCompleted, "Trade already completed");
        
        // Mark trade as inactive
        trade.isActive = false;
        
        // Return SNT tokens to seller
        require(sntToken.transfer(trade.seller, trade.sntAmount), "SNT transfer failed");
        
        emit TradeCancelled(tradeId, msg.sender);
    }
    
    /**
     * @dev Cancel expired trades (can be called by anyone)
     * @param tradeId ID of the trade to cancel
     */
    function cancelExpiredTrade(uint256 tradeId) external nonReentrant {
        Trade storage trade = trades[tradeId];
        
        require(trade.isActive, "Trade is not active");
        require(!trade.isCompleted, "Trade already completed");
        require(
            block.timestamp >= trade.createdAt + TRADE_TIMEOUT,
            "Trade has not expired yet"
        );
        
        // Mark trade as inactive
        trade.isActive = false;
        
        // Return SNT tokens to seller
        require(sntToken.transfer(trade.seller, trade.sntAmount), "SNT transfer failed");
        
        emit TradeCancelled(tradeId, trade.seller);
    }
    
    /**
     * @dev Get trade information
     * @param tradeId ID of the trade
     * @return seller Seller address
     * @return sntAmount Amount of SNT
     * @return avaxAmount Amount of AVAX
     * @return isActive Whether trade is active
     * @return isCompleted Whether trade is completed
     * @return createdAt Creation timestamp
     */
    function getTrade(uint256 tradeId) external view returns (
        address seller,
        uint256 sntAmount,
        uint256 avaxAmount,
        bool isActive,
        bool isCompleted,
        uint256 createdAt
    ) {
        Trade storage trade = trades[tradeId];
        return (
            trade.seller,
            trade.sntAmount,
            trade.avaxAmount,
            trade.isActive,
            trade.isCompleted,
            trade.createdAt
        );
    }
    
    /**
     * @dev Get active trades in a range
     * @param start Starting index
     * @param limit Number of trades to return
     * @return tradeIds Array of trade IDs
     * @return sellers Array of seller addresses
     * @return sntAmounts Array of SNT amounts
     * @return avaxAmounts Array of AVAX amounts
     */
    function getActiveTrades(uint256 start, uint256 limit) external view returns (
        uint256[] memory tradeIds,
        address[] memory sellers,
        uint256[] memory sntAmounts,
        uint256[] memory avaxAmounts
    ) {
        uint256 activeCount = 0;
        
        // Count active trades
        for (uint256 i = start; i < tradeCounter && activeCount < limit; i++) {
            if (trades[i].isActive && !trades[i].isCompleted) {
                activeCount++;
            }
        }
        
        // Initialize arrays
        tradeIds = new uint256[](activeCount);
        sellers = new address[](activeCount);
        sntAmounts = new uint256[](activeCount);
        avaxAmounts = new uint256[](activeCount);
        
        // Fill arrays
        uint256 index = 0;
        for (uint256 i = start; i < tradeCounter && index < activeCount; i++) {
            if (trades[i].isActive && !trades[i].isCompleted) {
                tradeIds[index] = i;
                sellers[index] = trades[i].seller;
                sntAmounts[index] = trades[i].sntAmount;
                avaxAmounts[index] = trades[i].avaxAmount;
                index++;
            }
        }
    }
    
    /**
     * @dev Get trading statistics
     * @return totalTrades Total number of trades created
     * @return activeTrades Number of active trades
     * @return completedTrades Number of completed trades
     */
    function getStats() external view returns (
        uint256 totalTrades,
        uint256 activeTrades,
        uint256 completedTrades
    ) {
        totalTrades = tradeCounter;
        
        for (uint256 i = 0; i < tradeCounter; i++) {
            if (trades[i].isActive && !trades[i].isCompleted) {
                activeTrades++;
            } else if (trades[i].isCompleted) {
                completedTrades++;
            }
        }
    }
    
    /**
     * @dev Update fee percentage (owner only)
     * @param _feePercentage New fee percentage in basis points
     */
    function updateFeePercentage(uint256 _feePercentage) external onlyOwner {
        require(_feePercentage <= 1000, "Fee cannot exceed 10%"); // Max 10%
        feePercentage = _feePercentage;
        emit FeeUpdated(_feePercentage);
    }
    
    /**
     * @dev Withdraw collected fees (owner only)
     */
    function withdrawFees() external onlyOwner {
        uint256 avaxBalance = address(this).balance;
        uint256 sntBalance = sntToken.balanceOf(address(this));
        
        // Calculate fees (approximate, as we don't track exact fee amounts)
        // This is a simplified approach - in production, you'd want to track fees separately
        
        if (avaxBalance > 0) {
            payable(owner()).transfer(avaxBalance);
        }
        
        if (sntBalance > 0) {
            // Only withdraw if there are no active trades
            uint256 lockedSnt = 0;
            for (uint256 i = 0; i < tradeCounter; i++) {
                if (trades[i].isActive && !trades[i].isCompleted) {
                    lockedSnt += trades[i].sntAmount;
                }
            }
            
            uint256 availableSnt = sntBalance - lockedSnt;
            if (availableSnt > 0) {
                require(sntToken.transfer(owner(), availableSnt), "SNT transfer failed");
            }
        }
    }
    
    /**
     * @dev Emergency function to update SNT token address
     * @param _newTokenAddress New token contract address
     */
    function updateTokenAddress(address _newTokenAddress) external onlyOwner {
        require(_newTokenAddress != address(0), "Invalid token address");
        sntToken = IERC20(_newTokenAddress);
    }
}

