// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;
import "@openzeppelin/contracts/utils/cryptography/ECDSA.sol";
import "@openzeppelin/contracts/utils/cryptography/MessageHashUtils.sol";
import "@openzeppelin/contracts/utils/ReentrancyGuard.sol";
import "@openzeppelin/contracts/token/ERC20/IERC20.sol";

/**
 * BattlepoolV2 — version ERC-20 du Battlepool.
 *
 * Identique à Battlepool (pools, rôles, timelock, limites, securityCoefficient,
 * frais, claimAndExit par signature), SAUF la devise :
 *  - dépôt en ERC-20 (`deposit(uint256)` via transferFrom),
 *  - payouts/claims en ERC-20 (`transfer`),
 *  - `userBalances` = solde en jetons,
 *  - `updateUserBalance` borné par `token.balanceOf(address(this))`.
 *
 * Le jeton ciblé est l'AVAXe (AVAX bridgé Fuji→Pingala). Gas = TST natif (inchangé).
 */
contract BattlepoolV2 is ReentrancyGuard {
    using ECDSA for bytes32;

    // Devise ERC-20 des mises/payouts (AVAXe).
    address public token;

    struct Pool {
        uint256 poolId;
        uint256 baseBet;
        uint256 maxSize;
        address[] users;
        string poolSalt;
        mapping(address => bool) isUserInPool;
        uint256 lastActivityBlock;
        bool isLockedForValidation;
    }

    uint256 public nextPoolId = 1;
    mapping(uint256 => Pool) internal pools;
    mapping(uint256 => address[]) public poolQueues;
    mapping(address => uint256) public userBalances;
    mapping(uint256 => string) public poolHistoryCIDs;
    mapping(address => string[]) public sessionHistoryCIDs;
    mapping(address => string) public userPremoveCIDs;
    mapping(address => bool) public isUserInAnyPool;
    mapping(address => uint256) public nextSessionAllowedTime;
    mapping(address => uint256) public nonces;

    event PoolCreated(uint256 indexed poolId, uint256 baseBet, uint256 maxSize);
    event PoolEmitted(uint256 indexed poolId, uint256 baseBet, address[] users, string[] premoveCIDs, string poolSalt, uint256[] balances);
    event DepositReceived(address indexed user, uint256 amount);
    event MatchHistoryCIDUpdated(uint256 indexed poolId, string cid);
    event sessionHistoryCIDUpdated(address indexed user, string cid);
    event PremoveCIDUpdated(address indexed user, string cid);
    event SecurityCoefficientUpdated(uint256 newCoefficient);
    event SignerUpdated(address newSigner);
    event PayoutOperatorUpdated(address newOperator);
    event PayoutProcessed(address indexed wallet, uint256 amount);
    event UserBalanceUpdated(address indexed user, uint256 oldBalance, uint256 newBalance);
    event DefaultPoolMaxSizeChanged(uint256 newSize);
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
    event TokenUpdated(address newToken);

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
    address public signer;
    address public payoutOperator;
    uint256 public securityCoefficient = 1000;
    uint256 public defaultPoolMaxSize;
    uint256 public constant MAX_POOL_SIZE = 16; // Plafond dur (Algo 6 : max 16 joueurs)
    uint256 public stagnantBlockLimit = 100;
    uint256 public feeBasisPoints = 500; // 5.0%
    uint256 public constant MAX_FEE_BASIS_POINTS = 1000; // max 10%
    uint256 public devBalance;
    address payable public devWallet;

    uint256 public constant TIMELOCK_DELAY = 2 days;
    struct TimelockOperation {
        bytes32 hash;
        uint256 readyAt;
    }
    mapping(bytes32 => TimelockOperation) public pendingTimelock;

    event TimelockQueued(bytes32 indexed opHash, uint256 readyAt);
    event TimelockExecuted(bytes32 indexed opHash);
    event TimelockCancelled(bytes32 indexed opHash);
    event OwnershipTransferred(address indexed previousOwner, address indexed newOwner);

    bool private rolesInitialized;
    uint256 public deployBlock;

    modifier onlyOwner() {
        require(msg.sender == owner, "Only owner can call this function");
        _;
    }
    modifier onlyPayoutOperator() {
        require(msg.sender == payoutOperator || msg.sender == owner, "Only payout operator or owner");
        _;
    }

    function _timelockHash(bytes4 selector, address target, bytes memory data) internal pure returns (bytes32) {
        return keccak256(abi.encodePacked(selector, target, data));
    }
    function _queueTimelock(bytes4 selector, address target, bytes memory data) internal returns (bytes32) {
        bytes32 opHash = _timelockHash(selector, target, data);
        require(pendingTimelock[opHash].readyAt == 0, "Operation already queued");
        pendingTimelock[opHash] = TimelockOperation({ hash: opHash, readyAt: block.timestamp + TIMELOCK_DELAY });
        emit TimelockQueued(opHash, block.timestamp + TIMELOCK_DELAY);
        return opHash;
    }
    modifier afterTimelock(bytes4 selector, address target, bytes memory data) {
        bytes32 opHash = _timelockHash(selector, target, data);
        require(pendingTimelock[opHash].readyAt > 0, "Operation not queued");
        require(block.timestamp >= pendingTimelock[opHash].readyAt, "Timelock delay not elapsed");
        _;
        delete pendingTimelock[opHash];
        emit TimelockExecuted(opHash);
    }
    function cancelTimelock(bytes32 opHash) external onlyOwner {
        require(pendingTimelock[opHash].readyAt > 0, "Operation not queued");
        delete pendingTimelock[opHash];
        emit TimelockCancelled(opHash);
    }

    constructor(address _token) {
        require(_token != address(0), "Invalid token");
        token = _token;
        owner = msg.sender;
        signer = msg.sender;
        payoutOperator = msg.sender;
        devWallet = payable(msg.sender);
        defaultPoolMaxSize = 13;
        defaultMaxBaseBet = 100 ether;
        defaultMaxQ = 2.0 * 1e18;
        defaultMinCooldown = 86400;
        deployBlock = block.number;
    }

    function initializeRoles(
        address _owner,
        address _signer,
        address _payoutOperator,
        address payable _devWallet
    ) external onlyOwner {
        require(!rolesInitialized, "Roles already initialized");
        require(block.number <= deployBlock + 100, "Initialization window expired");
        if (_signer != address(0)) signer = _signer;
        if (_payoutOperator != address(0)) payoutOperator = _payoutOperator;
        if (_devWallet != address(0)) devWallet = _devWallet;
        if (_owner != address(0)) {
            address previousOwner = owner;
            owner = _owner;
            emit OwnershipTransferred(previousOwner, _owner);
        }
        rolesInitialized = true;
    }

    function queueTransferOwnership(address newOwner) external onlyOwner {
        require(newOwner != address(0), "Invalid owner address");
        _queueTimelock(this.executeTransferOwnership.selector, address(this), abi.encode(newOwner));
    }
    function executeTransferOwnership(address newOwner) external
        afterTimelock(this.executeTransferOwnership.selector, address(this), abi.encode(newOwner)) onlyOwner
    {
        address previousOwner = owner;
        owner = newOwner;
        emit OwnershipTransferred(previousOwner, newOwner);
    }

    function queueSetSigner(address newSigner) external onlyOwner {
        require(newSigner != address(0), "Invalid signer address");
        _queueTimelock(this.executeSetSigner.selector, address(this), abi.encode(newSigner));
    }
    function executeSetSigner(address newSigner) external
        afterTimelock(this.executeSetSigner.selector, address(this), abi.encode(newSigner)) onlyOwner
    {
        signer = newSigner;
        emit SignerUpdated(newSigner);
    }

    function queueSetPayoutOperator(address newOperator) external onlyOwner {
        require(newOperator != address(0), "Invalid payout operator address");
        _queueTimelock(this.executeSetPayoutOperator.selector, address(this), abi.encode(newOperator));
    }
    function executeSetPayoutOperator(address newOperator) external
        afterTimelock(this.executeSetPayoutOperator.selector, address(this), abi.encode(newOperator)) onlyOwner
    {
        payoutOperator = newOperator;
        emit PayoutOperatorUpdated(newOperator);
    }

    /// @dev Change le jeton (ERC-20) de mise. TIMELOCK : opération sensible.
    function queueSetToken(address newToken) external onlyOwner {
        require(newToken != address(0), "Invalid token address");
        _queueTimelock(this.executeSetToken.selector, address(this), abi.encode(newToken));
    }
    function executeSetToken(address newToken) external
        afterTimelock(this.executeSetToken.selector, address(this), abi.encode(newToken)) onlyOwner
    {
        token = newToken;
        emit TokenUpdated(newToken);
    }

    function setDefaultPoolMaxSize(uint256 newSize) external onlyOwner {
        require(newSize >= 2 && newSize <= MAX_POOL_SIZE, "Invalid pool size");
        defaultPoolMaxSize = newSize;
        emit DefaultPoolMaxSizeChanged(newSize);
    }
    function getContractBalance() external view returns (uint256) {
        return IERC20(token).balanceOf(address(this));
    }
    function setSecurityCoefficient(uint256 newCoefficient) external onlyOwner {
        require(newCoefficient > 0, "Coefficient must be greater than 0");
        securityCoefficient = newCoefficient;
        emit SecurityCoefficientUpdated(newCoefficient);
    }
    function setFeeBasisPoints(uint256 newFee) external onlyOwner {
        require(newFee <= MAX_FEE_BASIS_POINTS, "Fee cannot exceed 10%");
        feeBasisPoints = newFee;
        emit FeeBasisPointsUpdated(newFee);
    }
    function queueSetDevWallet(address payable newWallet) external onlyOwner {
        require(newWallet != address(0), "Invalid wallet address");
        _queueTimelock(this.executeSetDevWallet.selector, address(this), abi.encode(newWallet));
    }
    function executeSetDevWallet(address payable newWallet) external
        afterTimelock(this.executeSetDevWallet.selector, address(this), abi.encode(newWallet)) onlyOwner
    {
        devWallet = newWallet;
        emit DevWalletUpdated(newWallet);
    }
    function withdrawDevFees() external nonReentrant {
        require(msg.sender == devWallet, "Only dev wallet can withdraw");
        uint256 amount = devBalance;
        require(amount > 0, "No fees to withdraw");
        devBalance = 0;
        require(IERC20(token).transfer(devWallet, amount), "Withdrawal failed");
        emit DevFeesWithdrawn(devWallet, amount);
    }

    function triggerPoolEmittedEventForTesting(
        uint256 poolId,
        uint256 baseBet,
        address[] memory users,
        string[] memory premoveCIDs,
        string memory poolSalt,
        uint256[] memory balances
    ) external onlyOwner {
        require(block.chainid == 99999, "Test-only function: not available on this network");
        emit PoolEmitted(poolId, baseBet, users, premoveCIDs, poolSalt, balances);
    }

    function createPool(uint256 baseBet, uint256 maxSize) private {
        require(pools[baseBet].poolId == 0, "Pool already exists");
        require(maxSize > 0 && maxSize <= MAX_POOL_SIZE, "Invalid maxSize");
        Pool storage newPool = pools[baseBet];
        newPool.poolId = nextPoolId;
        newPool.baseBet = baseBet;
        newPool.maxSize = maxSize;
        newPool.poolSalt = "";
        newPool.lastActivityBlock = block.number;
        newPool.isLockedForValidation = false;
        nextPoolId++;
        emit PoolCreated(newPool.poolId, baseBet, maxSize);
    }

    function addUsersToPool(uint256 baseBet, address[] memory users) external {
        require(users.length > 0, "No users to add");
        Pool storage pool = pools[baseBet];
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize);
        } else if (pool.users.length == 0 && !pool.isLockedForValidation) {
            pool.poolId = nextPoolId;
            nextPoolId++;
        }
        for (uint256 i = 0; i < users.length; i++) {
            require(users[i] != address(0), "Invalid user address");
            require(baseBet <= getUserMaxBaseBet(users[i]), "Base bet exceeds authorized limit");
            require(!pool.isUserInPool[users[i]], "User already in pool");
            require(!isUserInAnyPool[users[i]], "User in another pool");
            require(block.timestamp >= nextSessionAllowedTime[users[i]], "User is in cooldown");
            if (pool.isLockedForValidation) {
                poolQueues[baseBet].push(users[i]);
                isUserInAnyPool[users[i]] = true;
            } else {
                pool.users.push(users[i]);
                pool.isUserInPool[users[i]] = true;
                isUserInAnyPool[users[i]] = true;
                pool.lastActivityBlock = block.number;
                if (pool.users.length == pool.maxSize) {
                    _emitPoolValidation(pool);
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
            createPool(baseBet, defaultPoolMaxSize);
        } else if (pool.users.length == 0) {
            pool.poolId = nextPoolId;
            nextPoolId++;
        }
        pool.users.push(user);
        pool.isUserInPool[user] = true;
        isUserInAnyPool[user] = true;
        pool.lastActivityBlock = block.number;
        if (pool.users.length == pool.maxSize) {
            _emitPoolValidation(pool);
        }
    }

    /// @dev Dépôt + enregistrement des premoves, en ERC-20 (amount de jetons).
    function submitPremoveCID(uint256 baseBet, string memory cid, uint256 amount) external {
        require(bytes(cid).length > 0, "CID cannot be empty");
        require(baseBet > 0, "Base bet must be greater than 0");
        require(baseBet <= getUserMaxBaseBet(msg.sender), "Base bet exceeds authorized limit");
        require(amount > 0, "Amount must be greater than 0");

        uint256 requiredBalance = baseBet * securityCoefficient;
        uint256 depositAmount = (amount * 10000) / (10000 + feeBasisPoints);
        require(depositAmount >= requiredBalance, "Insufficient deposit after fees for the required security margin");

        require(IERC20(token).transferFrom(msg.sender, address(this), amount), "Transfer failed");
        _processDeposit(msg.sender, amount);
        userPremoveCIDs[msg.sender] = cid;
        emit PremoveCIDUpdated(msg.sender, cid);
        addSingleUserToPool(baseBet, msg.sender);
    }

    function getPoolInfo(uint256 baseBet) external view returns (uint256 poolId, uint256 maxSize, uint256 userCount, bool isLocked) {
        Pool storage pool = pools[baseBet];
        return (pool.poolId, pool.maxSize, pool.users.length, pool.isLockedForValidation);
    }
    function getPoolUsers(uint256 baseBet) external view returns (address[] memory) {
        return pools[baseBet].users;
    }
    function storeMatchHistoryCID(uint256 poolId, string memory cid) external onlyPayoutOperator {
        require(bytes(cid).length > 0, "CID cannot be empty");
        poolHistoryCIDs[poolId] = cid;
        emit MatchHistoryCIDUpdated(poolId, cid);
    }
    function storeSessionCID(address user, string memory cid) external onlyPayoutOperator {
        require(bytes(cid).length > 0, "CID cannot be empty");
        sessionHistoryCIDs[user].push(cid);
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
        require(newMaxSize >= 2 && newMaxSize <= MAX_POOL_SIZE, "Invalid pool size");
        pool.maxSize = newMaxSize;
    }

    function validatePool(uint256 baseBet) external onlyPayoutOperator {
        Pool storage pool = pools[baseBet];
        require(pool.isLockedForValidation, "Pool is not locked for validation");
        for (uint256 i = 0; i < pool.users.length; i++) {
            pool.isUserInPool[pool.users[i]] = false;
            isUserInAnyPool[pool.users[i]] = false;
            delete userPremoveCIDs[pool.users[i]];
        }
        delete pool.users;
        pool.isLockedForValidation = false;
        _processQueue(baseBet);
    }

    function invalidatePoolUsers(uint256 baseBet, address[] calldata invalidUsers) external onlyPayoutOperator {
        Pool storage pool = pools[baseBet];
        require(pool.isLockedForValidation, "Pool is not locked for validation");
        for (uint256 i = 0; i < invalidUsers.length; i++) {
            address invalidUser = invalidUsers[i];
            for (uint256 j = 0; j < pool.users.length; j++) {
                if (pool.users[j] == invalidUser) {
                    pool.users[j] = pool.users[pool.users.length - 1];
                    pool.users.pop();
                    break;
                }
            }
            pool.isUserInPool[invalidUser] = false;
            isUserInAnyPool[invalidUser] = false;
            delete userPremoveCIDs[invalidUser];
        }
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
        if (pool.poolId == 0) {
            createPool(baseBet, defaultPoolMaxSize);
        } else if (pool.users.length == 0) {
            pool.poolId = nextPoolId;
            nextPoolId++;
        }
        for (uint256 i = 0; i < queue.length; i++) {
            if (pool.isLockedForValidation) break;
            address user = queue[i];
            pool.users.push(user);
            pool.isUserInPool[user] = true;
            pool.lastActivityBlock = block.number;
            processedCount++;
            if (pool.users.length == pool.maxSize) {
                _emitPoolValidation(pool);
            }
        }
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
        address[] memory users = pool.users;
        pool.poolSalt = _generateSalt(users);
        string[] memory premoveCIDs = new string[](users.length);
        uint256[] memory balances = new uint256[](users.length);
        for (uint256 i = 0; i < users.length; i++) {
            premoveCIDs[i] = userPremoveCIDs[users[i]];
            balances[i] = userBalances[users[i]];
        }
        emit PoolEmitted(pool.poolId, pool.baseBet, users, premoveCIDs, pool.poolSalt, balances);
        pool.isLockedForValidation = true;
    }

    function _generateSalt(address[] memory _users) internal pure returns (string memory) {
        bytes memory packedAddresses;
        for (uint256 i = 0; i < _users.length; i++) {
            packedAddresses = abi.encodePacked(packedAddresses, abi.encodePacked(_users[i]));
        }
        return _toHexString(keccak256(packedAddresses));
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

    /// @dev Dépôt simple en ERC-20.
    function deposit(uint256 amount) external {
        require(amount > 0, "Deposit must be greater than 0");
        require(IERC20(token).transferFrom(msg.sender, address(this), amount), "Transfer failed");
        _processDeposit(msg.sender, amount);
    }

    function _processDeposit(address user, uint256 amount) private {
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

    function updateUserBalance(address user, uint256 newBalance) external onlyPayoutOperator {
        require(user != address(0), "Invalid user address");
        require(newBalance <= IERC20(token).balanceOf(address(this)), "New balance exceeds contract token balance");
        uint256 oldBalance = userBalances[user];
        userBalances[user] = newBalance;
        emit UserBalanceUpdated(user, oldBalance, newBalance);
    }

    function payOut(address user, uint256 amount) external onlyPayoutOperator {
        require(user != address(0), "Invalid user address");
        require(amount > 0, "Amount must be greater than 0");
        require(amount <= userBalances[user], "Amount exceeds user balance");
        userBalances[user] = 0;
        isUserInAnyPool[user] = false;
        delete userPremoveCIDs[user];
        require(IERC20(token).transfer(user, amount), "Payment failed");
        emit PayoutProcessed(user, amount);
        emit PlayerClaimed(user, amount, 0);
    }

    function claimAndExit(uint256 amount, uint256 deadline, bytes memory signature) external nonReentrant {
        require(amount > 0, "Amount must be greater than 0");
        require(amount <= userBalances[msg.sender], "Amount exceeds user balance");
        require(block.timestamp <= deadline, "Signature expired");

        bytes32 messageHash = keccak256(abi.encodePacked(msg.sender, amount, nonces[msg.sender], address(this), block.chainid, deadline));
        bytes32 ethSignedMessageHash = MessageHashUtils.toEthSignedMessageHash(messageHash);
        address recovered = ethSignedMessageHash.recover(signature);
        require(recovered == signer, "Invalid signer signature");

        uint256 nonceUsed = nonces[msg.sender];
        nonces[msg.sender]++;
        userBalances[msg.sender] = 0;
        isUserInAnyPool[msg.sender] = false;
        delete userPremoveCIDs[msg.sender];

        require(IERC20(token).transfer(msg.sender, amount), "Claim payment failed");
        emit PlayerClaimed(msg.sender, amount, nonceUsed);
    }

    function batchPayOut(address[] calldata wallets, uint256[] calldata amounts) external onlyPayoutOperator nonReentrant {
        require(wallets.length == amounts.length, "Mismatched arrays");
        for (uint i = 0; i < wallets.length; i++) {
            require(amounts[i] <= userBalances[wallets[i]], "Amount exceeds user balance");
            userBalances[wallets[i]] = 0;
            isUserInAnyPool[wallets[i]] = false;
            require(IERC20(token).transfer(wallets[i], amounts[i]), "Payment failed");
            emit PayoutProcessed(wallets[i], amounts[i]);
        }
    }

    function setStagnantBlockLimit(uint256 _limit) external onlyOwner {
        stagnantBlockLimit = _limit;
        emit StagnantBlockLimitUpdated(_limit);
    }
    function setUserNextSessionTime(address user, uint256 nextTime) external onlyPayoutOperator {
        nextSessionAllowedTime[user] = nextTime;
        emit NextSessionTimeUpdated(user, nextTime);
    }

    function checkAndRefundStagnantPool(uint256 baseBet) external nonReentrant {
        Pool storage pool = pools[baseBet];
        require(pool.poolId != 0, "Pool does not exist");
        require(pool.users.length > 0, "Pool is empty");
        require(block.number > pool.lastActivityBlock + stagnantBlockLimit, "Pool is not stagnant");
        uint256 refundedCount = pool.users.length;
        address[] memory usersToRefund = pool.users;
        for (uint256 i = 0; i < usersToRefund.length; i++) {
            address user = usersToRefund[i];
            pool.isUserInPool[user] = false;
            isUserInAnyPool[user] = false;
            delete userPremoveCIDs[user];
            uint256 amount = userBalances[user];
            if (amount > 0) {
                userBalances[user] = 0;
                require(IERC20(token).transfer(user, amount), "Refund failed");
            }
        }
        delete pool.users;
        pool.lastActivityBlock = block.number;
        emit PoolStagnantRefund(pool.poolId, refundedCount, block.timestamp);
    }

    function getUserMaxBaseBet(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.maxBaseBet == 0) return defaultMaxBaseBet;
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) return defaultMaxBaseBet;
            } else {
                if (block.timestamp > limit.expiry) return defaultMaxBaseBet;
            }
        }
        return limit.maxBaseBet;
    }
    function getUserMaxQ(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.maxQ == 0) return defaultMaxQ;
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) return defaultMaxQ;
            } else {
                if (block.timestamp > limit.expiry) return defaultMaxQ;
            }
        }
        return limit.maxQ;
    }
    function getUserMinCooldown(address user) public view returns (uint256) {
        UserLimit memory limit = userLimits[user];
        if (limit.minCooldown == 0) return defaultMinCooldown;
        if (limit.expiry > 0) {
            if (limit.expiry < 1e9) {
                if (block.number > limit.expiry) return defaultMinCooldown;
            } else {
                if (block.timestamp > limit.expiry) return defaultMinCooldown;
            }
        }
        return limit.minCooldown;
    }
    function setUserLimits(address user, uint256 maxBaseBet, uint256 maxQ, uint256 minCooldown, uint256 expiry) external onlyPayoutOperator {
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
