import puppeteer from 'puppeteer-core';

(async () => {
    console.log("🚀 Lancement du test E2E UI...");
    
    // Launch using Edge on Windows
    const browser = await puppeteer.launch({ 
        executablePath: "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    const page = await browser.newPage();

    page.on('console', msg => console.log('PAGE LOG:', msg.text()));

    // S'assurer que le localStorage est vide pour forcer l'affichage de la popup
    await page.goto('http://127.0.0.1:8001/', { waitUntil: 'domcontentloaded' });
    await page.evaluate(() => localStorage.clear());
    await page.reload({ waitUntil: 'networkidle0' });

    console.log("🔎 Vérification de la popup de langue...");
    
    // Attendre que l'écran "Select your Language" apparaisse
    const isPopupVisible = await page.evaluate(() => {
        const h1 = Array.from(document.querySelectorAll('h1')).find(el => el.textContent.includes('Select your Language'));
        return !!h1;
    });

    if (isPopupVisible) {
        console.log("✅ SUCCÈS : La popup de sélection de langue s'affiche bien !");
    } else {
        console.error("❌ ECHEC : La popup ne s'affiche pas.");
    }

    console.log("🖱️ Clic sur la langue Espagnol...");
    await page.evaluate(() => {
        const buttons = Array.from(document.querySelectorAll('button'));
        const esBtn = buttons.find(b => b.textContent.includes('Español'));
        if (esBtn) esBtn.click();
    });

    // Attendre un peu pour que la promesse se résolve et que i18n applique le localStorage
    await new Promise(r => setTimeout(r, 1000));

    const savedLocale = await page.evaluate(() => localStorage.getItem('user_locale'));
    
    if (savedLocale === 'es') {
        console.log("✅ SUCCÈS : La langue 'es' a été correctement enregistrée dans le navigateur !");
        console.log("🇪🇸 La traduction a été appliquée au DOM (document.documentElement.lang =", await page.evaluate(() => document.documentElement.lang), ")");
    } else {
        console.error("❌ ECHEC : La langue n'a pas été sauvegardée. Locale:", savedLocale);
    }

    await browser.close();
    console.log("🏁 Test terminé.");
})();
