@echo off
echo ===========================================
echo   Arret de tous les services Battlepool
echo ===========================================
echo.

echo [1/9] Arret Hardhat Node (port 8545)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8545 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [2/9] Arret Laravel API (port 8001)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8001 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [3/9] Arret Laravel API legacy (port 8000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8000 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [4/9] Arret Queue Worker...
wmic process where "name='php.exe' and CommandLine like '%%queue:work%%'" delete >nul 2>&1

echo [5/9] Arret Reverb WebSocket (port 8008)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :8008 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [6/9] Arret Reverb WebSocket legacy (port 6001)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :6001 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [7/9] Arret Bridge Node.js (app.js port 3000)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3000 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [8/9] Arret Batch Processor (port 3001)...
for /f "tokens=5" %%a in ('netstat -aon ^| findstr :3001 ^| findstr LISTENING') do (
    echo   Killing PID %%a
    taskkill /PID %%a /F >nul 2>&1
)

echo [9/9] Nettoyage process Node/PHP orphelins...
wmic process where "name='node.exe' and CommandLine like '%%hardhat node%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%full_deploy%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%run_batch_processor%%'" delete >nul 2>&1
wmic process where "name='node.exe' and CommandLine like '%%app.js%%'" delete >nul 2>&1

echo.
echo ===========================================
echo   Tous les services sont arretes.
echo ===========================================
timeout /t 2 /nobreak > nul
