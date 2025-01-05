// SPDX-License-Identifier: MIT
pragma solidity ^0.8.23;

contract Battlepool {
    struct Pool {
        uint256 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        bytes32 poolSalt;
    }

    struct MatchHistory {
        address user1;
        address user2;
        uint256 result; // 0: draw, 1: user1 wins, 2: user2 wins
        uint256 user1Gain;
        uint256 user2Gain;
        uint256 premoveIndex;
        uint256 premoveHistoryIndex;
    }

    uint256 public nextPoolId = 1;
    mapping(uint256 => Pool) public pools; // Maps baseBet to Pool
    mapping(address => uint256) public userBalances;
    mapping(uint256 => MatchHistory[]) public poolHistories; // Maps poolId to match history
    mapping(address => string[]) public userPremoves; // Maps user address to premoves

    event PoolCreated(uint256 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(uint256 indexed poolId, address[] users, bytes32 poolSalt);
    event DepositReceived(address indexed user, uint256 amount);
    event MatchResult(uint256 indexed poolId, address indexed user1, address indexed user2, uint256 result);

    constructor() {
        //console.log("Contract deployed at:", address(this));
    }

    function createPool(uint256 baseBet, uint256 maxSize) public {
        require(pools[baseBet].poolId == 0, "Pool already exists");
        require(maxSize > 0, "Invalid maxSize");

        Pool storage newPool = pools[baseBet];
        newPool.poolId = nextPoolId++;
        newPool.baseBet = baseBet;
        newPool.maxSize = maxSize;
        newPool.poolSalt = 0; // Initialize salt to 0 (will be generated when pool is full)

        emit PoolCreated(newPool.poolId, baseBet, maxSize);
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            createPool(baseBet, 5); // Default maxSize set to 5 if pool does not exist
        }
        require(users.length > 0, "No users to add");

        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address"); // Validate user address
            pool.users.push(users[i]);
            if (pool.users.length == pool.maxSize) {
                _emitAndResetPool(pool);
                break;
            }
        }
    }

    function addSingleUserToPool(uint256 baseBet, address user) public {
        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            createPool(baseBet, 5); // Default maxSize set to 5 if pool does not exist
        }
        require(user != address(0), "Invalid user address");

        pool.users.push(user);
        if (pool.users.length == pool.maxSize) {
            _emitAndResetPool(pool);
        }
    }

    function submitPremove(uint256 baseBet, string[] memory premoves) external payable {

        userPremoves[msg.sender] = premoves; // Store the premoves for the user
        addSingleUserToPool(baseBet, msg.sender);
    }

    function getPoolUsers(uint256 baseBet) external view returns (address[] memory) {
        Pool storage pool = pools[baseBet];
        return pool.users;
    }
    function storeMatchHistory(
        uint256 poolId,
        address user1,
        address user2,
        uint256 result,
        uint256 user1Gain,
        uint256 user2Gain,
        uint256 user1PremoveIndex,
        uint256 user2PremoveIndex,
        uint256 user1PremoveHistoryIndex,
        uint256 user2PremoveHistoryIndex
    ) external {
            MatchHistory memory matchHistory = MatchHistory({
            user1: user1,
            user2: user2,
            result: result,
            user1Gain: user1Gain,
            user2Gain: user2Gain,
            user1PremoveIndex: user1PremoveIndex,
            user2PremoveIndex: user2PremoveIndex,
            user1PremoveHistoryIndex: user1PremoveHistoryIndex,
            user2PremoveHistoryIndex: user2PremoveHistoryIndex
        });
        poolHistories[poolId].push(matchHistory);
        emit MatchResult(poolId, user1, user2, result);
    }

    function getMatchHistory(uint256 poolId) external view returns (MatchHistory[] memory) {
        return poolHistories[poolId];
    }

    function _emitAndResetPool(Pool storage pool) internal {
        // Generate the salt only when the pool is full
        pool.poolSalt = _generateSalt(pool.users);
        
        emit PoolEmitted(pool.poolId, pool.users, pool.poolSalt);
        delete pool.users;
    }

    function _generateSalt(address[] memory users) internal view returns (bytes32) {
        bytes32 hash = keccak256(abi.encodePacked(users, block.timestamp, blockhash(block.number - 1)));
        return hash;
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
}