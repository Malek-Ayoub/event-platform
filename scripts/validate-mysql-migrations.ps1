# Validates all migrations against a live MySQL 8 server (no Docker required).
# Usage: configure DB_* in .env or pass env vars, then run:
#   .\scripts\validate-mysql-migrations.ps1

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

$env:DB_CONNECTION = if ($env:DB_CONNECTION) { $env:DB_CONNECTION } else { "mysql" }

$host = if ($env:DB_HOST) { $env:DB_HOST } else { "127.0.0.1" }
$port = if ($env:DB_PORT) { $env:DB_PORT } else { "3306" }

Write-Host "Validating migrations on MySQL (${host}:${port})..." -ForegroundColor Cyan

php artisan db:show --database=mysql 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "MySQL is not reachable. Install MySQL 8 / Laragon / XAMPP locally, or point DB_HOST to a remote server." -ForegroundColor Yellow
    Write-Host "Example:" -ForegroundColor Yellow
    Write-Host '  $env:DB_CONNECTION="mysql"; $env:DB_HOST="127.0.0.1"; $env:DB_PORT="3306"; $env:DB_DATABASE="event_platform"; $env:DB_USERNAME="root"; $env:DB_PASSWORD="secret"; .\scripts\validate-mysql-migrations.ps1' -ForegroundColor Gray
    exit 1
}

if (Test-Path "database\schema\mysql-schema.sql") {
    Remove-Item "database\schema\mysql-schema.sql" -Force
}

php artisan migrate:fresh --seed --no-interaction --database=mysql
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan schema:dump --database=mysql
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

php artisan test
exit $LASTEXITCODE
