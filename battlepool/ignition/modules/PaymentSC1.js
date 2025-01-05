const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

module.exports = buildModule("PaymentSC1Module", (m) => {
  // Deploy the PaymentSC1 contract
  const paymentSC1 = m.contract("PaymentSC1");

  return { paymentSC1 };
});
