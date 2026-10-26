<?php
/**
 * Fachmiete-Cronjob (Partner-Abrechnung Schritt 4)
 *
 * Läuft täglich (empfohlen: 06:00 Uhr) — tut nur etwas, wenn ein Monat fällig ist.
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\fachmiete.php
 *
 * Linux crontab (crontab -e):
 *   0 6 * * * php /var/www/mealana/erp/cron/fachmiete.php >> /var/log/mealana_cron.log 2>&1
 *
 * Logik (Details siehe MietRechnungService): monatlich im Voraus, der Folgemonat wird ab
 * 2 Tage vor Monatsende verrechnet (Einstellung fachmiete_vorlauf_tage). Je Partner und
 * Monat eine Rechnung mit allen Fächern; verpasste Monate werden nachgeholt, nie doppelt.
 * Derselbe Lauf geht auch per Knopf: Partner → Mietfächer → "Jetzt verrechnen".
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/partner/MietRechnungService.php';

$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

// Kein $_SESSION im Cron → Systembenutzer explizit mitgeben (wie cron/mahnwesen.php)
$systemId = (int)$db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
$_SESSION = ['benutzer' => ['id' => $systemId]];

$svc = new MietRechnungService();
$log('=== Fachmiete gestartet (verrechnet bis ' . $svc->zielMonat() . ') ===');
$r = $svc->verrechnen($systemId);
foreach ($r['rechnungen'] as $re) {
    $log(sprintf('Rechnung %s: %s, %s, %d Fach/Fächer, € %.2f', $re['rechnung_nr'], $re['partner'], $re['monat'], $re['faecher'], $re['brutto']));
}
foreach ($r['fehler'] as $f) {
    $log('FEHLER: ' . $f);
}
$log('=== Fertig: ' . count($r['rechnungen']) . ' Rechnung(en), ' . count($r['fehler']) . ' Fehler ===');
