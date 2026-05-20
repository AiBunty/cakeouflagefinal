# PowerShell script to drop all tables and import the SQL dump as the new master DB
# Usage: Run in PowerShell from the project root

param(
    [string]$dbHost = "localhost",
    [string]$dbUser = "root",
    [string]$dbPass = "",
    [string]$dbName = "cakeouflage"
)

# Path to SQL dump
$sqlFile = "sdb-77_hosting_stackcp_net.sql"

if (!(Test-Path $sqlFile)) {
    Write-Error "SQL dump file '$sqlFile' not found in project root."
    exit 1
}

Write-Host "Dropping all tables in database '$dbName'..."

# Get list of tables
$tables = & mysql --host=$dbHost --user=$dbUser --password=$dbPass --database=$dbName --skip-column-names -e "SHOW TABLES;"

if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to connect to MySQL or list tables."
    exit 1
}

if ($tables) {
    $dropSql = "SET FOREIGN_KEY_CHECKS=0; " + ($tables -join "; DROP TABLE IF EXISTS ") + "; DROP TABLE IF EXISTS " + $tables[-1] + "; SET FOREIGN_KEY_CHECKS=1;"
    & mysql --host=$dbHost --user=$dbUser --password=$dbPass --database=$dbName -e $dropSql
    if ($LASTEXITCODE -ne 0) {
        Write-Error "Failed to drop tables."
        exit 1
    }
    Write-Host "All tables dropped."
} else {
    Write-Host "No tables to drop."
}

Write-Host "Importing SQL dump..."
Get-Content $sqlFile | & mysql --host=$dbHost --user=$dbUser --password=$dbPass --database=$dbName
if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to import SQL dump."
    exit 1
}
Write-Host "Database import complete. Local DB now mirrors live."
