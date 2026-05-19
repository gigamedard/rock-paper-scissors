# Arret des processus existants si besoin
Stop-Process -Name "node", "php" -Force -ErrorAction SilentlyContinue

echo "Demarrage du noeud Hardhat..."
Start-Process -NoNewWindow -FilePath "cmd.exe" -ArgumentList "/c npx hardhat node" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors\battlepool" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\hardhat.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\hardhat_err.log"

Start-Sleep -Seconds 5

echo "Demarrage du serveur Laravel..."
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan serve --port=8001" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\laravel.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\laravel_err.log"

echo "Nettoyage de la base de donnees..."
php artisan migrate:fresh --seed

echo "Demarrage de la file dattente..."
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan queue:work" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\queue.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\queue_err.log"

echo "Demarrage de Reverb..."
Start-Process -NoNewWindow -FilePath "php" -ArgumentList "artisan reverb:start" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\reverb.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\reverb_err.log"

echo "Deploiement des smart contracts..."
Set-Location "d:\dev\PHP\rock-paper-scissors\battlepool"
cmd.exe /c "npx hardhat run full_deploy.js --network localhost" > "d:\dev\PHP\rock-paper-scissors\storage\logs\deploy.log" 2>&1

Start-Sleep -Seconds 5

echo "Demarrage du Bridge Node.js..."
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "app.js" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors\smart_contracts" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\bridge.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\bridge_err.log"

echo "Demarrage du Batch Processor..."
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "run_batch_processor.js" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors\smart_contracts" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\batch.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\batch_err.log"

Start-Sleep -Seconds 2

echo "Demarrage de la simulation de test bots (30 bots a partir de l index 2)..."
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "simulation_bots.js 30 2" -WorkingDirectory "d:\dev\PHP\rock-paper-scissors\smart_contracts" -RedirectStandardOutput "d:\dev\PHP\rock-paper-scissors\storage\logs\simulation.log" -RedirectStandardError "d:\dev\PHP\rock-paper-scissors\storage\logs\simulation_err.log"

echo "Tous les services et bots de simulation sont lances en arriere-plan !"
