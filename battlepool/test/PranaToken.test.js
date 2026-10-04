const { expect } = require("chai");
const { ethers } = require("hardhat");

// Tests de PranaToken (ex-SNTToken renommé, décision utilisateur).
// CORRECTIF AUDIT : la constante placeholder INITIAL_OWNER_ADDRESS
// (0x8C3229EC…43D, compte de test Hardhat sans clé ni gas) est abrogée.
// Comportement initial :
// - nom "Prana", symbole "PRANA", 18 décimales
// - le DEPLOYER (signer #0) devient owner Ownable
// - supply initiale : 1 000 000 PRANA (18 décimales) mintée au deployer
describe("PranaToken", function () {
    const ONE_MILLION = ethers.parseEther("1000000");

    let prana;
    let deployer; // signer #0 = owner initial = bénéficiaire du mint
    let other;

    before(async function () {
        [deployer, other] = await ethers.getSigners();
    });

    beforeEach(async function () {
        const PranaToken = await ethers.getContractFactory("PranaToken", deployer);
        prana = await PranaToken.deploy();
        await prana.waitForDeployment();
    });

    it("expose le nom 'Prana' et le symbole 'PRANA'", async function () {
        expect(await prana.name()).to.equal("Prana");
        expect(await prana.symbol()).to.equal("PRANA");
    });

    it("a 18 décimales", async function () {
        expect(await prana.decimals()).to.equal(18n);
    });

    it("mint 1 000 000 PRANA au deployer et lui transfère l'ownership", async function () {
        expect(await prana.totalSupply()).to.equal(ONE_MILLION);
        expect(await prana.balanceOf(deployer.address)).to.equal(ONE_MILLION);
        expect(await prana.owner()).to.equal(deployer.address);
    });

    it("permet au owner (deployer) de transférer des tokens", async function () {
        const amount = ethers.parseEther("1000");
        await prana.connect(deployer).transfer(other.address, amount);
        expect(await prana.balanceOf(other.address)).to.equal(amount);
        expect(await prana.balanceOf(deployer.address)).to.equal(ONE_MILLION - amount);
    });

    it("refuse les transferts depuis une adresse sans solde", async function () {
        await expect(prana.connect(other).transfer(deployer.address, 1n))
            .to.be.revertedWithCustomError(prana, "ERC20InsufficientBalance");
    });

    it("respecte approve / allowance / transferFrom", async function () {
        const amount = ethers.parseEther("50");
        await prana.connect(deployer).approve(other.address, amount);
        expect(await prana.allowance(deployer.address, other.address)).to.equal(amount);

        await prana.connect(other).transferFrom(deployer.address, other.address, amount);
        expect(await prana.balanceOf(other.address)).to.equal(amount);
        expect(await prana.allowance(deployer.address, other.address)).to.equal(0n);
    });

    it("l'ownership est transférable par le owner initial (Ownable)", async function () {
        await prana.connect(deployer).transferOwnership(other.address);
        expect(await prana.owner()).to.equal(other.address);
        await expect(prana.connect(deployer).transferOwnership(other.address))
            .to.be.revertedWithCustomError(prana, "OwnableUnauthorizedAccount");
    });
});