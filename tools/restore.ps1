# tools/restore.ps1 - DB-Restore mit garantiert korrekter UTF-8-Kodierung + Sicherheitscheck
#
# Gleicher Encoding-Fix wie dump.ps1: mysql-Client wird ueber cmd.exe mit reiner "<"-Umleitung
# aufgerufen (byte-transparent), nicht per PowerShell-Pipe -- sonst droht wieder CP850-Mojibake,
# siehe .claude/memory/project_infrastruktur.md.
#
# Zusaetzlich: prueft die Datei vorher auf USE/CREATE DATABASE-Statements. Ein fremder Voll-Dump
# (z.B. mit --all-databases erzeugt) kann damit eine ANDERE Datenbank treffen als angenommen.
# Realer Beinahe-Vorfall 2026-07-17: die LIVE-DB wurde dadurch kurz mit einem 11 Tage alten
# Test-Dump ueberschrieben, nur per Zufall existierte ein frisches Backup.
#
# Aufruf: .\restore.ps1 -InFile "D:\pfad\dump.sql"

param(
    [Parameter(Mandatory = $true)][string]$InFile
)

$toolsRoot  = $PSScriptRoot
$repoRoot   = Split-Path -Parent $toolsRoot
$configPath = Join-Path $repoRoot "erp\config\database.php"
$mysql      = "C:\xampp\mysql\bin\mysql.exe"

if (-not (Test-Path $InFile)) {
    Write-Host "FEHLER: $InFile nicht gefunden." -ForegroundColor Red
    exit 1
}
if (-not (Test-Path $configPath)) {
    Write-Host "FEHLER: $configPath nicht gefunden." -ForegroundColor Red
    exit 1
}
if (-not (Test-Path $mysql)) {
    Write-Host "FEHLER: mysql-Client nicht gefunden unter $mysql" -ForegroundColor Red
    exit 1
}

$treffer = Select-String -Path $InFile -Pattern '^(USE |CREATE DATABASE)' -CaseSensitive
if ($treffer) {
    Write-Host "WARNUNG: Die Datei enthaelt USE/CREATE DATABASE-Statements:" -ForegroundColor Yellow
    $treffer | ForEach-Object { Write-Host "  $($_.Line)" -ForegroundColor Yellow }
    Write-Host "Das kann eine ANDERE Datenbank treffen als die hier konfigurierte." -ForegroundColor Yellow
    $antwort = Read-Host "Trotzdem fortfahren? (ja/nein)"
    if ($antwort -ne "ja") { Write-Host "Abgebrochen."; exit 1 }
}

$json = & php -r "echo json_encode(require '$configPath');"
if ($LASTEXITCODE -ne 0 -or -not $json) {
    Write-Host "FEHLER: config/database.php konnte nicht gelesen werden." -ForegroundColor Red
    exit 1
}
$config = $json | ConvertFrom-Json

Write-Host "Restore nach '$($config.dbname)' auf '$($config.host)' aus $InFile ..." -ForegroundColor Cyan
$antwort2 = Read-Host "Sicher? Bestehende Daten koennen ueberschrieben werden (ja/nein)"
if ($antwort2 -ne "ja") { Write-Host "Abgebrochen."; exit 1 }

$passwortArg = ""
if ($config.password) { $passwortArg = "-p$($config.password)" }
# -p ohne Wert wuerde mysql interaktiv nach dem Passwort fragen -> haengt in einem
# nicht-interaktiven Aufruf fuer immer. Bei leerem Passwort (uebliches lokales XAMPP-Setup)
# deshalb komplett weglassen statt "-p" mit leerem Wert anzuhaengen.

$cmd = "`"$mysql`" --default-character-set=utf8mb4 -h $($config.host) -u $($config.username) $passwortArg $($config.dbname) < `"$InFile`""
cmd /c $cmd

if ($LASTEXITCODE -eq 0) {
    Write-Host "Restore abgeschlossen." -ForegroundColor Green
} else {
    Write-Host "FEHLER beim Restore (Exit-Code $LASTEXITCODE)" -ForegroundColor Red
    exit 1
}
