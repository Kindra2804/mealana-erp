<?php
/**
 * BFR-Ausfall-Recovery-Cronjob
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
 * Logik (seit 2026-07-06 — State-Check-Gate ersetzt die alte Nachsignierungs-
 * Warteschlange, siehe BfrService): jeder Verkauf/Storno prüft die Erreichbarkeit
 * selbst VOR der Buchung, ein Beleg bleibt also nie unsigniert hängen. Was bleibt,
 * ist die Kassenstart-Recovery (Nullbeleg, sobald eine offene Ausfall-Episode
 * wieder abgeschlossen werden kann) — die soll aber nicht davon abhängen, dass
 * jemand die Kasse manuell neu startet, falls eine Störung tagelang läuft.
 * Deshalb hier: für jede Kasse mit offener Episode denselben Recovery-Versuch wie
 * beim Kassenstart auslösen. Zusätzlich der normale Monats-Nullbeleg-Check.
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/kasse/BfrService.php';

$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log('=== BFR-Ausfall-Recovery-Cronjob gestartet ===');

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
        $ergebnis = $service->pruefeKassenstart($kasseId);
        if (!$ergebnis['erreichbar']) {
            $log('  → weiterhin nicht erreichbar: ' . ($ergebnis['grund'] ?? 'unbekannt'));
        } elseif ($service->offeneEpisode($kasseId)) {
            $log('  → erreichbar, Störung läuft aber weiter (Sicherheitseinrichtung noch ausgefallen)');
        } else {
            $log('  → erreichbar, keine offene Störung');
        }
    } catch (Throwable $e) {
        $log('  → FEHLER bei Ausfall-Recovery: ' . $e->getMessage());
    }
}

$log('=== BFR-Ausfall-Recovery-Cronjob abgeschlossen ===');
