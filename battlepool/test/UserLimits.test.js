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
      // MIGRATION/base-bet (AGENTS.md §8) : le défaut du contrat est désormais
      // 100 ETH (constructor + full_deploy), aligné backend User.php.
      const defaultMaxBaseBet = ethers.parseEther("100");
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

    it("Should prevent a wallet that is neither owner nor payoutOperator from setting limits", async function () {
      const maxBaseBet = ethers.parseEther("0.1");
      const maxQ = ethers.parseUnits("3.0", 18);
      const minCooldown = 3600;
      const expiry = Math.floor(Date.now() / 1000) + 1000;

      // setUserLimits est réservé au payoutOperator OU au owner (modificateur
      // onlyPayoutOperator). Au déploiement de test, owner == payoutOperator ==
      // deployer ; user1 n'est ni l'un ni l'autre.
      await expect(
        battlepool.connect(user1).setUserLimits(user2.address, maxBaseBet, maxQ, minCooldown, expiry)
      ).to.be.revertedWith("Only payout operator or owner");
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
      const defaultMaxBaseBet = ethers.parseEther("100");
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
      const defaultMaxBaseBet = ethers.parseEther("100");
      expect(await battlepool.getUserMaxBaseBet(user1.address)).to.equal(defaultMaxBaseBet);
    });
  });

  describe("Validation Checks in Functions", function () {
    it("Should revert submitPremoveCID if base bet exceeds the user limit", async function () {
      // Le défaut du contrat est 100 ETH (AGENTS.md §8) : pour exercer la garde
      // "Base bet exceeds authorized limit" sans déposer 150 000 ETH, on assigne
      // une limite basse à user1 via setUserLimits (owner == deployer au test).
      const baseBet = ethers.parseEther("0.02");
      const cid = "QmTest";
      const securityCoef = await battlepool.securityCoefficient();
      const requiredBalance = baseBet * securityCoef;
      const depositAmount = (requiredBalance * 10500n) / 10000n;

      await battlepool.setUserLimits(
        user1.address,
        ethers.parseEther("0.01"), // maxBaseBet < baseBet
        ethers.parseUnits("2.0", 18),
        0,                          // minCooldown 0 -> pas de contrainte additionnelle
        0                           // expiry 0 -> jamais expirée
      );

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

    it("Should revert setUserNextSessionTime if the caller is neither owner nor payoutOperator", async function () {
      // Le setter est volontairement neutre (pas de vérification de cooldown
      // interne) : la garde "User is in cooldown" est exercée au JOIN de pool
      // (addSingleUserToPool / addUsersToPool). On teste ici la protection
      // d'accès du setter : un wallet tiers doit être rejeté.
      const currentBlock = await ethers.provider.getBlock("latest");
      const currentTimestamp = currentBlock.timestamp;
      const invalidNextTime = currentTimestamp + 3600; // Only 1 hour from now

      await expect(
        battlepool.connect(user1).setUserNextSessionTime(user1.address, invalidNextTime)
      ).to.be.revertedWith("Only payout operator or owner");
    });

    it("Should enforce minCooldown at pool join time after setUserNextSessionTime", async function () {
      // minCooldown par défaut = 86400s (24h). Le payoutOperator (deployer ici)
      // pose un nextTime à +1h : le submit/join de pool doit revert.
      const currentBlock = await ethers.provider.getBlock("latest");
      const invalidNextTime = currentBlock.timestamp + 3600;

      await battlepool.setUserNextSessionTime(user1.address, invalidNextTime);

      const baseBet = ethers.parseEther("0.01");
      const cid = "QmTest";
      const securityCoef = await battlepool.securityCoefficient();
      const requiredBalance = baseBet * securityCoef;
      const depositAmount = (requiredBalance * 10500n) / 10000n;

      await expect(
        battlepool.connect(user1).submitPremoveCID(baseBet, cid, { value: depositAmount })
      ).to.be.revertedWith("User is in cooldown");
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
