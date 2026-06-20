// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/utils/cryptography/MessageHashUtils.sol";

contract Battlepool {
    using ECDSA for bytes32;
    struct Pool {
        uint256 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        string poolSalt; // Changed to string
        mapping(address => bool) isUserInPool; // Track if a user is already in the pool
        uint256 lastActivityBlock; // Track last activity block
        bool isLockedForValidation; // Prevents new users before backend validates
    }

    uint256 public nextPoolId = 1;
    mapping(uint256 => Pool) internal pools; // Internal: accessed via helper functions only
    mapping(uint256 => address[]) public poolQueues; // Stores users waiting to join a locked pool
    mapping(address => uint256) public userBalances;
    mapping(uint256 => string) public poolHistoryCIDs; // Maps poolId to IPFS CID
    mapping(address => string[]) public sessionHistoryCIDs; // Allows multiple CIDs per user
    mapping(address => string) public userPremoveCIDs; // Maps user address to IPFS CID for premoves
    mapping(address => bool) public isUserInAnyPool;
    mapping(address => uint256) public nextSessionAllowedTime; // Track when user can play again
    mapping(address => uint256) public nonces; // Replay protection for signatures
    event PoolCreated(uint256 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(uint256 indexed poolId, uint256 baseBet, address[] users, string[] premoveCIDs, string poolSalt, uint256[] balances); // Added balances array
    event DepositReceived(address indexed user, uint256 amount);
    event MatchHistoryCIDUpdated(uint256 indexed poolId, string cid);
    event sessionHistoryCIDUpdated(address indexed user, string cid);
    event PremoveCIDUpdated(address indexed user, string cid);

    event SecurityCoefficientUpdated(uint256 newCoefficient);
    event PayoutProcessed(address indexed wallet, uint256 amount);
    event DefaultPoolMaxSizeChanged(uint256 newSize); // <<<--- AJOUTEZ CETTE LIGNE
    event PoolStagnantRefund(uint256 indexed poolId, uint256 refundedCount, uint256 timestamp);
    event StagnantBlockLimitUpdated(uint256 newLimit);
    event NextSessionTimeUpdated(address indexed user, uint256 nextTime);
    event FeeBasisPointsUpdated(uint256 newFee);
    event DevWalletUpdated(address newWallet);
    event DevFeesWithdrawn(address indexed wallet, uint256 amount);
    event PlayerClaimed(address indexed wallet, uint256 amount, uint256 nonce);

    event DefaultMaxBaseBetChanged(uint256 newLimit);
    event DefaultMaxQChanged(uint256 newLimit);
    event DefaultMinCooldownChanged(uint256 newLimit);
    event UserLimitsUpdated(address indexed user, uint256 maxBaseBet, uint256 maxQ, uint256 minCooldown, uint256 expiry);

    struct UserLimit {
        uint256 maxBaseBet;
        uint256 maxQ;
        uint256 minCooldown;
        uint256 expiry;
    }

    mapping(address => UserLimit) public userLimits;

    uint256 public defaultMaxBaseBet;
    uint256 public defaultMaxQ;
    uint256 public defaultMinCooldown;

    address public owner;
    uint256 public securityCoefficient = 1000;
    uint256 public defaultPoolMaxSize; // <<<--- AJOUTEZ CETTE LIGNE
    uint256 public stagnantBlockLimit = 100; // Default 100 blocks
    
    uint256 public feeBasisPoints = 500; // Default 5.0% fee
    uint256 public devBalance; // Accumulated fees
    address payable public devWallet;

   

    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }

    constructor() {
        owner = msg.sender;
        devWallet = payable(msg.sender); // Default to deployer
        defaultPoolMaxSize = 5; // <<<--- AJOUTEZ CETTE LIGNE
        defaultMaxBaseBet = 0.01 ether;
        defaultMaxQ = 2.0 * 1e18;
        defaultMinCooldown = 86400;
    }

    /**
     * @dev Permet au propriétaire de changer la taille par défaut des nouveaux pools.
     */
    function setDefaultPoolMaxSize(uint256 newSize) external onlyOwner {
        require(newSize >= 2, "Default size must be at least 2");
        defaultPoolMaxSize = newSize;
        emit DefaultPoolMaxSizeChanged(newSize);
    }

    function getContractBalance() external view returns (uint256) {
        return address(this).balance;
    }

    function setSecurityCoefficient(uint256 newCoefficient) external onlyOwner {
        require(newCoefficient > 0, "Coefficient must be greater than 0");
        securityCoefficient = newCoefficient;
        emit SecurityCoefficientUpdated(newCoefficient);
    }

    function setFeeBasisPoints(uint256 newFee) external onlyOwner {
        require(newFee <= 10000, "Fee cannot exceed 100%");
        feeBasisPoints = newFee;
        emit FeeBasisPointsUpdated(newFee);
    }

    function setDevWallet(address payable newWallet) external onlyOwner {
        require(newWallet != address(0), "Invalid wallet address");
        devWallet = newWallet;
        emit DevWalletUpdated(newWallet);
    }

    function withdrawDevFees() external {
        require(msg.sender == devWallet || msg.sender == owner, "Only dev or owner can withdraw");
        uint256 amount = devBalance;
        require(amount > 0, "No fees to withdraw");

        devBalance = 0;
        (bool success, ) = devWallet.call{value: amount}("");
        require(success, "Withdrawal failed");
        
        emit DevFeesWithdrawn(devWallet, amount);
    }
        
    function triggerPoolEmittedEventForTesting(
        uint256 poolId,
        uint256 baseBet,
        address[] memory users,
        string[] memory premoveCIDs,
        string memory poolSalt, // Changed to string
        uint256[] memory balances
    ) external {
        // Emit the PoolEmitted event with the provided parameters
        emit PoolEmitted(poolId, baseBet, users, premoveCIDs, poolSalt, balances);
    }
        
    function createPool(uint256 baseBet, uint256 maxSize) private {
        require(pools[baseBet].poolId == 0, "Pool already exists");
        require(maxSize > 0, "Invalid maxSize");

        Pool storage newPool = pools[baseBet];
        newPool.poolId = nextPoolId;
        newPool.baseBet = baseBet;
        newPool.maxSize = maxSize;
        newPool.poolSalt = ""; // Initialize salt to empty string
        newPool.lastActivityBlock = block.number; // Initialize activity
        newPool.isLockedForValidation = false;
        nextPoolId++;

        emit PoolCreated(newPool.poolId, baseBet, maxSize);
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        require(users.length > 0, "No users to add");
        

        Pool storage pool = pools[baseBet];

        // Ensure a pool object exists
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize);
        } else if (pool.users.length == 0 && !pool.isLockedForValidation) {
            pool.poolId = nextPoolId; // Move to the next pool ID if the previous one is fully processed
            nextPoolId++;
        }

        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address");
            require(baseBet <= getUserMaxBaseBet(users[i]), "Base bet exceeds authorized limit");
            require(!pool.isUserInPool[users[i]], "User already in pool");
            require(!isUserInAnyPool[users[i]], "User in another pool");
            require(block.timestamp >= nextSessionAllowedTime[users[i]], "User is in cooldown");

            if (pool.isLockedForValidation) {
                // If the pool is locked for verification, put the user in the queue
                poolQueues[baseBet].push(users[i]);
                isUserInAnyPool[users[i]] = true; // Mark as taking part in the matchmaking
            } else {
                pool.users.push(users[i]);
                pool.isUserInPool[users[i]] = true; // Mark user as added to the pool
                isUserInAnyPool[users[i]] = true; // Mark user as in any pool
                pool.lastActivityBlock = block.number; // Update activity

                // Check if the pool is full
                if (pool.users.length == pool.maxSize) {
                    _emitPoolValidation(pool); // Emit and lock the pool for backend validation
                }
            }
        }
    }

    function addSingleUserToPool(uint256 baseBet, address user) public {
        
        require(user != address(0), "Invalid user address");
        require(baseBet <= getUserMaxBaseBet(user), "Base bet exceeds authorized limit");
        
        require(!isUserInAnyPool[user], "User in another pool");
        require(block.timestamp >= nextSessionAllowedTime[user], "User is in cooldown");
        
        Pool storage pool = pools[baseBet];

        if (pool.isLockedForValidation) {
            poolQueues[baseBet].push(user);
            isUserInAnyPool[user] = true;
            return;
        }
        
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize); // Default maxSize set to defaultPoolMaxSize if pool does not exist
        } else if (pool.users.length == 0 ) {
            pool.poolId = nextPoolId; // NOT pool.id
            nextPoolId++;
        }
        
        pool.users.push(user);
        pool.isUserInPool[user] = true; // Mark user as added to the pool
        isUserInAnyPool[user] = true; // Mark user as in any pool
        pool.lastActivityBlock = block.number; // Update activity

        if (pool.users.length == pool.maxSize) {
            _emitPoolValidation(pool);
        }
    }

    function submitPremoveCID(uint256 baseBet, string memory cid) external payable {
        require(bytes(cid).length > 0, "CID cannot be empty");
        require(baseBet > 0, "Base bet must be greater than 0");
        require(baseBet <= getUserMaxBaseBet(msg.sender), "Base bet exceeds authorized limit");
        
        uint256 requiredBalance = baseBet * securityCoefficient;
        uint256 depositAmount = (msg.value * 10000) / (10000 + feeBasisPoints);
        require(depositAmount >= requiredBalance, "Insufficient deposit after fees for the required security margin");

         _processDeposit(msg.sender, msg.value); // Process the deposit
        userPremoveCIDs[msg.sender] = cid; // Store the CID for the user's premoves
        emit PremoveCIDUpdated(msg.sender, cid);

        addSingleUserToPool(baseBet, msg.sender);
    }

    function getPoolInfo(uint256 baseBet) external view returns (uint256 poolId, uint256 maxSize, uint256 userCount, bool isLocked) {
        Pool storage pool = pools[baseBet];
        return (pool.poolId, pool.maxSize, pool.users.length, pool.isLockedForValidation);
    }

    function getPoolUsers(uint256 baseBet) external view returns (address[] memory) {
        Pool storage pool = pools[baseBet];
        return pool.users;
    }

    function storeMatchHistoryCID(uint256 poolId, string memory cid) external {
        require(bytes(cid).length > 0, "CID cannot be empty");
        poolHistoryCIDs[poolId] = cid;
        emit MatchHistoryCIDUpdated(poolId, cid);
    }


    function storeSessionCID(address user, string memory cid) external {
        require(bytes(cid).length > 0, "CID cannot be empty");
        sessionHistoryCIDs[user].push(cid); // Append CID instead of replacing it
        emit sessionHistoryCIDUpdated(user, cid);
    }
    
    function getSessionHistoryCIDs(address user) external view returns (string[] memory) {
        return sessionHistoryCIDs[user];
    }

    function getMatchHistoryCID(uint256 poolId) external view returns (string memory) {
        return poolHistoryCIDs[poolId];
    }

    function getPremoveCID(address user) external view returns (string memory) {
        return userPremoveCIDs[user];
    }


    function setPoolMaxSize(uint256 baseBet, uint256 newMaxSize) external onlyOwner {
        Pool storage pool = pools[baseBet];
        require(pool.poolId != 0, "Pool does not exist");
        pool.maxSize = newMaxSize;
    }



    function validatePool(uint256 baseBet) external onlyOwner {
        Pool storage pool = pools[baseBet];
        require(pool.isLockedForValidation, "Pool is not locked for validation");

        // Clear out all the tracking mappings for valid users
        for (uint256 i = 0; i < pool.users.length; i++) {
            pool.isUserInPool[pool.users[i]] = false;
            isUserInAnyPool[pool.users[i]]   = false;
            delete userPremoveCIDs[pool.users[i]];
        }

        delete pool.users;
        pool.isLockedForValidation = false;
        
        _processQueue(baseBet);
    }

    function invalidatePoolUsers(uint256 baseBet, address[] calldata invalidUsers) external onlyOwner {
        Pool storage pool = pools[baseBet];
        require(pool.isLockedForValidation, "Pool is not locked for validation");

        for (uint256 i = 0; i < invalidUsers.length; i++) {
            address invalidUser = invalidUsers[i];
            
            // Remove from pool.users array
            for (uint256 j = 0; j < pool.users.length; j++) {
                if (pool.users[j] == invalidUser) {
                    pool.users[j] = pool.users[pool.users.length - 1];
                    pool.users.pop();
                    break;
                }
            }

            // Clear mappings
            pool.isUserInPool[invalidUser] = false;
            isUserInAnyPool[invalidUser] = false;
            delete userPremoveCIDs[invalidUser];
        }

        // Unlock so it can fill up again
        pool.isLockedForValidation = false;
        
        _processQueue(baseBet);
    }

    function getPoolQueueLength(uint256 baseBet) external view returns (uint256) {
        return poolQueues[baseBet].length;
    }

    function _processQueue(uint256 baseBet) internal {
        Pool storage pool = pools[baseBet];
        address[] storage queue = poolQueues[baseBet];
        
        if (queue.length == 0) return;

        uint256 processedCount = 0;
        
        // Ensure pool exists
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize);
        } else if (pool.users.length == 0) {
            pool.poolId = nextPoolId;
            nextPoolId++;
        }

        for (uint256 i = 0; i < queue.length; i++) {
            if (pool.isLockedForValidation) {
                break; // Stop processing if pool just locked
            }
            address user = queue[i];

            pool.users.push(user);
            pool.isUserInPool[user] = true;
            // isUserInAnyPool is already true from the queue insertion
            pool.lastActivityBlock = block.number;

            processedCount++;

            if (pool.users.length == pool.maxSize) {
                _emitPoolValidation(pool);
            }
        }

        // Remove processed items from queue
        // We shift the remaining items forward and pop the end
        if (processedCount > 0) {
            uint256 remaining = queue.length - processedCount;
            for (uint256 i = 0; i < remaining; i++) {
                queue[i] = queue[i + processedCount];
            }
            for (uint256 i = 0; i < processedCount; i++) {
                queue.pop();
            }
        }
    }

    function _emitPoolValidation(Pool storage pool) internal {
        // 1) Snapshot users into memory once, so we never accidentally drift
        address[] memory users = pool.users;

        // 2) Generate the salt based on that snapshot
        pool.poolSalt = _generateSalt(users);

        // 3) Gather premove CIDs and current balances in lock-step with the snapshot
        string[] memory premoveCIDs = new string[](users.length);
        uint256[] memory balances = new uint256[](users.length);
        
        for (uint256 i = 0; i < users.length; i++) {
            premoveCIDs[i] = userPremoveCIDs[users[i]];
            balances[i]    = userBalances[users[i]];
        }

        // 4) Emit using the memory arrays – users, premoveCIDs, and balances are guaranteed to align
        emit PoolEmitted(pool.poolId, pool.baseBet, users, premoveCIDs, pool.poolSalt, balances);

        // 5) Lock the pool, do NOT clear arrays yet. Backend must validate.
        pool.isLockedForValidation = true;
    }

    // Generate a salt from the concatenated addresses of all users
    function _generateSalt(address[] memory _users) internal pure returns (string memory) {
        bytes memory packedAddresses;
        for (uint256 i = 0; i < _users.length; i++) {
            // Each address is individually packed into its 20-byte representation.
            packedAddresses = abi.encodePacked(packedAddresses, abi.encodePacked(_users[i]));
        }
        bytes32 hash = keccak256(packedAddresses); // Hash the concatenated addresses
        // Convert the hash to a hexadecimal string
        return _toHexString(hash);
    }    


    function _toHexString(bytes32 _bytes) internal pure returns (string memory) {
        bytes memory alphabet = "0123456789abcdef";
        bytes memory str = new bytes(64); // 64 characters for 32 bytes

        for (uint256 i = 0; i < 32; i++) {
            str[i * 2] = alphabet[uint8(_bytes[i] >> 4)]; // First 4 bits
            str[i * 2 + 1] = alphabet[uint8(_bytes[i] & 0x0f)]; // Last 4 bits
        }

    return string(str);
    }

    function deposit() external payable {
        require(msg.value > 0, " from deposit Deposit must be greater than 0");
        _processDeposit(msg.sender, msg.value);
    }

    receive() external payable {
        require(msg.value > 0, " from receive Deposit must be greater than 0");
        emit DepositReceived(msg.sender, msg.value);
    }

    fallback() external payable {
        require(msg.value > 0, "from fallback Deposit must be greater than 0");
        emit DepositReceived(msg.sender, msg.value);
    }

    function _processDeposit(address user, uint256 amount) private {
        // Calculate the actual deposit amount after fees
        uint256 depositAmount = (amount * 10000) / (10000 + feeBasisPoints);
        uint256 feeAmount = amount - depositAmount;

        devBalance += feeAmount;
        uint256 balance = userBalances[user] += depositAmount;
        
        emit DepositReceived(user, balance);
    }

    function getUserBalance(address user) external view returns (uint256) {
        return userBalances[user];
    }

    function getContractAddress() external view returns (address) {
        return address(this);
    }


    function isUserInPoolByBaseBet(uint256 baseBet, address user) public view returns (bool) {
        return pools[baseBet].isUserInPool[user];
    }

    //payOut function
    function payOut(address payable user, uint256 amount) external onlyOwner {
        require(user != address(0), "Invalid user address");
        require(amount > 0, "Amount must be greater than 0");
       

        // 🛑 1. Update state **before** sending ETH (prevents reentrancy)
        userBalances[user] = 0;
        isUserInAnyPool[user] = false;
        delete userPremoveCIDs[user]; // Cleanup residual CID

        // ✅ 2. Use `.call{value: amount}("")` instead of `.transfer()`
        (bool success, ) = user.call{value: amount}("");
        require(success, "Payment failed");

        // 📢 3. Emit an event for tracking
        emit PayoutProcessed(user, amount);
        emit PlayerClaimed(user, amount, 0); // Nonce 0 for admin-triggered payout
    }

    /**
     * @dev Allows a user to claim their funds using a signature from the admin.
     * Prevents the server from paying gas for human withdrawals.
     */
    function claimAndExit(uint256 amount, bytes memory signature) external {
        require(amount > 0, "Amount must be greater than 0");

        // Verify signature: hash(address, amount, nonce, contractAddress)
        bytes32 messageHash = keccak256(abi.encodePacked(msg.sender, amount, nonces[msg.sender], address(this)));
        bytes32 ethSignedMessageHash = MessageHashUtils.toEthSignedMessageHash(messageHash);
        
        address signer = ethSignedMessageHash.recover(signature);
        require(signer == owner, "Invalid admin signature");

        // Update state
        uint256 nonceUsed = nonces[msg.sender];
        nonces[msg.sender]++;
        userBalances[msg.sender] = 0;
        isUserInAnyPool[msg.sender] = false;
        delete userPremoveCIDs[msg.sender];

        // Transfer funds
        (bool success, ) = payable(msg.sender).call{value: amount}("");
        require(success, "Claim payment failed");

        emit PlayerClaimed(msg.sender, amount, nonceUsed);
    }




    function batchPayOut(address[] calldata wallets, uint256[] calldata amounts) external {
        require(wallets.length == amounts.length, "Mismatched arrays");

        for (uint i = 0; i < wallets.length; i++) {
            

            // 🛑 1. Update state first (prevents reentrancy)
            userBalances[wallets[i]] = 0;
            isUserInAnyPool[wallets[i]] = false;

            // ✅ 2. Send ETH safely using `.call{value: amount}("")`
            (bool success, ) = payable(wallets[i]).call{value: amounts[i]}("");
            require(success, "Payment failed");

            // 📢 3. Emit an event for tracking
            emit PayoutProcessed(wallets[i], amounts[i]);
        }
    }

    function setStagnantBlockLimit(uint256 _limit) external onlyOwner {
        stagnantBlockLimit = _limit;
        emit StagnantBlockLimitUpdated(_limit);
    }

    function setUserNextSessionTime(address user, uint256 nextTime) external onlyOwner {
        nextSessionAllowedTime[user] = nextTime;
        emit NextSessionTimeUpdated(user, nextTime);
    }

    function checkAndRefundStagnantPool(uint256 baseBet) external {
        Pool storage pool = pools[baseBet];
        require(pool.poolId != 0, "Pool does not exist");
        require(pool.users.length > 0, "Pool is empty");
        require(block.number > pool.lastActivityBlock + stagnantBlockLimit, "Pool is not stagnant");

        uint256 refundedCount = pool.users.length;
        address[] memory usersToRefund = pool.users;

        for (uint256 i = 0; i < usersToRefund.length; i++) {
            address user = usersToRefund[i];
            
            // Clear user state
            pool.isUserInPool[user] = false;
            isUserInAnyPool[user] = false;
            delete userPremoveCIDs[user];

            // Refund balance
            uint256 amount = userBalances[user];
            if (amount > 0) {
                userBalances[user] = 0;
                (bool success, ) = payable(user).call{value: amount}("");
                require(success, "Refund failed");
            }
        }

        // Reset pool
        delete pool.users;
        pool.lastActivityBlock = block.number; // Reset timer (though pool is empty now)

        emit PoolStagnantRefund(pool.poolId, refundedCount, block.timestamp);
    }

    function getUserMaxBaseBet(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.maxBaseBet == 0) {
            return defaultMaxBaseBet;
        }
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) {
                    return defaultMaxBaseBet;
                }
            } else {
                if (block.timestamp > limit.expiry) {
                    return defaultMaxBaseBet;
                }
            }
        }
        return limit.maxBaseBet;
    }

    function getUserMaxQ(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.maxQ == 0) {
            return defaultMaxQ;
        }
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) {
                    return defaultMaxQ;
                }
            } else {
                if (block.timestamp > limit.expiry) {
                    return defaultMaxQ;
                }
            }
        }
        return limit.maxQ;
    }

    function getUserMinCooldown(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.minCooldown == 0) {
            return defaultMinCooldown;
        }
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) {
                    return defaultMinCooldown;
                }
            } else {
                if (block.timestamp > limit.expiry) {
                    return defaultMinCooldown;
                }
            }
        }
        return limit.minCooldown;
    }

    function setUserLimits(
        address user,
        uint256 maxBaseBet,
        uint256 maxQ,
        uint256 minCooldown,
        uint256 expiry
    ) external onlyOwner {
        userLimits[user] = UserLimit(maxBaseBet, maxQ, minCooldown, expiry);
        emit UserLimitsUpdated(user, maxBaseBet, maxQ, minCooldown, expiry);
    }

    function setDefaultMaxBaseBet(uint256 newLimit) external onlyOwner {
        defaultMaxBaseBet = newLimit;
        emit DefaultMaxBaseBetChanged(newLimit);
    }

    function setDefaultMaxQ(uint256 newLimit) external onlyOwner {
        defaultMaxQ = newLimit;
        emit DefaultMaxQChanged(newLimit);
    }

    function setDefaultMinCooldown(uint256 newLimit) external onlyOwner {
        defaultMinCooldown = newLimit;
        emit DefaultMinCooldownChanged(newLimit);
    }
} 
