const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool Contract", function () {
  let battlepool;
  let owner, user1, user2, user3, user4, user5, user6, user7;

  beforeEach(async function () {
    // Get signers
    [owner, user1, user2, user3, user4, user5, user6, user7] = await ethers.getSigners();

    // Deploy the Battlepool contract
    const Battlepool = await ethers.getContractFactory("Battlepool");
    battlepool = await Battlepool.deploy();
  });

  describe("Pool Creation", function () {
    it("Should create a new pool", async function () {
      const baseBet = ethers.parseEther("1");
      const maxSize = 5;

      await expect(battlepool.createPool(baseBet, maxSize))
        .to.emit(battlepool, "PoolCreated")
        .withArgs(1, baseBet, maxSize);

      const pool = await battlepool.pools(baseBet);
      expect(pool.poolId).to.equal(1);
      expect(pool.baseBet).to.equal(baseBet);
      expect(pool.maxSize).to.equal(maxSize);
    });

    it("Should revert if a pool with the same baseBet already exists", async function () {
      const baseBet = ethers.parseEther("1");
      const maxSize = 5;

      await battlepool.createPool(baseBet, maxSize);

      await expect(battlepool.createPool(baseBet, maxSize)).to.be.revertedWith(
        "Pool already exists"
      );
    });

    it("Should revert if maxSize is zero", async function () {
      const baseBet = ethers.parseEther("1");
      const maxSize = 0;

      await expect(battlepool.createPool(baseBet, maxSize)).to.be.reverted;
    });
  });

  describe("Adding Users to a Pool", function () {
    it("Should add users to a pool", async function () {
      const baseBet = ethers.parseEther("1");
      const users = [user1.address, user2.address, user3.address];

      await battlepool.createPool(baseBet, 5);
      await battlepool.addUsersToPool(baseBet, users);

      const poolUsers = await battlepool.getPoolUsers(baseBet);
      expect(poolUsers).to.deep.equal(users);
    });

    it("Should revert if a user is already in the pool", async function () {
      const baseBet = ethers.parseEther("1");
      const users = [user1.address, user1.address];

      await battlepool.createPool(baseBet, 5);

      await expect(battlepool.addUsersToPool(baseBet, users)).to.be.reverted;
    });
    it("Should emit PoolEmitted when the pool is full and reuse the pool", async function () {
      const baseBet = ethers.parseEther("1");
      const users = [user1.address, user2.address, user3.address, user4.address, user5.address];
    
      // Create the pool explicitly
      await battlepool.createPool(baseBet, 5);
    
      // Compute the expected poolSalt in the test
      const concatenatedAddresses = ethers.concat(users.map((user) => ethers.getBytes(user)));
      const hash = ethers.keccak256(concatenatedAddresses);
      const expectedSalt = ethers.dataSlice(hash, 0, 4); // First 4 bytes of the hash
    
      // Add users to the pool and expect the PoolEmitted event
      await expect(battlepool.addUsersToPool(baseBet, users))
        .to.emit(battlepool, "PoolEmitted")
        .withArgs(
          1, // poolId
          users, // users array
          users.map(() => ""), // premoveCIDs array (empty for this test)
          expectedSalt // poolSalt (first 4 bytes of the hash)
        );
    
      // Verify the pool is reset
      const poolUsers = await battlepool.getPoolUsers(baseBet);
      expect(poolUsers).to.have.lengthOf(0); // Pool should be empty after emission
    
      // Add more users to the same pool
      const additionalUsers = [user6.address, user7.address];
      await battlepool.addUsersToPool(baseBet, additionalUsers);
    
      // Verify the additional users were added
      const updatedPoolUsers = await battlepool.getPoolUsers(baseBet);
      expect(updatedPoolUsers).to.deep.equal(additionalUsers);
    });

  });

  describe("Submitting Premoves", function () {
    it("Should allow a user to submit premoves", async function () {
      const baseBet = ethers.parseEther("1");
      const cid = "QmExampleCID";

      await battlepool.createPool(baseBet, 5);
      await expect(battlepool.connect(user1).submitPremoveCID(baseBet, cid))
        .to.emit(battlepool, "PremoveCIDUpdated")
        .withArgs(user1.address, cid);

      const userPremoveCID = await battlepool.getPremoveCID(user1.address);
      expect(userPremoveCID).to.equal(cid);
    });

    it("Should revert if the CID is empty", async function () {
      const baseBet = ethers.parseEther("1");
      const cid = "";

      await battlepool.createPool(baseBet, 5);

      await expect(
        battlepool.connect(user1).submitPremoveCID(baseBet, cid)
      ).to.be.revertedWith("CID cannot be empty");
    });
  });

  describe("Deposits", function () {
    it("Should allow users to deposit Ether", async function () {
      const depositAmount = ethers.parseEther("1");

      await expect(battlepool.connect(user1).deposit({ value: depositAmount }))
        .to.emit(battlepool, "DepositReceived")
        .withArgs(user1.address, depositAmount);

      const userBalance = await battlepool.getUserBalance(user1.address);
      expect(userBalance).to.equal(depositAmount);
    });

    it("Should revert if the deposit amount is zero", async function () {
      await expect(battlepool.connect(user1).deposit({ value: 0 })).to.be.revertedWith(
        "Deposit must be greater than 0"
      );
    });
  });

  describe("Match History", function () {
    it("Should store match history CID", async function () {
      const poolId = 1;
      const cid = "QmMatchCID";

      await expect(battlepool.storeMatchHistoryCID(poolId, cid))
        .to.emit(battlepool, "MatchHistoryCIDUpdated")
        .withArgs(poolId, cid);

      const matchHistoryCID = await battlepool.getMatchHistoryCID(poolId);
      expect(matchHistoryCID).to.equal(cid);
    });

    it("Should revert if the CID is empty", async function () {
      const poolId = 1;
      const cid = "";

      await expect(battlepool.storeMatchHistoryCID(poolId, cid)).to.be.revertedWith(
        "CID cannot be empty"
      );
    });
  });
});