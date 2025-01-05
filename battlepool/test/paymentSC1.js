const { expect } = require("chai");
const { ethers } = require("hardhat");

describe("PaymentSC1 Contract (with receive and fallback)", function () {
  let paymentSC1;
  let owner, user1, user2;

  beforeEach(async function () {
    [owner, user1, user2] = await ethers.getSigners();
    const PaymentSC1 = await ethers.getContractFactory("PaymentSC1");
    paymentSC1 = await PaymentSC1.deploy();
  });

  it("Should allow a user to deposit Ether via deposit() and emit an event", async function () {
    const depositAmount = ethers.parseEther("1");
    console.log('depositAmount',depositAmount);
    console.log('value sent ',0x16345785d8a0000);
    await expect(paymentSC1.connect(user1).deposit({ value: depositAmount }))
      .to.emit(paymentSC1, "DepositReceived")
      .withArgs(user1.address, depositAmount);
  });

  it("Should allow a user to send Ether directly via receive() and emit an event", async function () {
    const depositAmount = ethers.parseEther("1");
    const contractAddressFromFunction = await paymentSC1.getContractAddress();
    await expect(user1.sendTransaction({ to: contractAddressFromFunction, value: depositAmount }))
      .to.emit(paymentSC1, "DepositReceived")
      .withArgs(user1.address, depositAmount);
  });

  it("Should handle Ether sent with invalid calldata via fallback() and emit an event", async function () {
    const depositAmount = ethers.parseEther("1");
    const calldata = "0x12345678";

    const contractAddressFromFunction = await paymentSC1.getContractAddress();

    await expect(user1.sendTransaction({
      to: contractAddressFromFunction,
      value: depositAmount,
      data: calldata,
    }))
      .to.emit(paymentSC1, "DepositReceived")
      .withArgs(user1.address, depositAmount);
  });

  it("Should revert transactions with zero Ether via deposit()", async function () {
    await expect(paymentSC1.connect(user1).deposit({ value: 0 })).to.be.revertedWith(
      "Deposit must be greater than 0"
    );
  });

  it("Should return zero balance for a user who has not deposited", async function () {
    const user2Balance = await paymentSC1.getUserBalance(user2.address);
    expect(user2Balance).to.equal(0);
  });

  it("Should handle multiple deposits from different users", async function () {
    const user1Deposit = ethers.parseEther("1");
    const user2Deposit = ethers.parseEther("2");

    // User1 deposits via deposit()
    await expect(paymentSC1.connect(user1).deposit({ value: user1Deposit }))
      .to.emit(paymentSC1, "DepositReceived")
      .withArgs(user1.address, user1Deposit);

    // User2 sends Ether directly via receive()
    const contractAddressFromFunction = await paymentSC1.getContractAddress();
    await expect(user2.sendTransaction({ to: contractAddressFromFunction, value: user2Deposit }))
      .to.emit(paymentSC1, "DepositReceived")
      .withArgs(user2.address, user2Deposit);
  });
});
