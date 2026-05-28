@echo off
echo ===========================================
echo   Lancement des Services Battlepool pour AVD
echo ===========================================
echo.

rem 1. Hardhat Node
echo [1/10] Demarrage de Hardhat Node (port 8545)...
start "Hardhat Node" cmd /k "cd battlepool && npx hardhat node"

rem Pause de 5 secondes
ping 127.0.0.1 -n 5 > nul

rem 2. Laravel Database Migration
echo [2/10] Execution des migrations Laravel...
start "Laravel Migration" cmd /k "php artisan migrate:fresh"

rem Pause de 3 secondes
ping 127.0.0.1 -n 3 > nul

rem 3. Deployment des Smart Contracts
echo [3/10] Deploiement des smart contracts...
start "Hardhat Deployment" cmd /k "cd battlepool && npx hardhat run full_deploy.js"

rem Pause de 7 secondes
ping 127.0.0.1 -n 7 > nul

rem 4. Laravel Serve sur port 8000 (requis pour AVD)
echo [4/10] Demarrage du Serveur Laravel (port 8000)...
set PHP_CLI_SERVER_WORKERS=4
start "Laravel API (Port 8000)" cmd /k "php artisan serve --host=0.0.0.0 --port=8000"

rem 5. Queue Worker
echo [5/10] Demarrage du Queue Worker...
start "Laravel Queue" cmd /k "php artisan queue:work"

rem 6. Reverb Websocket
echo [6/10] Demarrage de Reverb WebSocket...
start "Laravel Reverb" cmd /k "php artisan reverb:start"

rem 7. Bridge Node JS
echo [7/10] Demarrage de App Bridge (Port 3000)...
start "Bridge Node" cmd /k "cd smart_contracts && node app.js"

rem 8. Matchmaking Round-Robin
echo [8/10] Demarrage du Matchmaker (Port 3001)...
start "Matchmaker Engine" cmd /k "cd smart_contracts && node run_batch_processor.js"

rem 9. Vite Frontend Dev Server
echo [9/10] Demarrage de Vite Dev Server (Port 5173)...
start "Vite Dev" cmd /k "npm run dev"

rem Pause de 5 secondes
ping 127.0.0.1 -n 5 > nul

rem 10. Configurer la redirection adb reverse pour l'AVD
echo [10/10] Configuration de la redirection de ports ADB pour l'AVD...
"C:\Users\GWX1223153\AppData\Local\Android\Sdk\platform-tools\adb.exe" reverse tcp:8000 tcp:8000
"C:\Users\GWX1223153\AppData\Local\Android\Sdk\platform-tools\adb.exe" reverse tcp:5173 tcp:5173
"C:\Users\GWX1223153\AppData\Local\Android\Sdk\platform-tools\adb.exe" reverse --list

echo.
echo ===========================================
echo   Tous les services ont ete lances !
echo ===========================================
