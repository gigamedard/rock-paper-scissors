const { expect } = require("chai");
const { ethers } = require("hardhat");
const hre = require("hardhat");

// Tests de WAVAX : wrapper canonique d'AVAX natif (pattern WETH9), 1:1, sans owner.
// Sur Pingala (chainId 99999), WAVAX servira d'asset/mise ERC-20 ; le wrap se fait
// à la source par deposit() (beta) — le pont réel viendra en phase ultérieure.
describe("WAVAX", function () {
    let wavax;
    let user;
    let userAddr;

    const DEPOSIT = ethers.parseEther("1.5");

    beforeEach(async function () {
        [, user] = await ethers.getSigners();
        userAddr = user.address;
        const WAVAX = await ethers.getContractFactory("WAVAX");
        wavax = await WAVAX.deploy();
        await wavax.waitForDeployment();
    });

    it("expose le nom 'Wrapped AVAX' et le symbole 'WAVAX'", async function () {
        expect(await wavax.name()).to.equal("Wrapped AVAX");
        expect(await wavax.symbol()).to.equal("WAVAX");
        expect(await wavax.decimals()).to.equal(18n);
    });

    it("deposit() minte 1:1 des WAVAX et crédite l'AVAX natif au contrat", async function () {
        const contractBefore = await ethers.provider.getBalance(await wavax.getAddress());

        await expect(wavax.connect(user).deposit({ value: DEPOSIT }))
            .to.emit(wavax, "Deposit")
            .withArgs(userAddr, DEPOSIT);

        expect(await wavax.balanceOf(userAddr)).to.equal(DEPOSIT);
        expect(await wavax.totalSupply()).to.equal(DEPOSIT);
        expect(await ethers.provider.getBalance(await wavax.getAddress())).to.equal(contractBefore + DEPOSIT);
    });

    it("un envoi direct d'AVAX (receive) minte des WAVAX comme deposit()", async function () {
        const wavaxAddr = await wavax.getAddress();

        await expect(user.sendTransaction({ to: wavaxAddr, value: DEPOSIT }))
            .to.emit(wavax, "Deposit")
            .withArgs(userAddr, DEPOSIT);

        expect(await wavax.balanceOf(userAddr)).to.equal(DEPOSIT);
        expect(await wavax.totalSupply()).to.equal(DEPOSIT);
    });

    it("withdraw() burn les WAVAX et rembourse l'AVAX natif (hors gas)", async function () {
        await wavax.connect(user).deposit({ value: DEPOSIT });

        const balanceBefore = await ethers.provider.getBalance(userAddr);
        const tx = await wavax.connect(user).withdraw(DEPOSIT);
        const receipt = await tx.wait();
        const gasUsed = receipt.gasUsed * receipt.gasPrice;

        expect(await wavax.balanceOf(userAddr)).to.equal(0n);
        expect(await wavax.totalSupply()).to.equal(0n);
        expect(await ethers.provider.getBalance(userAddr)).to.equal(balanceBefore + DEPOSIT - gasUsed);

        await expect(tx).to.emit(wavax, "Withdrawal").withArgs(userAddr, DEPOSIT);
    });

    it("redeem() est un alias de withdraw()", async function () {
        await wavax.connect(user).deposit({ value: DEPOSIT });

        const balanceBefore = await ethers.provider.getBalance(userAddr);
        const tx = await wavax.connect(user).redeem(DEPOSIT);
        const receipt = await tx.wait();
        const gasUsed = receipt.gasUsed * receipt.gasPrice;

        expect(await wavax.balanceOf(userAddr)).to.equal(0n);
        expect(await ethers.provider.getBalance(userAddr)).to.equal(balanceBefore + DEPOSIT - gasUsed);
        await expect(tx).to.emit(wavax, "Withdrawal").withArgs(userAddr, DEPOSIT);
    });

    it("refuse un withdraw supérieur au solde ERC20", async function () {
        await wavax.connect(user).deposit({ value: DEPOSIT });
        const tooMuch = DEPOSIT + 1n;
        await expect(wavax.connect(user).withdraw(tooMuch))
            .to.be.revertedWith("WAVAX: insufficient balance");
    });

    it("refuse un withdraw qui échoue à livrer l'AVAX (wallet rejetant)", async function () {
        await wavax.connect(user).deposit({ value: DEPOSIT });

        // Déployer une cible qui refuse nativement tout AVAX reçu (fallback revert),
        // lui faire wrapper 0.5 AVAX en WAVAX, puis appeler withdraw() depuis son
        // adresse (impersonation Hardhat, réservée au réseau de test in-process).
        const Rejecter = await ethers.getContractFactory("WavaxRejectReceiver");
        const rejecter = await Rejecter.deploy(await wavax.getAddress());
        await rejecter.waitForDeployment();
        const rejecterAddr = await rejecter.getAddress();

        await rejecter.connect(user).wrap({ value: ethers.parseEther("0.5") });
        expect(await wavax.balanceOf(rejecterAddr)).to.equal(ethers.parseEther("0.5"));

        await hre.network.provider.request({
            method: "hardhat_impersonateAccount",
            params: [rejecterAddr],
        });
        await hre.network.provider.send("hardhat_setBalance", [
            rejecterAddr,
            "0x8AC7230489E80000", // 10 ETH pour le gas
        ]);
        const impersonated = await ethers.getSigner(rejecterAddr);
        await expect(wavax.connect(impersonated).withdraw(ethers.parseEther("0.5")))
            .to.be.revertedWith("WAVAX: AVAX transfer failed");
        // CEI : la transaction a rollback -> le solde WAVAX du rejecter est intact.
        expect(await wavax.balanceOf(rejecterAddr)).to.equal(ethers.parseEther("0.5"));
        await hre.network.provider.request({
            method: "hardhat_stopImpersonatingAccount",
            params: [rejecterAddr],
        });
    });

    it("garantit l'invariant 1:1 : totalSupply == balance AVAX native du contrat", async function () {
        const wavaxAddr = await wavax.getAddress();
        const [, u2] = await ethers.getSigners();

        await wavax.connect(user).deposit({ value: DEPOSIT });
        await u2.sendTransaction({ to: wavaxAddr, value: ethers.parseEther("0.25") });

        expect(await wavax.totalSupply()).to.equal(await ethers.provider.getBalance(wavaxAddr));

        await wavax.connect(user).withdraw(ethers.parseEther("1"));
        expect(await wavax.totalSupply()).to.equal(await ethers.provider.getBalance(wavaxAddr));
    });
});