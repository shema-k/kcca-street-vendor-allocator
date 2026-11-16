param(
    [string]$XamppPath = 'C:\xampp',
    [ValidateRange(1, 65535)][int]$DatabasePort = 3306,
    [ValidateRange(1, 65535)][int]$WebPort = 8080
)

$ErrorActionPreference = 'Stop'

try {
    $app = Join-Path $PSScriptRoot 'kcca-vendor-relocation-updated\kcca-vendor-relocation'
    $php = Join-Path $XamppPath 'php\php.exe'
    $mysql = Join-Path $XamppPath 'mysql\bin\mysql.exe'
    if (!(Test-Path -LiteralPath $php) -or !(Test-Path -LiteralPath $mysql)) {
        throw 'Install XAMPP with PHP 8+ in C:\xampp from https://www.apachefriends.org/download.html, then run START-LOCAL.bat again.'
    }
    if (!(Test-Path -LiteralPath (Join-Path $app 'database\seed.php'))) {
        throw ('Application source was not found at ' + $app)
    }

    '<?php exit(PHP_VERSION_ID >= 80000 && extension_loaded("pdo_mysql") ? 0 : 1);' | & $php
    if ($LASTEXITCODE -ne 0) {
        throw 'PHP 8+ with the pdo_mysql extension is required. Check your XAMPP PHP installation.'
    }

    # Check the web port before creating or seeding anything.
    $probe = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $WebPort)
    try { $probe.Start() } catch { throw "Web port $WebPort is busy. Close the existing server or run this script with -WebPort 8081." } finally { $probe.Stop() }

    $dbArgs = @('--host=127.0.0.1', "--port=$DatabasePort", '--user=root', '--connect-timeout=5', '--batch', '--skip-column-names')
    $connectionResult = & $mysql @dbArgs '--execute=SELECT 1;'
    if ($LASTEXITCODE -ne 0) {
        throw 'Start MySQL in the XAMPP Control Panel, then run START-LOCAL.bat again. If your root account has a password, set MYSQL_PWD in PowerShell before running Start-Local.ps1.'
    }

    # A separate database keeps the original application database untouched.
    $database = 'kcca_vendor_local_demo'
    $accountSql = @"
CREATE DATABASE IF NOT EXISTS kcca_vendor_local_demo CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS 'kcca_local_demo'@'localhost' IDENTIFIED BY 'kcca_local_demo_2026';
GRANT ALL PRIVILEGES ON kcca_vendor_local_demo.* TO 'kcca_local_demo'@'localhost';
"@
    & $mysql @dbArgs "--execute=$accountSql"
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the local demo database and account.' }

    $count = & $mysql @dbArgs "--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$database';"
    if ($LASTEXITCODE -ne 0 -or "$count" -notmatch '^\d+$') {
        throw 'Could not inspect the demo database. No sample data was loaded.'
    }

    $env:KCCA_DB_HOST = '127.0.0.1'
    $env:KCCA_DB_PORT = "$DatabasePort"
    $env:KCCA_DB_SOCKET = ''
    $env:KCCA_DB_NAME = $database
    $env:KCCA_DB_USER = 'kcca_local_demo'
    $env:KCCA_DB_PASS = 'kcca_local_demo_2026'

    # A fresh PHP connection verifies the app account independently of root.
    $connectionCheck = '<?php new PDO("mysql:host=127.0.0.1;port=" . getenv("KCCA_DB_PORT") . ";dbname=" . getenv("KCCA_DB_NAME"), getenv("KCCA_DB_USER"), getenv("KCCA_DB_PASS"), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);'
    $connectionCheck | & $php
    if ($LASTEXITCODE -ne 0) { throw 'The demo database account could not connect. No sample data was loaded.' }

    Push-Location -LiteralPath $app
    try {
        if ([int]$count -eq 0) {
            Write-Host 'Loading demonstration data into the separate local database. This can take a few minutes.'
            & $php 'database/seed.php'
            if ($LASTEXITCODE -ne 0) {
                throw 'Sample-data initialization failed. The database has been retained for diagnosis; it will not be automatically reset.'
            }
        } else {
            Write-Host 'Using the existing local demo database. Its records will be preserved.'
        }

        # Older demo databases may lack these screens' tables. This schema only
        # creates missing tables; it never resets records or loads sample rows.
        $supportSchema = Get-Content -LiteralPath 'database\ensure_trader_support.sql' -Raw
        & $mysql @dbArgs "--database=$database" "--execute=$supportSchema"
        if ($LASTEXITCODE -ne 0) { throw 'Could not prepare the trader support tables. Existing data has been preserved.' }

        $readyCheck = '<?php require "lib/bootstrap.php"; if ((int) Database::value("SELECT COUNT(*) FROM markets") < 1 || (int) Database::value("SELECT COUNT(*) FROM users") < 1) { fwrite(STDERR, "Demo data is incomplete.\n"); exit(1); }'
        $readyCheck | & $php
        if ($LASTEXITCODE -ne 0) { throw 'The demo database is incomplete. It has not been reset.' }

        $url = "http://127.0.0.1:$WebPort/auth/login.php"
        Write-Host "Open: $url"
        Write-Host 'Username: admin'
        Write-Host 'Password: KCCA VRSP 2026'
        Write-Host 'Keep this window open. Press Ctrl+C to stop the website.'
        & $php -S "127.0.0.1:$WebPort" -t .
        if ($LASTEXITCODE -ne 0) { throw 'The local web server stopped with an error.' }
    } finally {
        Pop-Location
    }
} catch {
    Write-Host ("Setup stopped: " + $_.Exception.Message) -ForegroundColor Red
    exit 1
}
