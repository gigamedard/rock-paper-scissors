// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

contract Battlepool {
    struct Pool {
        bytes32 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        string poolSalt;
        mapping(address => bool) isUserInPool;
    }

    // Mapping from poolId to Pool
    mapping(bytes32 => Pool) public pools;
    
    // Maps baseBet to the current Pool's poolId (for lookup)
    mapping(uint256 => bytes32) private _poolsByBaseBet;
    
    // Nonce for deterministic poolId generation
    uint256 private nonce = 1;

    mapping(address => uint256) public userBalances;
    mapping(bytes32 => string) public poolHistoryCIDs;
    mapping(address => string[]) public sessionHistoryCIDs;
    mapping(address => string) public userPremoveCIDs;
    mapping(address => bool) public isUserInAnyPool;

    event PoolCreated(bytes32 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(bytes32 indexed poolId, uint256 baseBet, address[] users, string[] premoveCIDs, string poolSalt);
    event DepositReceived(address indexed user, uint256 amount);
    event MatchHistoryCIDUpdated(bytes32 indexed poolId, string cid);
    event sessionHistoryCIDUpdated(address indexed user, string cid);
    event PremoveCIDUpdated(address indexed user, string cid);
    event SecurityCoefficientUpdated(uint256 newCoefficient);
    event PayoutProcessed(address indexed wallet, uint256 amount);

    address public owner;
    uint256 public securityCoefficient = 1000;

    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }

    constructor() {
        owner = msg.sender;
    }

    // --- Utility Functions ---
    function _generatePoolId() private returns (bytes32) {
        bytes32 newPoolId = keccak256(abi.encodePacked(nonce));
        nonce++;
        return newPoolId;
    }

    // --- Core Functions ---
    function createPool(uint256 baseBet, uint256 maxSize) private returns (bytes32) {
        require(_poolsByBaseBet[baseBet] == bytes32(0), "Pool already exists for this base bet");
        require(maxSize > 0, "Invalid maxSize");

        bytes32 newPoolId = _generatePoolId();
        Pool storage newPool = pools[newPoolId];
        newPool.poolId = newPoolId;
        newPool.baseBet = baseBet;
        newPool.maxSize = maxSize;
        newPool.poolSalt = ""; // Initialize to empty string

        // Link baseBet to this pool's poolId
        _poolsByBaseBet[baseBet] = newPoolId;

        emit PoolCreated(newPoolId, baseBet, maxSize);
        return newPoolId;
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        require(users.length > 0, "No users to add");
        
        bytes32 poolId = _poolsByBaseBet[baseBet];
        Pool storage pool;

        if (poolId == bytes32(0)) {
            poolId = createPool(baseBet, 5); // Default maxSize=5
            pool = pools[poolId];
        } else {
            pool = pools[poolId];
        }

        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address");
            require(!pool.isUserInPool[users[i]], "User already in pool");
            require(!isUserInAnyPool[users[i]], "User in another pool");

            pool.users.push(users[i]);
            pool.isUserInPool[users[i]] = true;
            isUserInAnyPool[users[i]] = true;

            if (pool.users.length == pool.maxSize) {
                _emitAndResetPool(pool);
            }
        }
    }

    function addSingleUserToPool(uint256 baseBet, address user) public {
        require(user != address(0), "Invalid user address");
        require(!isUserInAnyPool[user], "User in another pool");

        bytes32 poolId = _poolsByBaseBet[baseBet];
        Pool storage pool;

        if (poolId == bytes32(0)) {
            poolId = createPool(baseBet, 5); // Default maxSize=5
            pool = pools[poolId];
        } else {
            pool = pools[poolId];
        }

        pool.users.push(user);
        pool.isUserInPool[user] = true;
        isUserInAnyPool[user] = true;

        if (pool.users.length == pool.maxSize) {
            _emitAndResetPool(pool);
        }
    }

    // --- Pool Emission and Reset ---
    function _emitAndResetPool(Pool storage pool) internal {
        pool.poolSalt = _generateSalt(pool.users);
        string[] memory premoveCIDs = new string[](pool.users.length);
        for (uint256 i = 0; i < pool.users.length; i++) {
            premoveCIDs[i] = userPremoveCIDs[pool.users[i]];
        }

        emit PoolEmitted(pool.poolId, pool.baseBet, pool.users, premoveCIDs, pool.poolSalt);

        // Clear user tracking
        for (uint256 i = 0; i < pool.users.length; i++) {
            pool.isUserInPool[pool.users[i]] = false;
            isUserInAnyPool[pool.users[i]] = false;
            delete userPremoveCIDs[pool.users[i]];
        }

        // Reset pool but keep it in _poolsByBaseBet for reuse
        delete pool.users;
    }

    // --- Helper Functions ---
    function _generateSalt(address[] memory _users) internal pure returns (string memory) {
        bytes memory packedAddresses;
        for (uint256 i = 0; i < _users.length; i++) {
            packedAddresses = abi.encodePacked(packedAddresses, _users[i]);
        }
        bytes32 hash = keccak256(packedAddresses);
        return _toHexString(hash);
    }

    function _toHexString(bytes32 _bytes) internal pure returns (string memory) {
        bytes memory alphabet = "0123456789abcdef";
        bytes memory str = new bytes(64);
        for (uint256 i = 0; i < 32; i++) {
            str[i * 2] = alphabet[uint8(_bytes[i] >> 4)];
            str[i * 2 + 1] = alphabet[uint8(_bytes[i] & 0x0f)];
        }
        return string(str);
    }

    // --- External Functions ---
    function getContractBalance() external view returns (uint256) {
        return address(this).balance;
    }

    function setSecurityCoefficient(uint256 newCoefficient) external onlyOwner {
        require(newCoefficient > 0, "Coefficient must be greater than 0");
        securityCoefficient = newCoefficient;
        emit SecurityCoefficientUpdated(newCoefficient);
    }

    function triggerPoolEmittedEventForTesting(
        uint256 baseBet,
        address[] memory users,
        string[] memory premoveCIDs,
        string memory poolSalt
    ) external {
        bytes32 testPoolId = keccak256(abi.encodePacked(baseBet, block.timestamp, poolSalt));
        emit PoolEmitted(testPoolId, baseBet, users, premoveCIDs, poolSalt);
    }

    function getPoolUsers(uint256 baseBet) external view returns (address[] memory) {
        bytes32 poolId = _poolsByBaseBet[baseBet];
        return pools[poolId].users;
    }

    function storeMatchHistoryCID(bytes32 poolId, string memory cid) external {
        require(bytes(cid).length > 0, "CID cannot be empty");
        poolHistoryCIDs[poolId] = cid;
        emit MatchHistoryCIDUpdated(poolId, cid);
    }

    function storeSessionCID(address user, string memory cid) external {
        require(bytes(cid).length > 0, "CID cannot be empty");
        sessionHistoryCIDs[user].push(cid);
        emit sessionHistoryCIDUpdated(user, cid);
    }

    function getSessionHistoryCIDs(address user) external view returns (string[] memory) {
        return sessionHistoryCIDs[user];
    }

    function getMatchHistoryCID(bytes32 poolId) external view returns (string memory) {
        return poolHistoryCIDs[poolId];
    }

    function getPremoveCID(address user) external view returns (string memory) {
        return userPremoveCIDs[user];
    }

    function setPoolMaxSize(uint256 baseBet, uint256 newMaxSize) external onlyOwner {
        bytes32 poolId = _poolsByBaseBet[baseBet];
        require(poolId != bytes32(0), "Pool does not exist");
        pools[poolId].maxSize = newMaxSize;
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

    function isUserInPool(bytes32 poolId, address user) public view returns (bool) {
        return pools[poolId].isUserInPool[user];
    }

    function payOut(address payable user, uint256 amount) external onlyOwner {
        require(user != address(0), "Invalid user address");
        require(amount > 0, "Amount must be greater than 0");
        userBalances[user] = 0;
        isUserInAnyPool[user] = false;
        (bool success, ) = user.call{value: amount}("");
        require(success, "Payment failed");
    }

    function batchPayOut(address[] calldata wallets, uint256[] calldata amounts) external {
        require(wallets.length == amounts.length, "Mismatched arrays");
        for (uint256 i = 0; i < wallets.length; i++) {
            userBalances[wallets[i]] = 0;
            isUserInAnyPool[wallets[i]] = false;
            (bool success, ) = payable(wallets[i]).call{value: amounts[i]}("");
            require(success, "Payment failed");
            emit PayoutProcessed(wallets[i], amounts[i]);
        }
    }
}