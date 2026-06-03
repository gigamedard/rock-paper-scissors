const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool - User Limits", function () {
  let battlepool;
  let owner, user1, user2;

  beforeEach(async function () {
    [owner, user1, user2] = await ethers.getSigners();
    const Battlepool = await ethers.getContractFactory("Battlepool");
    battlepool = await Battlepool.deploy();
    await battlepool.waitForDeployment();
  });

  describe("Defaults", function () {
    it("Should return correct defaults for users with no card limits", async function () {
      const defaultMaxBaseBet = ethers.parseEther("0.01");
      const defaultMaxQ = ethers.parseUnits("2.0", 18);
      const defaultMinCooldown = 86400; // 24 hours

      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(defaultMaxBaseBet);
      expect(await battlepool.getUserMaxQ(user1.address)).to.equal(defaultMaxQ);
      expect(await battlepool.getUserMinCooldown(user1.address)).to.equal(defaultMinCooldown);
    });
  });

  describe("Setting and Querying Limits", function () {
    it("Should allow owner to set limits and reflect them", async function () {
      const maxBaseBet = ethers.parseEther("0.1");
      const maxQ = ethers.parseUnits("3.0", 18);
      const minCooldown = 3600; // 1 hour
      const expiry = Math.floor(Date.now() / 1000) + 1000; // Future timestamp

      await battlepool.setUserLimits(user1.address, maxBaseBet, maxQ, minCooldown, expiry);

      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(maxBaseBet);
      expect(await battlepool.getUserMaxQ(user1.address)).to.equal(maxQ);
      expect(await battlepool.getUserMinCooldown(user1.address)).to.equal(minCooldown);
    });

    it("Should prevent non-owner from setting limits", async function () {
      const maxBaseBet = ethers.parseEther("0.1");
      const maxQ = ethers.parseUnits("3.0", 18);
      const minCooldown = 3600;
      const expiry = Math.floor(Date.now() / 1000) + 1000;

      await expect(
        battlepool.connect(user1).setUserLimits(user2.address, maxBaseBet, maxQ, minCooldown, expiry)
      ).to.be.revertedWith("Only owner can call this function");
    });
  });

  describe("Block-based Expiration (< 1e9)", function () {
    it("Should enforce block-based limits when active, and fall back to default when expired", async function () {
      const maxBaseBet = ethers.parseEther("0.05");
      const maxQ = ethers.parseUnits("2.5", 18);
      const minCooldown = 7200; // 2 hours
      
      const currentBlock = await ethers.provider.getBlockNumber();
      const expiryBlock = currentBlock + 5; // Valid for 5 blocks

      await battlepool.setUserLimits(user1.address, maxBaseBet, maxQ, minCooldown, expiryBlock);

      // Verify active
      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(maxBaseBet);

      // Mine blocks to expire
      for (let i = 0; i < 5; i++) {
        await ethers.provider.send("evm_mine", []);
      }

      // Verify expired, falls back to default
      const defaultMaxBaseBet = ethers.parseEther("0.01");
      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(defaultMaxBaseBet);
    });
  });

  describe("Timestamp-based Expiration (>= 1e9)", function () {
    it("Should enforce timestamp-based limits when active, and fall back to default when expired", async function () {
      const maxBaseBet = ethers.parseEther("0.05");
      const maxQ = ethers.parseUnits("2.5", 18);
      const minCooldown = 7200;
      
      const currentBlock = await ethers.provider.getBlock("latest");
      const currentTimestamp = currentBlock.timestamp;
      const expiryTime = currentTimestamp + 1000; // Valid for 1000 seconds

      await battlepool.setUserLimits(user1.address, maxBaseBet, maxQ, minCooldown, expiryTime);

      // Verify active
      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(maxBaseBet);

      // Advance time to expire
      await ethers.provider.send("evm_increaseTime", [1001]);
      await ethers.provider.send("evm_mine", []);

      // Verify expired, falls back to default
      const defaultMaxBaseBet = ethers.parseEther("0.01");
      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(defaultMaxBaseBet);
    });
  });

  describe("Validation Checks in Functions", function () {
    it("Should revert submitPremoveCID if base bet exceeds limit", async function () {
      const baseBet = ethers.parseEther("0.02"); // Exceeds default of 0.01
      const cid = "QmTest";
      const securityCoef = await battlepool.securityCoefficient();
      const requiredBalance = baseBet * securityCoef;
      const depositAmount = (requiredBalance * 10500n) / 10000n;

      await expect(
        battlepool.connect(user1).submitPremoveCID(baseBet, cid, { value: depositAmount })
      ).to.be.revertedWith("Base bet exceeds authorized limit");
    });

    it("Should allow submitPremoveCID if base bet is within limit", async function () {
      const baseBet = ethers.parseEther("0.01"); // Within default limit
      const cid = "QmTest";
      const securityCoef = await battlepool.securityCoefficient();
      const requiredBalance = baseBet * securityCoef;
      const depositAmount = (requiredBalance * 10500n) / 10000n;

      await expect(
        battlepool.connect(user1).submitPremoveCID(baseBet, cid, { value: depositAmount })
      ).to.not.be.revertedWith("Base bet exceeds authorized limit");
    });

    it("Should revert setUserNextSessionTime if cooldown is shorter than minCooldown", async function () {
      // User1 has default minCooldown = 86400 (24h)
      const currentBlock = await ethers.provider.getBlock("latest");
      const currentTimestamp = currentBlock.timestamp;
      const invalidNextTime = currentTimestamp + 3600; // Only 1 hour from now

      await expect(
        battlepool.setUserNextSessionTime(user1.address, invalidNextTime)
      ).to.be.revertedWith("Cooldown is too short");
    });

    it("Should allow setUserNextSessionTime if nextTime is 0 (clearing cooldown)", async function () {
      await expect(
        battlepool.setUserNextSessionTime(user1.address, 0)
      ).to.not.be.reverted;
    });

    it("Should allow setUserNextSessionTime if nextTime satisfies minCooldown", async function () {
      const currentBlock = await ethers.provider.getBlock("latest");
      const currentTimestamp = currentBlock.timestamp;
      const validNextTime = currentTimestamp + 86400 + 10; // 24h from now with buffer

      await expect(
        battlepool.setUserNextSessionTime(user1.address, validNextTime)
      ).to.not.be.reverted;
    });
  });
});
