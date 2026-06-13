@echo off
echo ===========================================
echo   Arret de tous les services existants...
echo ===========================================
call stop_all.bat

echo.
echo ===========================================
echo   Relance de l'environnement complet (TB)
echo ===========================================

echo [1/9] Demarrage de Hardhat Node...
start "Hardhat Node" cmd /k "cd battlepool && npx hardhat node"
timeout /t 5 /nobreak > nul

echo [2/9] Serveur Laravel API...
start "Serveur API Laravel" cmd /k "php artisan serve --port=8001"

echo [3/9] Queue Worker...
start "Queue Worker" cmd /k "php artisan queue:work"

echo [4/9] Reverb WebSocket...
start "Serveur Reverb" cmd /k "php artisan reverb:start"
timeout /t 3 /nobreak > nul

echo [5/9] Migration DB fresh + Seed...
php artisan migrate:fresh --seed --force

echo [6/9] Deploiement des smart contracts...
cd battlepool
call npx hardhat run full_deploy.js --network localhost
cd ..

echo [7/9] Bridge Node.js...
start "Bridge Node.js" cmd /k "cd smart_contracts && node app.js"

echo [8/9] Batch Processor...
start "Batch Processor" cmd /k "cd smart_contracts && node run_batch_processor.js"
timeout /t 3 /nobreak > nul

echo [9/9] Injection des 40 Bots...
start "Simulation Bots" cmd /k "cd smart_contracts && node simulation_bots.js 40 2"

echo.
echo ===========================================
echo   Environnement TB entierement redemarre !
echo   Laissez la fenetre Simulation Bots se
echo   terminer (environ 40 secondes) avant
echo   de commencer a jouer.
echo ===========================================

