const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

// Ce module n'a plus besoin de "m.getParameter" ou de "args"
module.exports = buildModule("SNTTokenModule", (m) => {
  
  // Le constructeur est vide, donc l'appel est simple
  const SNTToken = m.contract("SNTToken");

  return { SNTToken };
});