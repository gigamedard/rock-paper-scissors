// SPDX-License-Identifier: MIT
pragma solidity ^0.8.23;

contract Battlepool {
    struct Pool {
        uint256 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        string poolSalt; // Changed to string
        mapping(address => bool) isUserInPool; // Track if a user is already in the pool
    }

    uint256 public nextPoolId = 1;
    mapping(uint256 => Pool) public pools; // Maps baseBet to Pool
    mapping(address => uint256) public userBalances;
    mapping(uint256 => string) public poolHistoryCIDs; // Maps poolId to IPFS CID
    mapping(address => string) public userPremoveCIDs; // Maps user address to IPFS CID for premoves

    event PoolCreated(uint256 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(uint256 indexed poolId, uint256 baseBet, address[] users, string[] premoveCIDs, string poolSalt); // Changed poolSalt to string
    event DepositReceived(address indexed user, uint256 amount);
    event MatchHistoryCIDUpdated(uint256 indexed poolId, string cid);
    event PremoveCIDUpdated(address indexed user, string cid);

    event SecurityCoefficientUpdated(uint256 newCoefficient);
    address public owner;
    uint256 public securityCoefficient = 1000;

    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }

    constructor() {
        owner = msg.sender;
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

    function createPool(uint256 baseBet, uint256 maxSize) public {
        require(pools[baseBet].poolId == 0, "Pool already exists");
        require(maxSize > 0, "Invalid maxSize");

        Pool storage newPool = pools[baseBet];
        newPool.poolId = nextPoolId++;
        newPool.baseBet = baseBet;
        newPool.maxSize = maxSize;
        newPool.poolSalt = ""; // Initialize salt to empty string

        emit PoolCreated(newPool.poolId, baseBet, maxSize);
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        require(users.length > 0, "No users to add");

        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            // Create a new pool if it doesn't exist
            createPool(baseBet, 5); // Default maxSize set to 5
        }

        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address"); // Validate user address
            require(!pool.isUserInPool[users[i]], "User already in pool"); // Ensure user is not already in the pool

            pool.users.push(users[i]);
            pool.isUserInPool[users[i]] = true; // Mark user as added to the pool

            // Check if the pool is full
            if (pool.users.length == pool.maxSize) {
                _emitAndResetPool(pool); // Emit and reset the pool
            }
        }
    }

    function addSingleUserToPool(uint256 baseBet, address user) public {
        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            createPool(baseBet, 5); // Default maxSize set to 5 if pool does not exist
        }
        require(user != address(0), "Invalid user address");
        require(!pool.isUserInPool[user], "User already in pool"); // Ensure user is not already in the pool

        pool.users.push(user);
        pool.isUserInPool[user] = true; // Mark user as added to the pool

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

    function getMatchHistoryCID(uint256 poolId) external view returns (string memory) {
        return poolHistoryCIDs[poolId];
    }

    function getPremoveCID(address user) external view returns (string memory) {
        return userPremoveCIDs[user];
    }

    function _emitAndResetPool(Pool storage pool) internal {
        // Generate the salt only when the pool is full
        pool.poolSalt = _generateSalt(pool.users);

        // Gather premove CIDs for all users in the pool
        string[] memory premoveCIDs = new string[](pool.users.length);
        for (uint256 i = 0; i < pool.users.length; i++) {
            premoveCIDs[i] = userPremoveCIDs[pool.users[i]];
        }

        // Emit the pool details with premove CIDs
        emit PoolEmitted(pool.poolId, pool.baseBet, pool.users, premoveCIDs, pool.poolSalt);

        // Reset the pool
        delete pool.users;

        // Clear the user tracking mapping
        for (uint256 i = 0; i < pool.users.length; i++) {
            pool.isUserInPool[pool.users[i]] = false;
        }
    }

    function _generateSalt(address[] memory users) internal pure returns (string memory) {
        // Concatenate all user addresses
        bytes memory concatenatedAddresses;
        for (uint256 i = 0; i < users.length; i++) {
            concatenatedAddresses = abi.encodePacked(concatenatedAddresses, users[i]);
        }

        // Hash the concatenated addresses
        bytes32 hash = keccak256(concatenatedAddresses);

        // Convert the hash to a string
        return string(abi.encodePacked(hash));
    }

    function deposit() external payable {
        require(msg.value > 0, "Deposit must be greater than 0");
        _processDeposit(msg.sender, msg.value);
    }

    receive() external payable {
        require(msg.value > 0, "Deposit must be greater than 0");
        emit DepositReceived(msg.sender, msg.value);
    }

    fallback() external payable {
        require(msg.value > 0, "Deposit must be greater than 0");
        emit DepositReceived(msg.sender, msg.value);
    }

    function _processDeposit(address user, uint256 amount) private {
        userBalances[user] += amount;
        emit DepositReceived(user, amount);
    }

    function getUserBalance(address user) external view returns (uint256) {
        return userBalances[user];
    }

    function getContractAddress() external view returns (address) {
        return address(this);
    }

    function updateUserBalance(address user, uint256 newBalance) external {
        //require(user != address(0), "Invalid user address");
        userBalances[user] = newBalance;
    }

    function isUserInPool(uint256 poolId, address user) public view returns (bool) {
        return pools[poolId].isUserInPool[user];
    }
} 
/* The contract has been updated to use a string for the  poolSalt  instead of bytes32. This change allows for easier handling of the salt value when concatenating user addresses. 
 The  triggerPoolEmittedEventForTesting  function has been added to the contract to allow triggering the  PoolEmitted  event with custom parameters for testing purposes. 
 The  createPool  function now initializes the  poolSalt  to an empty string when creating a new pool. 
 The  addUsersToPool  function has been updated to emit the  PoolEmitted  event and reset the pool when the pool is full. 
 The  addSingleUserToPool  function has been added to allow adding a single user to the pool and emitting the  PoolEmitted  event when the pool is full. 
 The  submitPremoveCID  function now stores the CID for the user's premoves and emits the  PremoveCIDUpdated  event. 
 The  storeMatchHistoryCID  function has been updated to store the match history CID and emit the  MatchHistoryCIDUpdated  event. 
 The  _emitAndResetPool  function has been updated to generate the salt only when the pool is full and emit the  PoolEmitted  event with the premove CIDs. 
 The  _generateSalt  function has been updated to concatenate user addresses and hash the concatenated addresses to generate the salt. 
 The  deposit ,  receive , and  fallback  functions have been updated to emit the  DepositReceived  event when a deposit is made. 
 The  _processDeposit  function has been added to process user deposits and update the user balances. 
 The  getUserBalance  function has been added to retrieve the balance of a specific user. 
 The  getContractAddress  function has been added to retrieve the address of the contract. 
 
 The contract has been updated to include the necessary functions and events for managing pools, user balances, and IPFS CIDs. The changes ensure that the contract can handle pool creation, user additions, CID submissions, and deposit processing effectively. 
 Testing the Smart Contract 
 To test the smart contract, we can use the Hardhat framework to write unit tests that cover the contract's functionality. We will write tests to verify the behavior of the contract functions and events. 
 Create a new file named  Battlepool.test.js  in the  test  directory and add the following code: 
 test/Battlepool.test*/