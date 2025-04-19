const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool", function () {
  let battlepool;
  let owner, user1, user2, user3, user4, user5;
  const securityCoefficient = 1000; // Default value from contract

  beforeEach(async function () {
    [owner, user1, user2, user3, user4, user5] = await ethers.getSigners();
    const Battlepool = await ethers.getContractFactory("Battlepool");
    battlepool = await Battlepool.deploy();
    await battlepool.deployed();
  });

  describe("Pool Creation & Management", function () {
    it("Should create a pool with unique bytes32 poolId", async function () {
      const baseBet = 1;
      const maxSize = 3;

      // Add users to trigger pool creation
      await battlepool.addUsersToPool(baseBet, [user1.address, user2.address]);

      // Verify pool exists in _poolsByBaseBet
      const pool = await battlepool.getPoolUsers(baseBet);
      expect(pool).to.deep.equal([user1.address, user2.address]);

      // Add third user to fill the pool
      await battlepool.addSingleUserToPool(baseBet, user3.address);

      // Check PoolEmitted event was emitted
      const emittedEvent = await battlepool.queryFilter(
        battlepool.filters.PoolEmitted(),
        "latest"
      );
      expect(emittedEvent.length).to.equal(1);

      const eventArgs = emittedEvent[0].args;
      expect(eventArgs.poolId).to.not.equal(ethers.constants.HashZero);
      expect(eventArgs.poolSalt).to.not.be.empty;
      expect(eventArgs.users).to.deep.equal([
        user1.address,
        user2.address,
        user3.address,
      ]);
    });

    it("Should reset pool and allow reuse after emission", async function () {
      const baseBet = 1;
      const maxSize = 3;

      // First pool creation and emission
      await battlepool.addUsersToPool(baseBet, [
        user1.address,
        user2.address,
        user3.address,
      ]);

      // Check pool is emitted and users are cleared
      const poolAfterEmit = await battlepool.getPoolUsers(baseBet);
      expect(poolAfterEmit.length).to.equal(0);

      // Add new users to the same baseBet pool (should create a new pool)
      await battlepool.addUsersToPool(baseBet, [user4.address, user5.address]);

      const newPoolUsers = await battlepool.getPoolUsers(baseBet);
      expect(newPoolUsers).to.deep.equal([user4.address, user5.address]);
    });

    it("Should handle submitPremoveCID and user addition", async function () {
      const baseBet = 1;
      const cid = "QmTestCID";
      const requiredBalance = baseBet * securityCoefficient;

      // Submit CID and add user
      await user1.sendTransaction({
        to: battlepool.address,
        value: requiredBalance,
      });
      await battlepool
        .connect(user1)
        .submitPremoveCID(baseBet, cid, { value: requiredBalance });

      // Check user is in the pool
      const pool = await battlepool.getPoolUsers(baseBet);
      expect(pool).to.include(user1.address);

      // Verify CID is stored
      const storedCid = await battlepool.getPremoveCID(user1.address);
      expect(storedCid).to.equal(cid);
    });

    it("Should prevent duplicate users in the same pool", async function () {
      const baseBet = 1;

      // Add user1 twice
      await battlepool.addSingleUserToPool(baseBet, user1.address);
      await expect(
        battlepool.addSingleUserToPool(baseBet, user1.address)
      ).to.be.revertedWith("User already in pool");
    });

    it("Should handle batch payout correctly", async function () {
      const baseBet = 1;
      const users = [user1.address, user2.address];
      const amounts = [100, 200];

      // Fund users (simulated)
      // ... (Assuming users have balances)

      await battlepool.batchPayOut(users, amounts);

      for (let i = 0; i < users.length; i++) {
        const userBal = await battlepool.getUserBalance(users[i]);
        expect(userBal).to.equal(0);
      }
    });
  });

  describe("Security & Edge Cases", function () {
    it("Should revert on insufficient deposit for submitPremoveCID", async function () {
      const baseBet = 1;
      const cid = "QmTestCID";
      const requiredBalance = baseBet * securityCoefficient;
      const insufficientAmount = requiredBalance - 1;

      await expect(
        battlepool
          .connect(user1)
          .submitPremoveCID(baseBet, cid, { value: insufficientAmount })
      ).to.be.revertedWith("Insufficient balance for the required security margin");
    });

    it("Should prevent adding zero address", async function () {
      const baseBet = 1;
      await expect(
        battlepool.addUsersToPool(baseBet, [ethers.constants.AddressZero])
      ).to.be.revertedWith("Invalid user address");
    });
  });

  describe("Event Emission & State Updates", function () {
    it("Should emit MatchHistoryCIDUpdated correctly", async function () {
      const testPoolId = ethers.utils.id("test");
      const cid = "QmTestCID";

      await battlepool.storeMatchHistoryCID(testPoolId, cid);

      const event = await battlepool.queryFilter(
        battlepool.filters.MatchHistoryCIDUpdated(testPoolId),
        "latest"
      );
      expect(event.length).to.equal(1);
      expect(event[0].args.cid).to.equal(cid);
    });
  });
});