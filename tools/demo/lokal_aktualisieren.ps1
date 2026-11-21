# Demo-Installation auf diesem Rechner mit dem aktuellen Code aus dem Repo abgleichen
# und (optional) die Demo-Daten neu erzeugen.
#
#   powershell -ExecutionPolicy Bypass -File D:\ERP\mealana\tools\demo\lokal_aktualisieren.ps1
#   ... -NurCode        (nur Code spiegeln, Daten bleiben)
#
# Ziel: D:\ERP\nahtlos-demo\erp  ->  http://localhost/nahtlos-demo/
# Erste Einrichtung siehe tools\demo\README.md

param([switch]$NurCode)

$quelle = 'D:\ERP\mealana\erp'
$ziel   = 'D:\ERP\nahtlos-demo\erp'
$php    = 'C:\xampp\php\php.exe'

# Code spiegeln. Nicht anfassen: Konfiguration der Demo, ihre Uploads/Belege/Logos.
# (/XD und /XF schützen die Ordner/Dateien auch vor dem Löschen durch /MIR)
robocopy $quelle $ziel /MIR /NFL /NDL /NJH /NP `
    /XD "$quelle\storage" "$quelle\public\uploads" "$quelle\public\img\logos" "$ziel\storage" "$ziel\public\uploads" "$ziel\public\img\logos" `
    /XF "$ziel\config\database.php" "$ziel\config\encryption.php" "$ziel\config\demo.php" `
        "$quelle\config\database.php" "$quelle\config\encryption.php" | Out-Null
# Achtung: /XF ohne Pfad wäre unter Windows unabhängig von Groß/klein ("demo.php" träfe auch src\core\Demo.php)
if ($LASTEXITCODE -ge 8) { Write-Error "robocopy fehlgeschlagen ($LASTEXITCODE)"; exit 1 }
Write-Host "Code abgeglichen -> $ziel"

if (-not $NurCode) {
    & $php "$ziel\scripts\demo\demo_erzeugen.php"
}
