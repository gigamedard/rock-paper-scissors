# 1. Kill all existing processes
echo "🛑 Killing existing processes..."
Get-Process | Where-Object { $_.Name -match "node|php" } | Stop-Process -Force -ErrorAction SilentlyContinue

# 2. Clear logs and cache
echo "🧹 Clearing logs and cache..."
Remove-Item -Path "storage/logs/*.log" -Force -ErrorAction SilentlyContinue
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 3. Start Hardhat Node in a separate process
echo "🚀 Starting fresh Hardhat Node..."
Start-Process cmd -ArgumentList "/k cd battlepool && npx hardhat node" -WindowStyle Minimized
Start-Sleep -Seconds 5

# 4. Deploy Contracts
echo "📜 Deploying Contracts..."
cd battlepool
npx hardhat run full_deploy.js --network localhost
cd ..

# 5. Database Reset
echo "🗄️ Resetting Database..."
php artisan migrate:fresh --force
php artisan simulation:reset

# 6. Start Essential Services
echo "🌐 Starting Bridge and Batch Processor..."
Start-Process cmd -ArgumentList "/k cd smart_contracts && node app.js" -WindowStyle Minimized
Start-Process cmd -ArgumentList "/k cd smart_contracts && node run_batch_processor.js" -WindowStyle Minimized
Start-Process cmd -ArgumentList "/k php artisan serve --port=8000" -WindowStyle Minimized

Start-Sleep -Seconds 5

# 7. Prepare and Launch Simulation
echo "🤖 Preparing 30 accounts..."
cd smart_contracts
node prepare_simulation.js
Start-Sleep -Seconds 2
echo "🚀 Launching 30-bot simulation..."
node simulate_large_scale.js
cd ..

echo "🏁 Reset and Launch completed!"
