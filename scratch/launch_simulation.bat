
@echo off
echo 🛑 Killing existing processes...
taskkill /F /IM node.exe /T 2>nul
taskkill /F /IM php.exe /T 2>nul
timeout /t 2

echo 🚀 Starting Hardhat Node...
start "Hardhat" cmd /k "cd battlepool && npx hardhat node"
timeout /t 5

echo 📜 Deploying Contracts...
cd battlepool && npx hardhat run full_deploy.js --network localhost
cd ..

echo 🧹 Resetting Laravel...
php artisan migrate:fresh --force
php artisan simulation:reset

echo 🌐 Starting Servers...
start "Laravel" cmd /k "php artisan serve --port=8000"
timeout /t 3
start "Bridge" cmd /k "cd smart_contracts && node app.js"
start "Batch" cmd /k "cd smart_contracts && node run_batch_processor.js"

echo ⏳ Waiting for services to stabilize...
timeout /t 5

echo 🤖 Launching 50 Bots Simulation...
cd smart_contracts && node simulate_large_scale.js
