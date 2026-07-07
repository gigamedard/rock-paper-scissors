param (
    [string]$BaseBet = "0.05",
    [int]$BotCount = 41
)

Write-Host "=============================================" -ForegroundColor Cyan
Write-Host " Testing Bots with Base Bet: $BaseBet" -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan

# 1. Mettre à jour les bots dans la BDD via Tinker (dans le conteneur Docker)
Write-Host "`n[1/2] Updating all mock bots in database to bet_amount = $BaseBet..." -ForegroundColor Yellow
$env:BASE_BET = $BaseBet
docker-compose exec -e BASE_BET=$BaseBet app php artisan tinker scratch/update_bots_bet.php

# 2. Redémarrer le Batch Processor
Write-Host "`n[2/2] Restarting Batch Processor with BASE_BET=$BaseBet..." -ForegroundColor Yellow

# On cherche s'il y a déjà une instance de run_batch_processor.js ou simulation_bots.js qui tourne
$nodeProcesses = Get-WmiObject Win32_Process | Where-Object { ($_.CommandLine -match "run_batch_processor.js" -or $_.CommandLine -match "simulation_bots.js" -or $_.CommandLine -match "bot_depositor.js") -and $_.Name -match "node" }
if ($nodeProcesses) {
    Write-Host "Stopping existing batch processor and simulation bots..." -ForegroundColor Yellow
    foreach ($proc in $nodeProcesses) {
        Stop-Process -Id $proc.ProcessId -Force -ErrorAction SilentlyContinue
    }
    Start-Sleep -Seconds 1
}

$LogDir = "$PSScriptRoot\..\storage\logs"
if (-Not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force > $null
}

$env:BASE_BET = $BaseBet

# Lancer le nouveau batch processor en background
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "smart_contracts/run_batch_processor.js" -WorkingDirectory "$PSScriptRoot\.." -RedirectStandardOutput "$LogDir\batch.log" -RedirectStandardError "$LogDir\batch_err.log" -PassThru

# Lancer les nouveaux simulation bots en background
Write-Host "Launching $BotCount bot deposits..." -ForegroundColor Cyan
Start-Process -NoNewWindow -FilePath "node" -ArgumentList "smart_contracts/bot_depositor.js $BotCount 2 $BaseBet" -WorkingDirectory "$PSScriptRoot\.." -RedirectStandardOutput "$LogDir\simulation.log" -RedirectStandardError "$LogDir\simulation_err.log" -PassThru

Write-Host "`n✅ Done! The batch processor and bot depositor are now running with BASE_BET=$BaseBet." -ForegroundColor Green
Write-Host "You can monitor the logs with: Get-Content storage\logs\simulation.log -Wait" -ForegroundColor Cyan
