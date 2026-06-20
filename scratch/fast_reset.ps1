.\stop_all.bat
php artisan migrate:fresh --seed
Start-Process -NoNewWindow -FilePath "npx.cmd" -ArgumentList "hardhat node" -WorkingDirectory "g:\DEV\PHP\rock-paper-scissors\battlepool"
Start-Sleep -Seconds 5
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan serve --port=8001" -WorkingDirectory "g:\DEV\PHP\rock-paper-scissors"
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan queue:work" -WorkingDirectory "g:\DEV\PHP\rock-paper-scissors"
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan reverb:start" -WorkingDirectory "g:\DEV\PHP\rock-paper-scissors"
Set-Location "g:\DEV\PHP\rock-paper-scissors\battlepool"
npx.cmd hardhat run full_deploy.js --network localhost
Set-Location "g:\DEV\PHP\rock-paper-scissors\smart_contracts"
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "app.js"
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "run_batch_processor.js"
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "simulation_bots.js 40 2"
