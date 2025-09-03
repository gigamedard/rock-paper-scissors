// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";
import "@openzeppelin/contracts/utils/cryptography/MerkleProof.sol";

contract SNTPresale is Ownable, ReentrancyGuard {
    IERC20 public sntToken;
    
    uint256 public constant TOKEN_PRICE = 0.001 ether; // 0.001 AVAX per SNT
    uint256 public constant MAX_PURCHASE = 1000 * 10**18; // 1000 SNT max per address
    
    bytes32 public merkleRoot;
    bool public presaleActive = false;
    
    mapping(address => uint256) public purchasedAmount;
    
    event TokensPurchased(address indexed buyer, uint256 amount, uint256 cost);
    event PresaleStatusChanged(bool active);
    event MerkleRootUpdated(bytes32 newRoot);
    
    constructor(address _sntTokenAddress, bytes32 _merkleRoot) {
        sntToken = IERC20(_sntTokenAddress);
        merkleRoot = _merkleRoot;
    }
    
    /**
     * @dev Buy tokens during presale (whitelist only)
     * @param amount Amount of tokens to buy
     * @param merkleProof Merkle proof for whitelist verification
     */
    function buyTokens(uint256 amount, bytes32[] calldata merkleProof) 
        external 
        payable 
        nonReentrant 
    {
        require(presaleActive, "Presale is not active");
        require(amount > 0, "Amount must be greater than 0");
        require(purchasedAmount[msg.sender] + amount <= MAX_PURCHASE, "Exceeds max purchase limit");
        
        // Verify whitelist
        bytes32 leaf = keccak256(abi.encodePacked(msg.sender));
        require(MerkleProof.verify(merkleProof, merkleRoot, leaf), "Not whitelisted");
        
        uint256 cost = amount * TOKEN_PRICE / 10**18;
        require(msg.value >= cost, "Insufficient AVAX sent");
        
        // Update purchased amount
        purchasedAmount[msg.sender] += amount;
        
        // Transfer tokens to buyer
        require(sntToken.transfer(msg.sender, amount), "Token transfer failed");
        
        // Refund excess AVAX
        if (msg.value > cost) {
            payable(msg.sender).transfer(msg.value - cost);
        }
        
        emit TokensPurchased(msg.sender, amount, cost);
    }
    
    /**
     * @dev Check if an address is whitelisted
     * @param user Address to check
     * @param merkleProof Merkle proof for verification
     * @return bool True if whitelisted
     */
    function isWhitelisted(address user, bytes32[] calldata merkleProof) 
        external 
        view 
        returns (bool) 
    {
        bytes32 leaf = keccak256(abi.encodePacked(user));
        return MerkleProof.verify(merkleProof, merkleRoot, leaf);
    }
    
    /**
     * @dev Get purchase information for an address
     * @param user Address to check
     * @return purchased Amount already purchased
     * @return remaining Amount still available to purchase
     */
    function getPurchaseInfo(address user) 
        external 
        view 
        returns (uint256 purchased, uint256 remaining) 
    {
        purchased = purchasedAmount[user];
        remaining = MAX_PURCHASE - purchased;
    }
    
    /**
     * @dev Set presale status (owner only)
     * @param _active New presale status
     */
    function setPresaleActive(bool _active) external onlyOwner {
        presaleActive = _active;
        emit PresaleStatusChanged(_active);
    }
    
    /**
     * @dev Update Merkle root (owner only)
     * @param _newRoot New Merkle root
     */
    function updateMerkleRoot(bytes32 _newRoot) external onlyOwner {
        merkleRoot = _newRoot;
        emit MerkleRootUpdated(_newRoot);
    }
    
    /**
     * @dev Withdraw collected AVAX (owner only)
     */
    function withdrawAVAX() external onlyOwner {
        uint256 balance = address(this).balance;
        require(balance > 0, "No AVAX to withdraw");
        payable(owner()).transfer(balance);
    }
    
    /**
     * @dev Emergency function to withdraw remaining tokens (owner only)
     * @param amount Amount to withdraw
     */
    function withdrawTokens(uint256 amount) external onlyOwner {
        require(sntToken.transfer(owner(), amount), "Token transfer failed");
    }
    
    /**
     * @dev Update SNT token address (owner only)
     * @param _newTokenAddress New token contract address
     */
    function updateTokenAddress(address _newTokenAddress) external onlyOwner {
        require(_newTokenAddress != address(0), "Invalid token address");
        sntToken = IERC20(_newTokenAddress);
    }
    
    /**
     * @dev Get contract balance information
     * @return avaxBalance AVAX balance
     * @return tokenBalance SNT token balance
     */
    function getBalances() external view returns (uint256 avaxBalance, uint256 tokenBalance) {
        avaxBalance = address(this).balance;
        tokenBalance = sntToken.balanceOf(address(this));
    }
}

