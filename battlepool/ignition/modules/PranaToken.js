const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

// RENOMMAGE : ex-SNTTokenModule. Le contrat déployé est désormais PranaToken
// ("Prana" / "PRANA"). Le constructeur est sans paramètre (initialOwner et le
// mint de 1M sont codés en dur dans le contrat, comportement conservé).
module.exports = buildModule("PranaTokenModule", (m) => {

  const PranaToken = m.contract("PranaToken");

  return { PranaToken };
});