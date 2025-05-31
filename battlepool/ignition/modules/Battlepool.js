const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

module.exports = buildModule("BattlepoolModule", (m) => {
  // Deploy the Battlepool contract
  const battlepool = m.contract("Battlepool");

  return { battlepool };
});