@echo off
echo Lancement de l'environnement complet de Rock-Paper-Scissors...

rem 1. Hardhat Node
start "Hardhat Node" cmd /k "cd battlepool && npx hardhat node"

rem Attendre un instant pour que le noeud hardhat demarre bien
timeout /t 3 /nobreak > nul

rem 2.1 Laravel Backend
set PHP_CLI_SERVER_WORKERS=4
start "Serveur API Laravel" cmd /k "php artisan serve --port=8001"

rem 2.2. Data Base clean
start "Data Base clean" cmd /k "php artisan migrate:fresh"

rem 3. Queue Worker
start "File d'attente (Jobs)" cmd /k "php artisan queue:work"

rem 4. Reverb Websocket
start "Serveur Reverb (WebSockets)" cmd /k "php artisan reverb:start"

rem . deployent des smart contract sur le node
start "full deployment" cmd /k "cd battlepool && npx hardhat run full_deploy.js"

rem Attendre un instant pour que le noeud hardhat demarre bien
timeout /t 7 /nobreak > nul

rem 5. App Bridge Node JS
start "Bridge Node.js (Ecouteur + IPFS)" cmd /k "cd smart_contracts && node app.js"

rem 6. Batch Processor Sorts/Matchmaking
start "Moteur Matchmaking Round-Robin" cmd /k "cd smart_contracts && node run_batch_processor.js"

rem 7. Serveur Frontend
rem start "Serveur Frontend UI" cmd /k "npx serve public/ -p 8080"

echo Tous les services ont demarre dans de nouvelles fenetres !
exit
