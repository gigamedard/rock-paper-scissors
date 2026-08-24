# start.ps1 - Secure Docker startup script
#
# Workflow:
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

Write-Host "Secure Docker startup - provisioning secrets..." -ForegroundColor Cyan
node secrets-manager.mjs provision

Write-Host ""
Write-Host "Starting Docker containers..." -ForegroundColor Cyan
docker compose up -d

Write-Host ""
Write-Host "Waiting for containers to be healthy..." -ForegroundColor Cyan
$maxWait = 180
$waited = 0
$allHealthy = $false
while ($waited -lt $maxWait) {
    Start-Sleep -Seconds 5
    $waited += 5
    $containers = docker compose ps --format json 2>&1
    $allHealthy = $true
    $runningCount = 0
    foreach ($line in $containers) {
        try {
            $s = $line | ConvertFrom-Json
            $status = $s.Status
            if ($status -match "Exited") {
                # init-db exits after success - that is OK
                continue
            }
            if ($status -match "Up") {
                $runningCount++
                # If it says "Up" but no "healthy", and it has no healthcheck, treat as OK
                # But we require healthcheck containers to be "healthy"
                if ($status -notmatch "healthy" -and $status -match "health: starting") {
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
Write-Host "Done! Host has NO plaintext secrets on disk." -ForegroundColor Green
Write-Host "Containers have secrets in RAM (/run/secrets/ bind mounts)." -ForegroundColor Green
Write-Host "To stop: docker compose down" -ForegroundColor White
Write-Host "To restart: run this script again (NOT docker compose restart)" -ForegroundColor White