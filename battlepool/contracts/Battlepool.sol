// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

contract Battlepool {
    struct Pool {
        uint256 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        string poolSalt; // Changed to string
        mapping(address => bool) isUserInPool; // Track if a user is already in the pool
        uint256 lastActivityBlock; // Track last activity block
    }

    uint256 public nextPoolId = 1;
    mapping(uint256 => Pool) public pools; // Maps baseBet to Pool
    mapping(address => uint256) public userBalances;
    mapping(uint256 => string) public poolHistoryCIDs; // Maps poolId to IPFS CID
    mapping(address => string[]) public sessionHistoryCIDs; // Allows multiple CIDs per user
    mapping(address => string) public userPremoveCIDs; // Maps user address to IPFS CID for premoves
    mapping(address => bool) public isUserInAnyPool;
    mapping(address => uint256) public nextSessionAllowedTime; // Track when user can play again
    event PoolCreated(uint256 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(uint256 indexed poolId, uint256 baseBet, address[] users, string[] premoveCIDs, string poolSalt); // Changed poolSalt to string
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

    address public owner;
    uint256 public securityCoefficient = 1000;
    uint256 public defaultPoolMaxSize; // <<<--- AJOUTEZ CETTE LIGNE
    uint256 public stagnantBlockLimit = 100; // Default 100 blocks

   

    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }

    constructor() {
        owner = msg.sender;
        defaultPoolMaxSize = 5; // <<<--- AJOUTEZ CETTE LIGNE
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
        
    function triggerPoolEmittedEventForTesting(
        uint256 poolId,
        uint256 baseBet,
        address[] memory users,
        string[] memory premoveCIDs,
        string memory poolSalt // Changed to string
    ) external {
        // Emit the PoolEmitted event with the provided parameters
        emit PoolEmitted(poolId, baseBet, users, premoveCIDs, poolSalt);
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
        nextPoolId++;

        emit PoolCreated(newPool.poolId, baseBet, maxSize);
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        require(users.length > 0, "No users to add");
        

        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            // Create a new pool if it doesn't exist
            createPool(baseBet, defaultPoolMaxSize); // Default maxSize set to 5
        }else if (pool.users.length == 0) {
        
            pool.poolId = nextPoolId; // NOT pool.id
            nextPoolId++;
        }

        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address"); // Validate user address
            require(!pool.isUserInPool[users[i]], "User already in pool"); // Ensure user is not already in the pool
            require(!isUserInAnyPool[users[i]], "User in another pool");
            require(block.timestamp >= nextSessionAllowedTime[users[i]], "User is in cooldown");

            pool.users.push(users[i]);
            pool.isUserInPool[users[i]] = true; // Mark user as added to the pool
            isUserInAnyPool[users[i]] = true; // Mark user as in any pool
            pool.lastActivityBlock = block.number; // Update activity


            // Check if the pool is full
            if (pool.users.length == pool.maxSize) {
                _emitAndResetPool(pool); // Emit and reset the pool
            }
        }
    }

    function addSingleUserToPool(uint256 baseBet, address user) public {
        
        require(user != address(0), "Invalid user address");
        
        require(!isUserInAnyPool[user], "User in another pool");
        require(block.timestamp >= nextSessionAllowedTime[user], "User is in cooldown");
        
        
        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize); // Default maxSize set to defaultPoolMaxSize if pool does not exist
        }else if (pool.users.length == 0 ) {
            pool.poolId = nextPoolId; // NOT pool.id
            nextPoolId++;
        }
        

        pool.users.push(user);
        pool.isUserInPool[user] = true; // Mark user as added to the pool
        isUserInAnyPool[user] = true; // Mark user as in any pool
        pool.lastActivityBlock = block.number; // Update activity


        if (pool.users.length == pool.maxSize) {
            _emitAndResetPool(pool);
        }
    }

    function submitPremoveCID(uint256 baseBet, string memory cid) external payable {
        require(bytes(cid).length > 0, "CID cannot be empty");
        require(baseBet > 0, "Base bet must be greater than 0");
        uint256 requiredBalance = baseBet * securityCoefficient;
        require(msg.value >= requiredBalance, "Insufficient balance for the required security margin");



         _processDeposit(msg.sender, msg.value); // Process the deposit
        userPremoveCIDs[msg.sender] = cid; // Store the CID for the user's premoves
        emit PremoveCIDUpdated(msg.sender, cid);

        addSingleUserToPool(baseBet, msg.sender);
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



    function _emitAndResetPool(Pool storage pool) internal {
        // 1) Snapshot users into memory once, so we never accidentally drift
        address[] memory users = pool.users;

        // 2) Generate the salt based on that snapshot
        pool.poolSalt = _generateSalt(users);

        // 3) Gather premove CIDs in lock-step with the snapshot
        string[] memory premoveCIDs = new string[](users.length);
        for (uint256 i = 0; i < users.length; i++) {
            premoveCIDs[i] = userPremoveCIDs[users[i]];
        }

        // 4) Emit using the memory arrays – users and premoveCIDs are guaranteed to align
        emit PoolEmitted(pool.poolId, pool.baseBet, users, premoveCIDs, pool.poolSalt);

        // 5) Now clear out all the tracking mappings
        for (uint256 i = 0; i < users.length; i++) {
            pool.isUserInPool[users[i]]     = false;
            isUserInAnyPool[users[i]]       = false;
            delete userPremoveCIDs[users[i]];
        }

        // 6) Finally, reset the pool's user list
        delete pool.users;
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
        uint256 balance = userBalances[user] += amount;
        emit DepositReceived(user, balance);
    }

    function getUserBalance(address user) external view returns (uint256) {
        return userBalances[user];
    }

    function getContractAddress() external view returns (address) {
        return address(this);
    }


    function isUserInPool(uint256 poolId, address user) public view returns (bool) {
        return pools[poolId].isUserInPool[user];
    }

    //payOut function
    function payOut(address payable user, uint256 amount) external onlyOwner {
        require(user != address(0), "Invalid user address");
        require(amount > 0, "Amount must be greater than 0");
       

        // 🛑 1. Update state **before** sending ETH (prevents reentrancy)
        userBalances[user] = 0;
        isUserInAnyPool[user] = false;

        // ✅ 2. Use `.call{value: amount}("")` instead of `.transfer()`
        (bool success, ) = user.call{value: amount}("");
        require(success, "Payment failed");
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
} 
