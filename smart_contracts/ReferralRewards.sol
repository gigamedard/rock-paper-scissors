// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/token/ERC20/IERC20.sol";
import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";

contract ReferralRewards is Ownable, ReentrancyGuard {
    IERC20 public sntToken;
    
    uint256 public constant REFERRAL_REWARD = 100 * 10**18; // 100 SNT
    
    mapping(address => bool) public hasClaimedReferralReward;
    mapping(address => uint256) public referralCount;
    
    event ReferralRewardClaimed(address indexed referrer, uint256 amount);
    event ReferralValidated(address indexed referrer, address indexed referred);
    
    constructor(address _sntTokenAddress) {
        sntToken = IERC20(_sntTokenAddress);
    }
    
    /**
     * @dev Validate a referral and distribute rewards
     * @param referrer Address of the referrer
     * @param referred Address of the referred user
     */
    function validateReferral(address referrer, address referred) external onlyOwner {
        require(referrer != address(0), "Invalid referrer address");
        require(referred != address(0), "Invalid referred address");
        require(referrer != referred, "Cannot refer yourself");
        
        // Increment referral count
        referralCount[referrer]++;
        
        // Transfer reward to referrer
        require(sntToken.transfer(referrer, REFERRAL_REWARD), "Token transfer failed");
        
        emit ReferralValidated(referrer, referred);
        emit ReferralRewardClaimed(referrer, REFERRAL_REWARD);
    }
    
    /**
     * @dev Batch validate multiple referrals
     * @param referrers Array of referrer addresses
     * @param referreds Array of referred user addresses
     */
    function batchValidateReferrals(
        address[] calldata referrers,
        address[] calldata referreds
    ) external onlyOwner {
        require(referrers.length == referreds.length, "Arrays length mismatch");
        
        for (uint256 i = 0; i < referrers.length; i++) {
            validateReferral(referrers[i], referreds[i]);
        }
    }
    
    /**
     * @dev Get referral statistics for an address
     * @param user Address to check
     * @return count Number of validated referrals
     * @return totalRewards Total rewards earned
     */
    function getReferralStats(address user) external view returns (uint256 count, uint256 totalRewards) {
        count = referralCount[user];
        totalRewards = count * REFERRAL_REWARD;
    }
    
    /**
     * @dev Emergency function to withdraw tokens
     * @param amount Amount to withdraw
     */
    function emergencyWithdraw(uint256 amount) external onlyOwner {
        require(sntToken.transfer(owner(), amount), "Token transfer failed");
    }
    
    /**
     * @dev Update SNT token address (in case of token migration)
     * @param _newTokenAddress New token contract address
     */
    function updateTokenAddress(address _newTokenAddress) external onlyOwner {
        require(_newTokenAddress != address(0), "Invalid token address");
        sntToken = IERC20(_newTokenAddress);
    }
}

