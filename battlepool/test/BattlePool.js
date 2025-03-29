const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool Contract", function () {
    let battlepool;
    let owner, user1, user2, user3, user4, user5, randomUser;

    beforeEach(async function () {
        [owner, user1, user2, user3, user4, user5, randomUser] = await ethers.getSigners();
        const Battlepool = await ethers.getContractFactory("Battlepool");
        battlepool = await Battlepool.deploy();
    });

    describe("Pool Management", function () {
        const baseBet = ethers.utils.parseEther("1");
        const defaultMaxSize = 3; // Assuming the default maxSize in createPool is 3

        it("Should auto-create pool with default maxSize when adding single user", async function () {
            await battlepool.addSingleUserToPool(baseBet, user1.address);
            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.deep.equal([user1.address]);

            // Verify the pool details
            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const pool = await battlepool.pools(generatedPoolId);
            expect(pool.baseBet).to.equal(baseBet);
            expect(pool.maxSize).to.equal(defaultMaxSize);
        });

        it("Should auto-create pool with default maxSize when adding multiple users", async function () {
            await battlepool.addUsersToPool(baseBet, [user1.address, user2.address]);
            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.deep.equal([user1.address, user2.address]);

            // Verify the pool details
            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const pool = await battlepool.pools(generatedPoolId);
            expect(pool.baseBet).to.equal(baseBet);
            expect(pool.maxSize).to.equal(defaultMaxSize);
        });

        it("Should prevent users from joining multiple pools", async function () {
            const baseBet1 = ethers.utils.parseEther("1");
            const baseBet2 = ethers.utils.parseEther("2");

            await battlepool.addSingleUserToPool(baseBet1, user1.address);

            await expect(
                battlepool.addSingleUserToPool(baseBet2, user1.address)
            ).to.be.revertedWith("User in another pool");

            await expect(
                battlepool.addUsersToPool(baseBet2, [user1.address])
            ).to.be.revertedWith("User in another pool");
        });

        it("Should prevent a user from joining the same pool twice", async function () {
            await battlepool.addSingleUserToPool(baseBet, user1.address);
            await expect(
                battlepool.addSingleUserToPool(baseBet, user1.address)
            ).to.be.revertedWith("User already in pool");

            await expect(
                battlepool.addUsersToPool(baseBet, [user1.address, user1.address])
            ).to.be.revertedWith("User already in pool");
        });

        it("Should emit PoolCreated event when a new pool is created", async function () {
            const maxSize = 5;
            const tx = await battlepool.addSingleUserToPool(baseBet, user1.address);
            const receipt = await tx.wait();

            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter

            expect(receipt.events.some(event =>
                event.event === "PoolCreated" &&
                event.args.poolId === generatedPoolId &&
                event.args.baseBet.eq(baseBet) &&
                event.args.maxSize.eq(defaultMaxSize)
            )).to.equal(true);
        });

        it("Should emit PoolEmitted and reset when full (single user add)", async function () {
            const maxSize = 3;
            for (let i = 0; i < maxSize; i++) {
                const user = [user1, user2, user3][i];
                await battlepool.addSingleUserToPool(baseBet, user.address);
            }

            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter

            const filter = battlepool.filters.PoolEmitted(generatedPoolId);
            const events = await battlepool.queryFilter(filter);

            expect(events).to.have.lengthOf(1);
            expect(events[0].args.poolId).to.equal(generatedPoolId);
            expect(events[0].args.baseBet).to.equal(baseBet);
            expect(events[0].args.users).to.deep.equal([user1.address, user2.address, user3.address]);

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.have.lengthOf(0);
        });

        it("Should emit PoolEmitted and reset when full (multiple user add)", async function () {
            const maxSize = 3;
            const usersToAdd = [user1.address, user2.address, user3.address];
            const tx = await battlepool.addUsersToPool(baseBet, usersToAdd);
            const receipt = await tx.wait();

            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter

            const filter = battlepool.filters.PoolEmitted(generatedPoolId);
            const events = await battlepool.queryFilter(filter);

            expect(events).to.have.lengthOf(1);
            expect(events[0].args.poolId).to.equal(generatedPoolId);
            expect(events[0].args.baseBet).to.equal(baseBet);
            expect(events[0].args.users).to.deep.equal(usersToAdd);

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.have.lengthOf(0);
        });

        it("Should create a new pool for the same baseBet after the previous one is full", async function () {
            const maxSize = 3;
            const initialUsers = [user1.address, user2.address, user3.address];
            await battlepool.addUsersToPool(baseBet, initialUsers);

            const nextUser = user4.address;
            await battlepool.addSingleUserToPool(baseBet, nextUser);

            const pool1Id = await battlepool.nextPoolIdForBaseBet(baseBet) - 2;
            const generatedPool1Id = await battlepool._generatePoolId(baseBet - 2); // Adjusted for counter
            const pool1Users = await battlepool.pools(generatedPool1Id).users;
            expect(pool1Users).to.deep.equal(initialUsers);

            const pool2Id = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPool2Id = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const pool2Users = await battlepool.getPoolUsers(baseBet); // This should return the users of the latest pool
            expect(pool2Users).to.deep.equal([nextUser]);
            const pool2Details = await battlepool.pools(generatedPool2Id);
            expect(pool2Details.maxSize).to.equal(defaultMaxSize);
        });
    });

    describe("Premoves & Deposits", function () {
        const baseBet = ethers.utils.parseEther("0.001");
        let securityCoefficient;
        let requiredBalance;

        beforeEach(async function () {
            securityCoefficient = await battlepool.securityCoefficient();
            requiredBalance = baseBet.mul(securityCoefficient);
        });

        it("Should enforce security coefficient requirements for submitPremoveCID", async function () {
            await expect(
                battlepool.connect(user1).submitPremoveCID(baseBet, "CID", {
                    value: requiredBalance.sub(1)
                })
            ).to.be.revertedWith("Insufficient balance for the required security margin");

            await expect(
                battlepool.connect(user1).submitPremoveCID(baseBet, "CID", {
                    value: requiredBalance
                })
            ).to.not.be.reverted;
        });

        it("Should store premove CID and add user to pool on submitPremoveCID", async function () {
            const cid = "testCID";
            await battlepool.connect(user1).submitPremoveCID(baseBet, cid, { value: requiredBalance });
            expect(await battlepool.userPremoveCIDs(user1.address)).to.equal(cid);

            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.include(user1.address);
        });

        it("Should emit PremoveCIDUpdated and DepositReceived events on submitPremoveCID", async function () {
            const cid = "anotherCID";
            const tx = await battlepool.connect(user1).submitPremoveCID(baseBet, cid, { value: requiredBalance });
            const receipt = await tx.wait();

            expect(receipt.events.some(event =>
                event.event === "PremoveCIDUpdated" &&
                event.args.user === user1.address &&
                event.args.cid === cid
            )).to.equal(true);

            expect(receipt.events.some(event =>
                event.event === "DepositReceived" &&
                event.args.user === user1.address &&
                event.args.amount.eq(requiredBalance)
            )).to.equal(true);
        });

        it("Should allow direct deposits and emit DepositReceived event", async function () {
            const depositAmount = ethers.utils.parseEther("0.5");
            const tx = await battlepool.connect(user2).deposit({ value: depositAmount });
            const receipt = await tx.wait();

            expect(await battlepool.userBalances(user2.address)).to.equal(depositAmount);
            expect(receipt.events.some(event =>
                event.event === "DepositReceived" &&
                event.args.user === user2.address &&
                event.args.amount.eq(depositAmount)
            )).to.equal(true);
        });

        it("Should allow receiving Ether and emit DepositReceived event (receive function)", async function () {
            const depositAmount = ethers.utils.parseEther("0.1");
            const tx = await user3.sendTransaction({
                to: battlepool.address,
                value: depositAmount
            });
            const receipt = await ethers.provider.getTransactionReceipt(tx.hash);

            expect(await battlepool.userBalances(user3.address)).to.equal(depositAmount);
            expect(receipt.logs.some(log => {
                const event = battlepool.interface.parseLog(log);
                return event.name === "DepositReceived" &&
                       event.args.user === user3.address &&
                       event.args.amount.eq(depositAmount);
            })).to.equal(true);
        });

        it("Should allow receiving Ether and emit DepositReceived event (fallback function)", async function () {
            const depositAmount = ethers.utils.parseEther("0.05");
            const tx = await user4.sendTransaction({
                to: battlepool.address,
                data: '0x', // Sending with no data will trigger fallback
                value: depositAmount
            });
            const receipt = await ethers.provider.getTransactionReceipt(tx.hash);

            expect(await battlepool.userBalances(user4.address)).to.equal(depositAmount);
            expect(receipt.logs.some(log => {
                const event = battlepool.interface.parseLog(log);
                return event.name === "DepositReceived" &&
                       event.args.user === user4.address &&
                       event.args.amount.eq(depositAmount);
            })).to.equal(true);
        });
    });

    describe("Owner Functions", function () {
        const baseBet = ethers.utils.parseEther("1");

        beforeEach(async function () {
            await battlepool.addSingleUserToPool(baseBet, user1.address);
        });

        it("Should allow owner to update maxSize", async function () {
            const newMaxSize = 10;
            await expect(battlepool.setPoolMaxSize(baseBet, newMaxSize)).to.not.be.reverted;
            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            expect(await battlepool.pools(generatedPoolId).maxSize).to.equal(newMaxSize);
        });

        it("Should prevent non-owners from updating maxSize", async function () {
            await expect(
                battlepool.connect(user1).setPoolMaxSize(baseBet, 10)
            ).to.be.revertedWith("Only owner can call this function");
        });

        it("Should allow owner to set security coefficient and emit event", async function () {
            const newCoefficient = 1500;
            const tx = await battlepool.setSecurityCoefficient(newCoefficient);
            const receipt = await tx.wait();
            expect(await battlepool.securityCoefficient()).to.equal(newCoefficient);
            expect(receipt.events.some(event =>
                event.event === "SecurityCoefficientUpdated" &&
                event.args.newCoefficient.eq(newCoefficient)
            )).to.equal(true);
        });

        it("Should prevent non-owners from setting security coefficient", async function () {
            await expect(
                battlepool.connect(user1).setSecurityCoefficient(1500)
            ).to.be.revertedWith("Only owner can call this function");
        });

        it("Should allow owner to payout to a user and emit event", async function () {
            const payoutAmount = ethers.utils.parseEther("0.2");
            await battlepool.connect(user1).deposit({ value: payoutAmount });
            const initialBalance = await ethers.provider.getBalance(user1.address);
            const tx = await battlepool.payOut(user1.address, payoutAmount);
            const receipt = await tx.wait();
            const finalBalance = await ethers.provider.getBalance(user1.address);

            expect(await battlepool.userBalances(user1.address)).to.equal(0);
            expect(await battlepool.isUserInAnyPool(user1.address)).to.equal(false);
            expect(finalBalance.sub(initialBalance)).to.be.closeTo(payoutAmount, ethers.utils.parseEther("0.001")); // Allow for gas
            expect(receipt.events.some(event =>
                event.event === "PayoutProcessed" &&
                event.args.wallet === user1.address &&
                event.args.amount.eq(payoutAmount)
            )).to.equal(true);
        });

        it("Should prevent non-owners from paying out", async function () {
            const payoutAmount = ethers.utils.parseEther("0.1");
            await expect(
                battlepool.connect(user1).payOut(user2.address, payoutAmount)
            ).to.be.revertedWith("Only owner can call this function");
        });

        it("Should allow owner to batch payout to multiple users and emit events", async function () {
            const payoutAmount1 = ethers.utils.parseEther("0.1");
            const payoutAmount2 = ethers.utils.parseEther("0.05");
            await battlepool.connect(user1).deposit({ value: payoutAmount1 });
            await battlepool.connect(user2).deposit({ value: payoutAmount2 });

            const initialBalance1 = await ethers.provider.getBalance(user1.address);
            const initialBalance2 = await ethers.provider.getBalance(user2.address);

            const wallets = [user1.address, user2.address];
            const amounts = [payoutAmount1, payoutAmount2];
            const tx = await battlepool.batchPayOut(wallets, amounts);
            const receipt = await tx.wait();

            const finalBalance1 = await ethers.provider.getBalance(user1.address);
            const finalBalance2 = await ethers.provider.getBalance(user2.address);

            expect(await battlepool.userBalances(user1.address)).to.equal(0);
            expect(await battlepool.userBalances(user2.address)).to.equal(0);
            expect(await battlepool.isUserInAnyPool(user1.address)).to.equal(false);
            expect(await battlepool.isUserInAnyPool(user2.address)).to.equal(false);
            expect(finalBalance1.sub(initialBalance1)).to.be.closeTo(payoutAmount1, ethers.utils.parseEther("0.001"));
            expect(finalBalance2.sub(initialBalance2)).to.be.closeTo(payoutAmount2, ethers.utils.parseEther("0.001"));

            expect(receipt.events.filter((event) =>
                event.event === "PayoutProcessed" &&
                ((event.args.wallet === user1.address && event.args.amount.eq(payoutAmount1)) ||
                 (event.args.wallet === user2.address && event.args.amount.eq(payoutAmount2)))
            )).to.have.lengthOf(2);
        });

        it("Should prevent non-owners from batch paying out", async function () {
            const wallets = [user1.address, user2.address];
            const amounts = [ethers.utils.parseEther("0.1"), ethers.utils.parseEther("0.05")];
            await expect(
                battlepool.connect(user1).batchPayOut(wallets, amounts)
            ).to.be.revertedWith("Only owner can call this function");
        });
    });

    describe("Salt Generation", function () {
        it("Should correctly generate a salt for a pool with multiple users", async function () {
            const baseBet = ethers.utils.parseEther("1");
            const maxSize = 3;

            await battlepool.addSingleUserToPool(baseBet, user1.address);
            await battlepool.addSingleUserToPool(baseBet, user2.address);
            await battlepool.addSingleUserToPool(baseBet, user3.address);

            const poolId = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const pool = await battlepool.pools(generatedPoolId);

            const concatenatedAddresses = ethers.utils.solidityPack(
                ["address", "address", "address"],
                [user1.address, user2.address, user3.address]
            );
            const expectedSalt = ethers.utils.keccak256(concatenatedAddresses);

            const filter = battlepool.filters.PoolEmitted(generatedPoolId);
            const events = await battlepool.queryFilter(filter);

            expect(events).to.have.lengthOf(1);
            expect(events[0].args.poolSalt).to.equal(expectedSalt);
        });

        it("Should generate a different salt if the order of users changes", async function () {
            const baseBet = ethers.utils.parseEther("1");
            const maxSize = 2;

            await battlepool.addSingleUserToPool(baseBet, user1.address);
            await battlepool.addSingleUserToPool(baseBet, user2.address);
            const poolId1 = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId1 = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const filter1 = battlepool.filters.PoolEmitted(generatedPoolId1);
            const events1 = await battlepool.queryFilter(filter1);
            const salt1 = events1[0].args.poolSalt;

            // Reset pool tracking for user2
            await battlepool.isUserInAnyPool(user2.address); // Just to read the state
            await battlepool.connect(owner).payOut(user2.address, await battlepool.userBalances(user2.address));

            await battlepool.addSingleUserToPool(baseBet, user2.address);
            await battlepool.addSingleUserToPool(baseBet, user1.address);
            const poolId2 = await battlepool.nextPoolIdForBaseBet(baseBet) - 1;
            const generatedPoolId2 = await battlepool._generatePoolId(baseBet - 1); // Adjusted for counter
            const filter2 = battlepool.filters.PoolEmitted(generatedPoolId2);
            const events2 = await battlepool.queryFilter(filter2);
            const salt2 = events2[0].args.poolSalt;

            expect(salt1).to.not.equal(salt2);
        });
    });

    describe("Getter Functions", function () {
        const baseBet = ethers.utils.parseEther("1");
        const cid = "testCID";

        beforeEach(async function () {
            await battlepool.connect(user1).deposit({ value: ethers.utils.parseEther("0.5") });
            await battlepool.connect(user2).submitPremoveCID(baseBet, cid, { value: baseBet.mul(await battlepool.securityCoefficient()) });
            await battlepool.storeMatchHistoryCID(await battlepool._generatePoolId(baseBet - 1), cid); // Adjusted for counter
            await battlepool.storeSessionCID(user3.address, cid);
        });

        it("Should return the contract balance", async function () {
            const contractBalance = await ethers.provider.getBalance(battlepool.address);
            expect(await battlepool.getContractBalance()).to.equal(contractBalance);
        });

        it("Should return the user balance", async function () {
            expect(await battlepool.getUserBalance(user1.address)).to.equal(ethers.utils.parseEther("0.5"));
            expect(await battlepool.getUserBalance(user2.address)).to.equal(baseBet.mul(await battlepool.securityCoefficient()));
            expect(await battlepool.getUserBalance(randomUser.address)).to.equal(0);
        });

        it("Should return the users in a pool", async function () {
            const poolUsers = await battlepool.getPoolUsers(baseBet);
            expect(poolUsers).to.deep.equal([user2.address]);
        });

        it("Should return the match history CID for a pool", async function () {
            expect(await battlepool.getMatchHistoryCID(await battlepool._generatePoolId(baseBet - 1))).to.equal(cid); // Adjusted for counter
        });

        it("Should return the session history CIDs for a user", async function () {
            expect(await battlepool.getSessionHistoryCIDs(user3.address)).to.deep.equal([cid]);
        });

        it("Should return the premove CID for a user", async function () {
            expect(await battlepool.getPremoveCID(user2.address)).to.equal(cid);
            expect(await battlepool.getPremoveCID(randomUser.address)).to.equal("");
        });

        it("Should return the contract address", async function () {
            expect(await battlepool.getContractAddress()).to.equal(battlepool.address);
        });

        it("Should return if a user is in a specific pool", async function () {
            expect(await battlepool.isUserInPool(await battlepool._generatePoolId(baseBet - 1), user2.address)).to.equal(true); // Adjusted for counter
            expect(await battlepool.isUserInPool(await battlepool._generatePoolId(baseBet - 1), user1.address)).to.equal(false); // Adjusted for counter
            // Test for a non-existent pool
            const nonExistentBaseBet = ethers.utils.parseEther("3");
            expect(await battlepool.isUserInPool(await battlepool._generatePoolId(nonExistentBaseBet - 1), user1.address)).to.equal(false); // Adjusted for counter
        });
    });
});