// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/security/ReentrancyGuard.sol";

contract InfluencerRewardPool is Ownable, ReentrancyGuard {
    struct Pool {
        string name;
        string language;
        uint256 milestone;
        uint256 poolMilestone;
        uint256 rewardAmount;
        bool isActive;
        uint256 totalReferrals;
        mapping(address => bool) influencers;
        mapping(address => bool) eligibleInfluencers;
        mapping(address => uint256) influencerReferrals;
        mapping(address => bool) hasClaimed;
        address[] influencerList;
    }
    
    mapping(uint256 => Pool) public pools;
    mapping(address => uint256) public influencerPool;
    uint256 public poolCount;
    
    event PoolCreated(uint256 indexed poolId, string name, string language);
    event InfluencerAdded(uint256 indexed poolId, address indexed influencer);
    event InfluencerEligibilityUpdated(uint256 indexed poolId, address indexed influencer, bool eligible);
    event ReferralUpdated(uint256 indexed poolId, address indexed influencer, uint256 newCount);
    event RewardClaimed(uint256 indexed poolId, address indexed influencer, uint256 amount);
    event PoolFunded(uint256 indexed poolId, uint256 amount);
    
    /**
     * @dev Create a new influencer pool
     * @param name Pool name
     * @param language Pool language
     * @param milestone Individual milestone for influencers
     * @param poolMilestone Total pool milestone
     */
    function createPool(
        string memory name,
        string memory language,
        uint256 milestone,
        uint256 poolMilestone
    ) external onlyOwner returns (uint256) {
        uint256 poolId = poolCount++;
        Pool storage newPool = pools[poolId];
        
        newPool.name = name;
        newPool.language = language;
        newPool.milestone = milestone;
        newPool.poolMilestone = poolMilestone;
        newPool.isActive = true;
        
        emit PoolCreated(poolId, name, language);
        return poolId;
    }
    
    /**
     * @dev Add an influencer to a pool
     * @param poolId Pool ID
     * @param influencer Influencer address
     */
    function addInfluencer(uint256 poolId, address influencer) external onlyOwner {
        require(poolId < poolCount, "Pool does not exist");
        require(!pools[poolId].influencers[influencer], "Influencer already in pool");
        
        pools[poolId].influencers[influencer] = true;
        pools[poolId].influencerList.push(influencer);
        influencerPool[influencer] = poolId;
        
        emit InfluencerAdded(poolId, influencer);
    }
    
    /**
     * @dev Set influencer eligibility
     * @param poolId Pool ID
     * @param influencer Influencer address
     * @param eligible Eligibility status
     */
    function setInfluencerEligibility(
        uint256 poolId, 
        address influencer, 
        bool eligible
    ) external onlyOwner {
        require(poolId < poolCount, "Pool does not exist");
        require(pools[poolId].influencers[influencer], "Not an influencer in this pool");
        
        pools[poolId].eligibleInfluencers[influencer] = eligible;
        
        emit InfluencerEligibilityUpdated(poolId, influencer, eligible);
    }
    
    /**
     * @dev Update influencer referral count
     * @param poolId Pool ID
     * @param influencer Influencer address
     * @param referralCount New referral count
     */
    function updateInfluencerReferrals(
        uint256 poolId,
        address influencer,
        uint256 referralCount
    ) external onlyOwner {
        require(poolId < poolCount, "Pool does not exist");
        require(pools[poolId].influencers[influencer], "Not an influencer in this pool");
        
        uint256 oldCount = pools[poolId].influencerReferrals[influencer];
        pools[poolId].influencerReferrals[influencer] = referralCount;
        
        // Update total pool referrals
        if (referralCount > oldCount) {
            pools[poolId].totalReferrals += (referralCount - oldCount);
        } else {
            pools[poolId].totalReferrals -= (oldCount - referralCount);
        }
        
        emit ReferralUpdated(poolId, influencer, referralCount);
    }
    
    /**
     * @dev Fund a pool with AVAX
     * @param poolId Pool ID
     */
    function fundPool(uint256 poolId) external payable onlyOwner {
        require(poolId < poolCount, "Pool does not exist");
        require(msg.value > 0, "Must send AVAX");
        
        pools[poolId].rewardAmount += msg.value;
        
        emit PoolFunded(poolId, msg.value);
    }
    
    /**
     * @dev Claim reward for eligible influencer
     * @param poolId Pool ID
     */
    function claimReward(uint256 poolId) external nonReentrant {
        require(poolId < poolCount, "Pool does not exist");
        
        Pool storage pool = pools[poolId];
        require(pool.influencers[msg.sender], "Not an influencer in this pool");
        require(pool.eligibleInfluencers[msg.sender], "Not eligible for rewards");
        require(!pool.hasClaimed[msg.sender], "Already claimed");
        require(pool.influencerReferrals[msg.sender] >= pool.milestone, "Milestone not reached");
        require(pool.totalReferrals >= pool.poolMilestone, "Pool milestone not reached");
        
        // Calculate eligible influencers count
        uint256 eligibleCount = 0;
        for (uint256 i = 0; i < pool.influencerList.length; i++) {
            address influencer = pool.influencerList[i];
            if (pool.eligibleInfluencers[influencer] && 
                pool.influencerReferrals[influencer] >= pool.milestone &&
                !pool.hasClaimed[influencer]) {
                eligibleCount++;
            }
        }
        
        require(eligibleCount > 0, "No eligible influencers");
        
        uint256 rewardAmount = pool.rewardAmount / eligibleCount;
        require(rewardAmount > 0, "No reward available");
        
        pool.hasClaimed[msg.sender] = true;
        
        // Transfer reward
        payable(msg.sender).transfer(rewardAmount);
        
        emit RewardClaimed(poolId, msg.sender, rewardAmount);
    }
    
    /**
     * @dev Get pool information
     * @param poolId Pool ID
     * @return name Pool name
     * @return language Pool language
     * @return milestone Individual milestone
     * @return poolMilestone Pool milestone
     * @return rewardAmount Total reward amount
     * @return totalReferrals Total referrals in pool
     * @return isActive Pool status
     */
    function getPoolInfo(uint256 poolId) external view returns (
        string memory name,
        string memory language,
        uint256 milestone,
        uint256 poolMilestone,
        uint256 rewardAmount,
        uint256 totalReferrals,
        bool isActive
    ) {
        require(poolId < poolCount, "Pool does not exist");
        Pool storage pool = pools[poolId];
        
        return (
            pool.name,
            pool.language,
            pool.milestone,
            pool.poolMilestone,
            pool.rewardAmount,
            pool.totalReferrals,
            pool.isActive
        );
    }
    
    /**
     * @dev Get influencer information in a pool
     * @param poolId Pool ID
     * @param influencer Influencer address
     * @return isInfluencer Whether address is an influencer
     * @return isEligible Whether influencer is eligible
     * @return referralCount Influencer's referral count
     * @return hasClaimed Whether influencer has claimed reward
     */
    function getInfluencerInfo(uint256 poolId, address influencer) external view returns (
        bool isInfluencer,
        bool isEligible,
        uint256 referralCount,
        bool hasClaimed
    ) {
        require(poolId < poolCount, "Pool does not exist");
        Pool storage pool = pools[poolId];
        
        return (
            pool.influencers[influencer],
            pool.eligibleInfluencers[influencer],
            pool.influencerReferrals[influencer],
            pool.hasClaimed[influencer]
        );
    }
    
    /**
     * @dev Get all influencers in a pool
     * @param poolId Pool ID
     * @return influencers Array of influencer addresses
     */
    function getPoolInfluencers(uint256 poolId) external view returns (address[] memory) {
        require(poolId < poolCount, "Pool does not exist");
        return pools[poolId].influencerList;
    }
    
    /**
     * @dev Emergency withdraw function
     * @param amount Amount to withdraw
     */
    function emergencyWithdraw(uint256 amount) external onlyOwner {
        require(amount <= address(this).balance, "Insufficient balance");
        payable(owner()).transfer(amount);
    }
    
    /**
     * @dev Set pool active status
     * @param poolId Pool ID
     * @param active New status
     */
    function setPoolActive(uint256 poolId, bool active) external onlyOwner {
        require(poolId < poolCount, "Pool does not exist");
        pools[poolId].isActive = active;
    }
}

