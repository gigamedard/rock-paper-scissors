
require("@nomicfoundation/hardhat-toolbox");
require("@nomicfoundation/hardhat-ignition-ethers");
// Ensure your configuration variables are set before executing the script
const { vars } = require("hardhat/config");

const TEST_NET_PRIVAT_KEY = vars.get("TEST_NET_PRIVAT_KEY");
const ALCHEMY_SEPOLIA_URL = vars.get("ALCHEMY_SEPOLIA_URL");

module.exports = {
  solidity: "0.8.27",
  networks: {
    sepolia: {
      url: ALCHEMY_SEPOLIA_URL,
      accounts: [TEST_NET_PRIVAT_KEY]
    }
  }
};

