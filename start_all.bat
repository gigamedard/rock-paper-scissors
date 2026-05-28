@echo off
echo ===========================================
echo   Lancement de l'environnement Battlepool
echo   (Branche: refactor/blockchain-optimization)
echo ===========================================
echo.

rem 1. Hardhat Node (blockchain locale)
echo [1/8] Demarrage de Hardhat Node (port 8545)...
start "Hardhat Node" cmd /k "cd battlepool && npx hardhat node"

rem Attendre que le noeud Hardhat demarre
timeout /t 5 /nobreak > nul

rem 2. Base de donnees : migration + seed
echo [2/8] Migration DB fresh + Seed des GameSettings...
php artisan migrate:fresh --seed --force
if %errorlevel% neq 0 (
    echo ERREUR: La migration a echoue !
    pause
    exit /b 1
)
echo    DB : OK (GameSettings: 8 entrees)

rem 3. Clear cache apres seed
echo [3/8] Nettoyage du cache...
php artisan cache:clear
php artisan config:clear

rem 4. Deploiement des Smart Contracts
echo [4/8] Deploiement des smart contracts sur Hardhat...
start "Full Deployment" cmd /k "cd battlepool && npx hardhat run full_deploy.js"

rem Attendre le deploiement
timeout /t 10 /nobreak > nul

rem 5. Laravel Backend (port 8001)
echo [5/8] Demarrage du Serveur Laravel API (port 8001)...
set PHP_CLI_SERVER_WORKERS=4
start "Serveur API Laravel" cmd /k "php artisan serve --port=8001"

rem 6. Queue Worker (CRITIQUE pour les jobs asynchrones blockchain)
echo [6/8] Demarrage du Queue Worker (Jobs blockchain asynchrones)...
start "Queue Worker (Jobs)" cmd /k "php artisan queue:work --tries=3 --timeout=120"

rem 7. Reverb WebSocket
echo [7/8] Demarrage de Reverb WebSocket (port 8008)...
start "Serveur Reverb (WebSockets)" cmd /k "php artisan reverb:start"

rem 8. Bridge Node.js (app.js)
echo [8/8] Demarrage de App Bridge Node.js (port 3000)...
start "Bridge Node.js (Ecouteur + IPFS)" cmd /k "cd smart_contracts && node app.js"

rem 9. Batch Processor (matchmaking)
rem start "Moteur Matchmaking Round-Robin" cmd /k "cd smart_contracts && node run_batch_processor.js"

echo.
echo ===========================================
echo   Tous les services sont lances !
echo ===========================================
echo.
echo   Services actifs :
echo     - Hardhat Node       : http://127.0.0.1:8545
echo     - Laravel API        : http://127.0.0.1:8001
echo     - Queue Worker       : Actif (jobs blockchain)
echo     - Reverb WebSocket   : ws://127.0.0.1:8008
echo     - Bridge Node.js     : http://127.0.0.1:3000
echo.
echo   IMPORTANT: Le Queue Worker est maintenant CRITIQUE.
echo   Tous les appels blockchain passent par des Jobs Laravel.
echo   Verifiez qu'il tourne avec: php artisan queue:work
echo ===========================================
timeout /t 3 /nobreak > nul
