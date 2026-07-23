# tools/dump.ps1 - DB-Dump mit garantiert korrekter UTF-8-Kodierung
#
# Grund: PowerShell/cmd dekodieren die Ausgabe von mysqldump beim Pipen/Umleiten normalerweise
# ueber die System-OEM-Codepage (auf deutschem/oesterreichischem Windows: 850) statt UTF-8 ->
# Umlaute werden dabei irreversibel als Mojibake in die Datei geschrieben. Realer Vorfall beim
# PC-Umzug 2026-07-17 (siehe .claude/memory/project_infrastruktur.md), 175 kaputte Zeilen quer
# durch die DB, muehsam nachtraeglich per iconv() korrigiert.
#
# Fix: mysqldump wird ueber cmd.exe mit reiner ">"-Umleitung aufgerufen (byte-transparent,
# keine PowerShell-String-Dekodierung dazwischen) statt per PowerShell-Pipe/Out-File.
#
# Aufruf: .\dump.ps1 [-OutFile "D:\pfad\dump.sql"]
# Ohne -OutFile: landet automatisch in D:\ERP\backups\mealana_erp_<Zeitstempel>.sql

param(
    [string]$OutFile = ""
)

$toolsRoot  = $PSScriptRoot
$repoRoot   = Split-Path -Parent $toolsRoot
$erpRoot    = Split-Path -Parent $repoRoot
$configPath = Join-Path $repoRoot "erp\config\database.php"
$mysqldump  = "C:\xampp\mysql\bin\mysqldump.exe"

if (-not (Test-Path $configPath)) {
    Write-Host "FEHLER: $configPath nicht gefunden." -ForegroundColor Red
    exit 1
}
if (-not (Test-Path $mysqldump)) {
    Write-Host "FEHLER: mysqldump nicht gefunden unter $mysqldump" -ForegroundColor Red
    exit 1
}

$json = & php -r "echo json_encode(require '$configPath');"
if ($LASTEXITCODE -ne 0 -or -not $json) {
    Write-Host "FEHLER: config/database.php konnte nicht gelesen werden." -ForegroundColor Red
    exit 1
}
$config = $json | ConvertFrom-Json

if (-not $OutFile) {
    $stempel = Get-Date -Format "yyyyMMdd_HHmmss"
    $backupOrdner = Join-Path $erpRoot "backups"
    if (-not (Test-Path $backupOrdner)) { New-Item -ItemType Directory -Path $backupOrdner | Out-Null }
    $OutFile = Join-Path $backupOrdner "mealana_erp_$stempel.sql"
}

$passwortArg = ""
if ($config.password) { $passwortArg = "-p$($config.password)" }
# -p ohne Wert wuerde mysqldump interaktiv nach dem Passwort fragen -> haengt in einem
# nicht-interaktiven Aufruf fuer immer. Bei leerem Passwort (uebliches lokales XAMPP-Setup)
# deshalb komplett weglassen statt "-p" mit leerem Wert anzuhaengen.

$cmd = "`"$mysqldump`" --default-character-set=utf8mb4 -h $($config.host) -u $($config.username) $passwortArg $($config.dbname) > `"$OutFile`""
cmd /c $cmd

if ($LASTEXITCODE -eq 0) {
    Write-Host "Dump geschrieben nach: $OutFile" -ForegroundColor Green
} else {
    Write-Host "FEHLER beim Dump (Exit-Code $LASTEXITCODE)" -ForegroundColor Red
    exit 1
}
