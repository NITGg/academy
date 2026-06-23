#!/usr/bin/env pwsh
# switch-url.ps1 — toggle Moodle wwwroot between localhost and ngrok
# Usage:
#   .\switch-url.ps1           # auto-detect (ngrok if running, else localhost)
#   .\switch-url.ps1 local     # force localhost
#   .\switch-url.ps1 ngrok     # force ngrok (must be running)

param([string]$Mode = "auto")

$NGROK_DOMAIN = "unwaddling-jericho-checkable.ngrok-free.dev"
$LOCAL_URL    = "http://localhost:8081"
$NGROK_URL    = "https://$NGROK_DOMAIN"
$CONFIG_FILE  = "$PSScriptRoot\src\config.php"

function Set-Wwwroot($url) {
    $content = Get-Content $CONFIG_FILE -Raw
    $content = $content -replace '\$CFG->wwwroot\s*=\s*''[^'']*'';', ('$CFG->wwwroot   = ''' + $url + ''';')
    Set-Content $CONFIG_FILE $content -Encoding utf8 -NoNewline
}

function Get-NgrokRunning {
    try {
        $tunnels = Invoke-RestMethod http://localhost:4040/api/tunnels -TimeoutSec 2
        $match = $tunnels.tunnels | Where-Object { $_.public_url -like "*$NGROK_DOMAIN*" }
        return ($null -ne $match)
    } catch {
        return $false
    }
}

function Purge-Cache {
    docker exec academy_app php /var/www/html/admin/cli/purge_caches.php 2>&1 | Out-Null
}

# ── Determine target mode ────────────────────────────────────────────────────
if ($Mode -eq "auto") {
    $ngrokRunning = Get-NgrokRunning
    $Mode = if ($ngrokRunning) { "ngrok" } else { "local" }
    Write-Host "Auto-detected: ngrok running = $ngrokRunning -> switching to [$Mode]" -ForegroundColor Cyan
}

# ── Apply ────────────────────────────────────────────────────────────────────
$modeLower = $Mode.ToLower()

if ($modeLower -eq "local") {
    Set-Wwwroot $LOCAL_URL
    Write-Host "OK wwwroot -> $LOCAL_URL" -ForegroundColor Green
    Write-Host "   Purging Moodle caches..." -NoNewline
    Purge-Cache
    Write-Host " done" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Open: $LOCAL_URL" -ForegroundColor Yellow

} elseif ($modeLower -eq "ngrok") {
    $running = Get-NgrokRunning
    if (-not $running) {
        Write-Host "WARNING: ngrok tunnel not detected on localhost:4040" -ForegroundColor Yellow
        Write-Host "         Start it first with:" -ForegroundColor Yellow
        Write-Host "         ngrok http --domain=$NGROK_DOMAIN 8081" -ForegroundColor White
        $confirm = Read-Host "         Switch anyway? (y/N)"
        if ($confirm -ne "y") { exit 0 }
    }
    Set-Wwwroot $NGROK_URL
    Write-Host "OK wwwroot -> $NGROK_URL" -ForegroundColor Green
    Write-Host "   Purging Moodle caches..." -NoNewline
    Purge-Cache
    Write-Host " done" -ForegroundColor Green
    Write-Host ""
    Write-Host "   Open      : $NGROK_URL" -ForegroundColor Yellow
    Write-Host "   Jitsi API : $NGROK_URL/mod/jitsi/api_token.php" -ForegroundColor White
    Write-Host "   Sessions  : $NGROK_URL/local/academysessions/api.php" -ForegroundColor White

} else {
    Write-Host "Usage: .\switch-url.ps1 [auto|local|ngrok]" -ForegroundColor Red
    exit 1
}
