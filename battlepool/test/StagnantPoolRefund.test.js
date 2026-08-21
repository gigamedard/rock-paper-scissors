// SPDX-License-Identifier: MIT
const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool - Stagnant Pool Refund", function () {
    let battlepool;
    let owner;
    let user1, user2, user3;
    let baseBet;
    let depositAmount;

    beforeEach(async function () {
        // Get signers
        [owner, user1, user2, user3] = await ethers.getSigners();

        // Deploy Battlepool contract
        const Battlepool = await ethers.getContractFactory("Battlepool");
        battlepool = await Battlepool.deploy();
        await battlepool.waitForDeployment();

        // Setup test parameters
        baseBet = ethers.parseEther("0.01"); // 0.01 ETH
        const securityCoefficient = await battlepool.securityCoefficient();
        depositAmount = baseBet * securityCoefficient;
    });

    describe("Stagnant Block Limit Configuration", function () {
        it("Should have default stagnant block limit of 100", async function () {
            expect(await battlepool.stagnantBlockLimit()).to.equal(100);
        });

        it("Should allow owner to change stagnant block limit", async function () {
            await battlepool.setStagnantBlockLimit(50);
            expect(await battlepool.stagnantBlockLimit()).to.equal(50);
        });

        it("Should emit StagnantBlockLimitUpdated event", async function () {
            await expect(battlepool.setStagnantBlockLimit(75))
                .to.emit(battlepool, "StagnantBlockLimitUpdated")
                .withArgs(75);
        });

        it("Should not allow non-owner to change limit", async function () {
            await expect(
                battlepool.connect(user1).setStagnantBlockLimit(50)
            ).to.be.revertedWith("Only owner can call this function");
        });
    });

    describe("Pool Activity Tracking", function () {
        it("Should initialize lastActivityBlock when pool is created", async function () {
            // Add first user to create pool
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(1);
        });

        it("Should update lastActivityBlock when user joins", async function () {
            // Add first user
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });

            // Mine some blocks
            await ethers.provider.send("hardhat_mine", ["0x5"]); // Mine 5 blocks

            // Add second user
            await battlepool.connect(user2).submitPremoveCID(baseBet, "QmTest2", { value: depositAmount });

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(2);
        });
    });

    describe("Stagnant Pool Refund", function () {
        beforeEach(async function () {
            // Set a lower limit for faster testing
            await battlepool.setStagnantBlockLimit(5);
        });

        it("Should fail if pool doesn't exist", async function () {
            const nonExistentBaseBet = ethers.parseEther("999");
            await expect(
                battlepool.checkAndRefundStagnantPool(nonExistentBaseBet)
            ).to.be.revertedWith("Pool does not exist");
        });

        it("Should fail if pool is empty", async function () {
            // Create pool first
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });
            const emptyBaseBet = ethers.parseEther("0.02");

            await expect(
                battlepool.checkAndRefundStagnantPool(emptyBaseBet)
            ).to.be.revertedWith("Pool does not exist");
        });

        it("Should fail if pool is not stagnant yet", async function () {
            // Add user to create pool
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });

            // Try to refund immediately
            await expect(
                battlepool.checkAndRefundStagnantPool(baseBet)
            ).to.be.revertedWith("Pool is not stagnant");
        });

        it("Should successfully refund stagnant pool", async function () {
            // Add users to pool
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });
            await battlepool.connect(user2).submitPremoveCID(baseBet, "QmTest2", { value: depositAmount });

            // Record balances before
            const user1BalanceBefore = await ethers.provider.getBalance(user1.address);
            const user2BalanceBefore = await ethers.provider.getBalance(user2.address);

            const user1ContractBalanceBefore = await battlepool.getUserBalance(user1.address);
            const user2ContractBalanceBefore = await battlepool.getUserBalance(user2.address);

            // Mine enough blocks to make pool stagnant
            await ethers.provider.send("hardhat_mine", ["0x6"]); // Mine 6 blocks

            // Trigger refund
            const tx = await battlepool.checkAndRefundStagnantPool(baseBet);
            const receipt = await tx.wait();

            // Verify event was emitted
            const event = receipt.logs.find(log => {
                try {
                    const parsed = battlepool.interface.parseLog(log);
                    return parsed && parsed.name === "PoolStagnantRefund";
                } catch (e) {
                    return false;
                }
            });

            expect(event).to.not.be.undefined;

            // Verify pool is empty
            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(0);

            // Verify users are no longer in any pool
            expect(await battlepool.isUserInAnyPool(user1.address)).to.be.false;
            expect(await battlepool.isUserInAnyPool(user2.address)).to.be.false;

            // Verify contract balances are zero
            expect(await battlepool.getUserBalance(user1.address)).to.equal(0);
            expect(await battlepool.getUserBalance(user2.address)).to.equal(0);

            // Verify ETH was refunded
            const user1BalanceAfter = await ethers.provider.getBalance(user1.address);
            const user2BalanceAfter = await ethers.provider.getBalance(user2.address);

            expect(user1BalanceAfter).to.be.gt(user1BalanceBefore);
            expect(user2BalanceAfter).to.be.gt(user2BalanceBefore);
        });

        it("Should emit PoolStagnantRefund event with correct data", async function () {
            // Add users
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });
            await battlepool.connect(user2).submitPremoveCID(baseBet, "QmTest2", { value: depositAmount });

            // Mine blocks
            await ethers.provider.send("hardhat_mine", ["0x6"]);

            // Trigger refund and check event
            await expect(battlepool.checkAndRefundStagnantPool(baseBet))
                .to.emit(battlepool, "PoolStagnantRefund");
        });

        it("Should handle multiple users in stagnant pool", async function () {
            // Add 3 users (assuming default max size is 5)
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });
            await battlepool.connect(user2).submitPremoveCID(baseBet, "QmTest2", { value: depositAmount });
            await battlepool.connect(user3).submitPremoveCID(baseBet, "QmTest3", { value: depositAmount });

            // Verify 3 users in pool
            let poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(3);

            // Mine blocks
            await ethers.provider.send("hardhat_mine", ["0x6"]);

            // Trigger refund
            await battlepool.checkAndRefundStagnantPool(baseBet);

            // Verify all users refunded
            poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(0);

            expect(await battlepool.isUserInAnyPool(user1.address)).to.be.false;
            expect(await battlepool.isUserInAnyPool(user2.address)).to.be.false;
            expect(await battlepool.isUserInAnyPool(user3.address)).to.be.false;
        });

        it("Should allow users to rejoin pool after refund", async function () {
            // Add user
            await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1", { value: depositAmount });

            // Make stagnant and refund
            await ethers.provider.send("hardhat_mine", ["0x6"]);
            await battlepool.checkAndRefundStagnantPool(baseBet);

            // User should be able to join again
            await expect(
                battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest1New", { value: depositAmount })
            ).to.not.be.reverted;

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers.length).to.equal(1);
            expect(poolUsers[0]).to.equal(user1.address);
        });
    });
});
