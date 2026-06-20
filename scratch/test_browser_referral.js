import puppeteer from 'puppeteer';

(async () => {
    console.log("🚀 Lancement du test End-to-End Headless (Bypass Wallet)...");
    
    const browser = await puppeteer.launch({ headless: "new" });
    const page = await browser.newPage();

    // Injecter un mock pour Web3 avant le chargement de la page
    await page.evaluateOnNewDocument(() => {
        window.ethereum = {
            isMetaMask: true,
            request: async (request) => {
                console.log("[Mock Wallet] Reçu requête:", request.method);
                if (request.method === 'eth_requestAccounts') {
                    // Simule le compte Hardhat 0
                    return ['0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266'];
                }
                if (request.method === 'personal_sign') {
                    // Signature "en dur" pour bypasser MetaMask. 
                    // Dans un vrai test, on utiliserait ethers.js pour signer le bon message,
                    // mais si on a test_sanctum.html ou api_mock.php, on peut bypass.
                    // Vu qu'on teste le frontend, on va renvoyer une signature bidon.
                    // SAUF QUE l'API backend vérifie la signature !
                    return '0x...'; 
                }
                return null;
            }
        };
    });

    console.log("🌐 Navigation vers le lien de parrainage...");
    // On visite avec le code de parrainage
    await page.goto('http://127.0.0.1:8001/?ref=ANTIGRAVITY_TEST', { waitUntil: 'networkidle2' });

    console.log("🔎 Vérification du stockage local pour le parrainage...");
    const pendingReferral = await page.evaluate(() => localStorage.getItem('pending_referral'));
    
    if (pendingReferral === 'ANTIGRAVITY_TEST') {
        console.log("✅ SUCCÈS : Le code de parrainage a été capturé correctement par le navigateur !");
    } else {
        console.error("❌ ÉCHEC : Le code de parrainage n'a pas été capturé.");
    }

    await browser.close();
    console.log("🏁 Test terminé.");
})();
