# start.ps1 - Secure Docker startup script
#
# Workflow:
# 0. Ensure Docker Desktop is running + fix WSL2 MTU (corporate VPN/Huawei network bug)
# 1. Provision secrets (decrypt .enc to plaintext, for Docker bind mounts)
# 2. docker compose up -d
# 3. Wait for containers to be healthy
# 4. Deprovision secrets (delete plaintext, keep only .enc encrypted)
#
# After this script, the host has NO plaintext secrets on disk.
# Containers have secrets in bind-mounted /run/secrets/ (already read into RAM
# by entrypoint scripts before deprovision).
#
# IMPORTANT: Do NOT restart containers after deprovision.
# Run this script again to provision + restart.

# --- 0a. Ensure Docker Desktop is running (daemon down after machine reboot) ---
$dockerRunning = $false
try { docker info *> $null; $dockerRunning = ($LASTEXITCODE -eq 0) } catch {}
if (-not $dockerRunning) {
    Write-Host "Docker daemon not running - starting Docker Desktop..." -ForegroundColor Cyan
    $dockerExe = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    if (Test-Path $dockerExe) {
        Start-Process -FilePath $dockerExe
        $maxDockerWait = 180
        $dockerWaited = 0
        while ($dockerWaited -lt $maxDockerWait) {
            Start-Sleep -Seconds 5
            $dockerWaited += 5
            docker info *> $null
            if ($LASTEXITCODE -eq 0) { break }
        }
        docker info *> $null
        if ($LASTEXITCODE -ne 0) {
            Write-Host "FATAL: Docker daemon not ready after ${maxDockerWait}s." -ForegroundColor Red
            exit 1
        }
        Write-Host "Docker daemon ready (waited ${dockerWaited}s)" -ForegroundColor Green
    } else {
        Write-Host "FATAL: Docker Desktop not found at $dockerExe" -ForegroundColor Red
        exit 1
    }
}

# --- 0b. Fix WSL2 MTU (corporate VPN / Huawei network drops large npm downloads).
# Symptom: docker builds hang on `npm ci` (network idle, 0 bytes transferred).
# WSL2 defaults to MTU 1280 on eth0; some corporate paths need >= 1400 for full TCP
# throughput. Volatile fix (resets on Docker Desktop restart) -> applied on each run.
$wslDistroRunning = (wsl --list --running 2>$null) -match "docker-desktop"
if ($wslDistroRunning) {
    $currentMtu = (wsl -d docker-desktop -- ip link show eth0 2>$null | Select-String "mtu (\d+)" | ForEach-Object { $_.Matches[0].Groups[1].Value })
    if ($currentMtu -and [int]$currentMtu -lt 1400) {
        Write-Host "Fixing WSL2 MTU: ${currentMtu} -> 1400 (corporate VPN / large downloads bug)..." -ForegroundColor Cyan
        wsl -d docker-desktop -- ip link set dev eth0 mtu 1400 | Out-Null
        Write-Host "WSL2 MTU set to 1400" -ForegroundColor Green
    }
}

# --- 0c. Clean ghost directories in .secrets/ (created by bind mounts when containers
# are recreated while plaintext secrets are deleted). They break `provision` (EISDIR).
$ghostDirs = Get-ChildItem ".secrets" -Directory -ErrorAction SilentlyContinue | Where-Object { (Get-ChildItem $_.FullName -Force -ErrorAction SilentlyContinue).Count -eq 0 }
if ($ghostDirs) {
    Write-Host "Cleaning ghost directories in .secrets/ (bind mount artifacts)..." -ForegroundColor Cyan
    $ghostDirs | Remove-Item -Recurse -Force
    Write-Host "Ghost directories removed." -ForegroundColor Green
}

Write-Host "Secure Docker startup - provisioning secrets..." -ForegroundColor Cyan
node secrets-manager.mjs provision

Write-Host ""
Write-Host "Starting Docker containers (2 limits workers - HANDOVER §12.4)..." -ForegroundColor Cyan
docker compose up -d --scale queue-worker-limits=2

Write-Host ""
Write-Host "Waiting for containers to be healthy..." -ForegroundColor Cyan
$maxWait = 600
$waited = 0
$allHealthy = $false
while ($waited -lt $maxWait) {
    Start-Sleep -Seconds 10
    $waited += 10
    $containers = docker compose ps --format json 2>&1
    $allHealthy = $true
    $runningCount = 0
    foreach ($line in $containers) {
        try {
            $s = $line | ConvertFrom-Json
            $status = $s.Status
            $name = $s.Name
            if ($status -match "Exited") {
                continue
            }
            if ($status -match "Up") {
                $runningCount++
                # blockchain has a long start_period — check for "healthy" explicitly
                if ($name -match "blockchain") {
                    if ($status -notmatch "healthy") {
                        $allHealthy = $false
                    }
                } elseif ($status -match "health: starting") {
                    $allHealthy = $false
                }
            } else {
                $allHealthy = $false
            }
        } catch {}
    }
    if ($allHealthy -and $runningCount -gt 0) {
        Write-Host "All containers healthy (waited ${waited}s)" -ForegroundColor Green
        break
    }
    Write-Host "  Waiting... (${waited}s) ${runningCount} running"
}

if (-not $allHealthy) {
    Write-Host "Not all containers healthy after ${maxWait}s - secrets remain provisioned" -ForegroundColor Yellow
    Write-Host "Run node secrets-manager.mjs deprovision manually when ready."
    exit 1
}

Write-Host ""
Write-Host "Deprovisioning secrets (deleting plaintext from host)..." -ForegroundColor Cyan
node secrets-manager.mjs deprovision

Write-Host ""
Write-Host "Post-start checks (blockchain / indexer)..." -ForegroundColor Cyan
# --- Vérification indexeur vs chaîne (piège HANDOVER §18.4) : après un restart,
# la chaîne Hardhat repart de 0 alors que l'indexeur persiste son état -> événements ratés.
$rpcBody = '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}'
try {
    $chainBlock = [Convert]::ToInt64((((Invoke-WebRequest -Uri http://127.0.0.1:8546 -Method Post -Body $rpcBody -ContentType "application/json" -UseBasicParsing -TimeoutSec 5).Content | ConvertFrom-Json).result), 16)
    Write-Host "Blockchain block number: $chainBlock" -ForegroundColor White
    Write-Host "NB: si la chaine vient de redemarrer, penser a reset blockchain_sync_states + funder les wallets de service" -ForegroundColor Yellow
    Write-Host "    (scripts bp-test\reset_indexer_marketplace.sh / fund_services.cjs - voir HANDOVER §18.6)" -ForegroundColor Yellow
} catch {
    Write-Host "Could not query blockchain RPC (not blocking)." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Done! Host has NO plaintext secrets on disk." -ForegroundColor Green
Write-Host "Containers have secrets in RAM (/run/secrets/ bind mounts)." -ForegroundColor Green
Write-Host "To stop: docker compose down" -ForegroundColor White
Write-Host "To restart: run this script again (NOT docker compose restart)" -ForegroundColor White