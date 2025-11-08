require("@nomicfoundation/hardhat-toolbox");

/** @type import('hardhat/config').HardhatUserConfig */
module.exports = {
  solidity: "0.8.28",
  networks: {
    hardhat: {
      accounts: {
        count: 101, // Change this number to get more accounts
      },
      mining: {
        auto: true,
        interval: 2000 // Mine un bloc toutes les 2 secondes
      },
    },
  },
};


