<?php
/**
 * BFR-Nachsignierungs-Cronjob
 *
 * Läuft alle paar Minuten (empfohlen: alle 5 Minuten).
 *
 * Windows Task Scheduler:
 *   Programm:  C:\laragon\bin\php\php-8.3.x\php.exe
 *   Argumente: C:\laragon\htdocs\mealana\erp\cron\bfr_nachsignierung.php
 *
 * Linux crontab (crontab -e):
 *   (jede 5.) * * * * php /var/www/mealana/erp/cron/bfr_nachsignierung.php >> /var/log/mealana_cron.log 2>&1
 *
 * Logik:
 *   Für jede aktive Kasse mit konfigurierter BFR-URL: offene Belege nachsignieren
 *   (BfrService::signiereAusstehende), damit das nicht vom nächsten echten Verkauf
 *   abhängt. Zusätzlich Monats-Nullbeleg absichern, falls die Kasse länger nicht
 *   benutzt wurde. Beides läuft mit derselben Stichtag-/Reihenfolge-Logik wie beim
 *   normalen Verkauf — siehe BfrService.
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/kasse/BfrService.php';

$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log('=== BFR-Nachsignierungs-Cronjob gestartet ===');

$service = new BfrService();

$kassen = $db->query("
    SELECT id, name FROM kassen WHERE aktiv = 1 AND bfr_url IS NOT NULL AND bfr_url != ''
")->fetchAll(PDO::FETCH_ASSOC);
$log('Aktive Kassen mit BFR-Anbindung: ' . count($kassen));

foreach ($kassen as $kasse) {
    $kasseId = (int)$kasse['id'];
    $log("Kasse #{$kasseId} ({$kasse['name']}):");

    try {
        $service->sicherstelleMonatsNullbeleg($kasseId);
    } catch (Throwable $e) {
        $log('  → FEHLER bei Nullbeleg-Check: ' . $e->getMessage());
    }

    try {
        $ergebnis = $service->signiereAusstehende($kasseId, 'cronjob');
        if (!$ergebnis['ausgefuehrt']) {
            $log('  → nicht ausgeführt: ' . ($ergebnis['grund'] ?? 'unbekannt'));
        } elseif ($ergebnis['signiert'] > 0 || $ergebnis['fehlgeschlagen'] > 0) {
            $log("  → signiert: {$ergebnis['signiert']}, fehlgeschlagen: {$ergebnis['fehlgeschlagen']}");
        } else {
            $log('  → nichts offen');
        }
    } catch (Throwable $e) {
        $log('  → FEHLER bei Nachsignierung: ' . $e->getMessage());
    }
}

$log('=== BFR-Nachsignierungs-Cronjob abgeschlossen ===');
