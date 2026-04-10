@echo off
echo ===========================================
echo   Arret de tous les services RPS
echo ===========================================
echo.

echo [1/8] Arret Hardhat Node (port 8545)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8545 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [2/8] Arret Laravel API (port 8000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [3/8] Arret Queue Worker...
for /f "tokens=2" %%a in ('tasklist /fi "imagename eq php.exe" /fo csv ^| findstr queue:work') do (
    echo   Killing PHP queue worker
)
wmic process where "name='php.exe' and CommandLine like '%%queue:work%%'" delete >nul 2>&1

echo [4/8] Arret Reverb WebSocket (port 6001)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :6001 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [5/8] Arret Bridge Node.js (app.js port 3000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [6/8] Arret Batch Processor (port 3001)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3001 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [7/8] Arret Frontend (port 8080)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8080 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [8/8] Nettoyage process Node/PHP orphelins...
wmic process where "name='node.exe' and CommandLine like '%%hardhat node%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%full_deploy%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%run_batch_processor%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%app.js%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%serve public%%'" delete >nul 2>&1

echo.
echo ===========================================
echo   Tous les services sont arretes.
echo ===========================================
timeout /t 2 /nobreak > nul
