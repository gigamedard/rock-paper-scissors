# PowerShell script to orchestrate TB Protocol
# Resets the environment, launches Hardhat, Laravel, Queue, Websockets, Bridge, Batch Processor, and Simulation Bots.

# Stop existing processes to avoid port conflicts
Write-Host "Stopping existing PHP and Node processes..." -ForegroundColor Yellow
Stop-Process -Name "node", "php" -Force -ErrorAction SilentlyContinue

# Get Project Root
$ProjectRoot = Resolve-Path "$PSScriptRoot\.."
Write-Host "Project Root: $ProjectRoot" -ForegroundColor Cyan

# 1. Clean logs
Write-Host "[1/10] Cleaning storage/logs/..." -ForegroundColor Yellow
$LogDir = "$ProjectRoot\storage\logs"
if (Test-Path $LogDir) {
    Remove-Item "$LogDir\*" -Force -Recurse -ErrorAction SilentlyContinue
} else {
    New-Item -ItemType Directory -Path $LogDir -Force
}

# Helper to start background processes with output redirection
function Start-BGProcess {
    param(
        [string]$Name,
        [string]$Command,
        [string]$Arguments,
        [string]$WorkDir,
        [string]$LogFile
    )
    Write-Host "Starting $Name..." -ForegroundColor Green
    $process = Start-Process -NoNewWindow -FilePath $Command -ArgumentList $Arguments -WorkingDirectory $WorkDir -RedirectStandardOutput "$LogDir\$LogFile" -RedirectStandardError "$LogDir\${Name}_err.log" -PassThru
    return $process
}

# 2. Start Hardhat Node
Start-BGProcess -Name "Hardhat_Node" -Command "cmd.exe" -Arguments "/c npx hardhat node" -WorkDir "$ProjectRoot\battlepool" -LogFile "hardhat.log"

Write-Host "Waiting 5 seconds for Hardhat Node to initialize..." -ForegroundColor Yellow
Start-Sleep -Seconds 5

# 3. Database Reset and Seed
Write-Host "[3/10] Resetting and seeding DB..." -ForegroundColor Yellow
Set-Location $ProjectRoot
php artisan migrate:fresh --seed
php artisan cache:clear

# 4. Start Laravel serve
Start-BGProcess -Name "Laravel_Serve" -Command "php" -Arguments "artisan serve --port=8001" -WorkDir $ProjectRoot -LogFile "laravel_server.log"

# 5. Start Queue Worker
Start-BGProcess -Name "Queue_Worker" -Command "php" -Arguments "artisan queue:work --tries=3 --timeout=120" -WorkDir $ProjectRoot -LogFile "queue.log"

# 6. Start Reverb Websocket
Start-BGProcess -Name "Reverb_WS" -Command "php" -Arguments "artisan reverb:start" -WorkDir $ProjectRoot -LogFile "reverb.log"

Start-Sleep -Seconds 3

# 7. Deploy smart contracts
Write-Host "[7/10] Deploying Smart Contracts..." -ForegroundColor Yellow
Set-Location "$ProjectRoot\battlepool"
cmd.exe /c "npx hardhat run full_deploy.js --network localhost" > "$LogDir\deploy.log" 2>&1

if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to deploy smart contracts. Check $LogDir\deploy.log"
    exit 1
}
Write-Host "Smart contracts deployed successfully." -ForegroundColor Green

Write-Host "Funding simulation bots..." -ForegroundColor Yellow
Set-Location "$ProjectRoot\smart_contracts"
cmd.exe /c "node fund_hardhat_bots.js" > "$LogDir\funding.log" 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to fund simulation bots. Check $LogDir\funding.log"
} else {
    Write-Host "Simulation bots funded successfully." -ForegroundColor Green
}

Start-Sleep -Seconds 5

# 8. Start Bridge Node.js
Start-BGProcess -Name "Bridge_Node" -Command "node" -Arguments "app.js" -WorkDir "$ProjectRoot\smart_contracts" -LogFile "bridge.log"

# 9. Start Batch Processor
Start-BGProcess -Name "Batch_Processor" -Command "node" -Arguments "run_batch_processor.js" -WorkDir "$ProjectRoot\smart_contracts" -LogFile "batch.log"

Start-Sleep -Seconds 2

# 10. Start Simulation Bots (90 bots starting from index 2)
Start-BGProcess -Name "Simulation_Bots" -Command "node" -Arguments "simulation_bots.js 90 2" -WorkDir "$ProjectRoot\smart_contracts" -LogFile "simulation.log"

Write-Host "`n===============================================" -ForegroundColor Green
Write-Host "TB Protocol initiated successfully!" -ForegroundColor Green
Write-Host "All processes running in background." -ForegroundColor Green
Write-Host "Logs are redirected to storage/logs/" -ForegroundColor Green
Write-Host "===============================================" -ForegroundColor Green

Write-Host "Keeping parent process alive to preserve background services..." -ForegroundColor Cyan
while ($true) {
    Start-Sleep -Seconds 1
}
