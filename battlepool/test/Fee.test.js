const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("Battlepool - Fees and Payout", function () {
    let battlepool;
    let owner;
    let devWallet;
    let user1;
    let baseBet;
    let securityCoefficient;

    beforeEach(async function () {
        [owner, devWallet, user1] = await ethers.getSigners();
        const Battlepool = await ethers.getContractFactory("Battlepool");
        battlepool = await Battlepool.deploy();
        await battlepool.waitForDeployment();

        // Setup separate dev wallet to track fees easily
        await battlepool.setDevWallet(devWallet.address);

        baseBet = ethers.parseEther("0.01");
        securityCoefficient = await battlepool.securityCoefficient();
    });

    it("should deduct 5% fee on deposit and credit the remainder to user balance", async function () {
        const requiredBalance = baseBet * securityCoefficient;
        const depositAmount = (requiredBalance * 10500n) / 10000n;

        await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest", { value: depositAmount });

        // User should have exactly the required balance
        const userBalance = await battlepool.getUserBalance(user1.address);
        expect(userBalance).to.be.gte(requiredBalance); // Allow it to be equal or very slightly higher due to rounding

        // Dev balance should have the fee
        const devBalance = await battlepool.devBalance();
        const expectedFee = depositAmount - userBalance; // exactly what wasn't credited
        expect(devBalance).to.equal(expectedFee);
    });

    it("should correctly update fee basis points", async function () {
        await battlepool.setFeeBasisPoints(500); // 5%
        expect(await battlepool.feeBasisPoints()).to.equal(500n);

        const requiredBalance = baseBet * securityCoefficient;
        // With 5% fee, user needs to send * 10500 / 10000
        const depositAmount = (requiredBalance * 10500n) / 10000n;

        await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest2", { value: depositAmount });

        const devBalance = await battlepool.devBalance();
        const userBalance = await battlepool.getUserBalance(user1.address);
        expect(devBalance + userBalance).to.equal(depositAmount);
    });

    it("should allow withdrawal of dev fees", async function () {
        const requiredBalance = baseBet * securityCoefficient;
        const depositAmount = (requiredBalance * 10500n) / 10000n;

        await battlepool.connect(user1).submitPremoveCID(baseBet, "QmTest", { value: depositAmount });

        const devBalanceBefore = await ethers.provider.getBalance(devWallet.address);
        const feesToWithdraw = await battlepool.devBalance();
        expect(feesToWithdraw).to.be.gt(0n);

        // Withdraw from dev wallet
        const tx = await battlepool.connect(devWallet).withdrawDevFees();
        const receipt = await tx.wait();
        const gasUsed = receipt.gasUsed * tx.gasPrice;

        const devBalanceAfter = await ethers.provider.getBalance(devWallet.address);

        // Dev wallet should have gained (fees - gasUsed)
        expect(devBalanceAfter).to.equal(devBalanceBefore + feesToWithdraw - gasUsed);

        // devBalance on contract should be 0
        expect(await battlepool.devBalance()).to.equal(0n);
    });
});
