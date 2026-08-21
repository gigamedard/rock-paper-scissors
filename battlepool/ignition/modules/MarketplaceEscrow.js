const { buildModule } = require("@nomicfoundation/hardhat-ignition/modules");

// Ce module déploie le contrat MarketplaceEscrow.
// Il requiert deux paramètres lors du déploiement :
// 1. _tokenAddress: L'adresse du contrat SNTToken (IERC20).
// 2. _initialOwner: L'adresse du portefeuille qui sera propriétaire (owner) du contrat.

module.exports = buildModule("MarketplaceEscrowModule", (m) => {
  // 1. Définir les paramètres requis par le constructeur
  // Hardhat Ignition ira chercher ces valeurs lors de l'exécution du déploiement.
  const tokenAddress = m.getParameter("_tokenAddress");
  const initialOwner = m.getParameter("_initialOwner");

  // 2. Déployer le contrat en passant les paramètres au constructeur
  // "args" est un tableau qui liste les arguments dans le même ordre que le constructeur
  const marketplaceEscrow = m.contract("MarketplaceEscrow", {
    args: [tokenAddress, initialOwner]
  });

  // 3. Exporter l'instance du contrat déployé pour référence future
  return { marketplaceEscrow };
});