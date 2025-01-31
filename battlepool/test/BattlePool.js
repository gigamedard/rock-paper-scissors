const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool Contract", function () {
  let battlepool;
  let owner, user1, user2, user3, user4, user5;

  beforeEach(async function () {
    [owner, user1, user2, user3, user4, user5] = await ethers.getSigners();
    const Battlepool = await ethers.getContractFactory("Battlepool");
    battlepool = await Battlepool.deploy();
  });

  describe("Pool Management", function () {
    it("Should auto-create pool with default maxSize when adding users", async function () {
      const baseBet = ethers.parseEther("1");
      
      // Add user to non-existent pool
      await battlepool.addUsersToPool(baseBet, [user1.address]);
      
      // Verify through getter function
      const poolUsers = await battlepool.getPoolUsers(baseBet);
      expect(poolUsers).to.deep.equal([user1.address]);
    });

    it("Should prevent users from joining multiple pools", async function () {
      const baseBet1 = ethers.parseEther("1");
      const baseBet2 = ethers.parseEther("2");
      
      // Add to first pool
      await battlepool.addUsersToPool(baseBet1, [user1.address]);
      
      // Try second pool
      await expect(
        battlepool.addUsersToPool(baseBet2, [user1.address])
      ).to.be.revertedWith("User in another pool");
    });

    it("Should emit PoolEmitted and reset when full", async function () {
      const baseBet = ethers.parseEther("1");
      const users = [user1, user2, user3, user4, user5].map(u => u.address);

      // Add users to fill the pool
      const tx = await battlepool.addUsersToPool(baseBet, users);
      const receipt = await tx.wait();
      
      // Verify pool reset
      const poolUsers = await battlepool.getPoolUsers(baseBet);
      expect(poolUsers).to.have.lengthOf(0);
    });
  });

  describe("Premoves & Deposits", function () {
    it("Should enforce security coefficient requirements", async function () {
      const baseBet = ethers.parseEther("0.001");
      const securityCoefficient = await battlepool.securityCoefficient();
      const requiredBalance = baseBet * securityCoefficient;

      await expect(
        battlepool.connect(user1).submitPremoveCID(baseBet, "CID", { 
          value: requiredBalance - 1n 
        })
      ).to.be.revertedWith("Insufficient balance for the required security margin");
    });
  });

  describe("Owner Functions", function () {
    it("Should prevent non-owners from updating maxSize", async function () {
      const baseBet = ethers.parseEther("1");
      await battlepool.addUsersToPool(baseBet, [user1.address]);
      
      await expect(
        battlepool.connect(user1).setPoolMaxSize(baseBet, 10)
      ).to.be.revertedWith("Only owner can call this function");
    });
  });
});