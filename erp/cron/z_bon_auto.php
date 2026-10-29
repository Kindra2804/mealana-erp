<?php
/**
 * Automatischer Z-Bon (Tagesabschluss) um Mitternacht
 *
 * Für jeden vergangenen Tag mit Umsatz, an dem niemand (mit ausreichenden Rechten) einen
 * Z-Bon gemacht hat, erstellt der Systembenutzer Jarvis den Z-Bon — der Reihe nach, ältester
 * Tag zuerst, damit die Z-Bon-Nummern in Tagesreihenfolge bleiben (KassenService::zReihenfolgeFehler).
 * Der heutige Tag wird nie angefasst.
 *
 * Windows Task Scheduler (täglich 00:05):
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\z_bon_auto.php
 *
 * Linux crontab:
 *   5 0 * * * php /var/www/mealana/erp/cron/z_bon_auto.php >> /var/log/mealana_cron.log 2>&1
 *
 * --dry-run: zeigt nur, welche Z-Bons erstellt würden.
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/kasse/KassenService.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

// Kein $_SESSION im Cron → Systembenutzer explizit mitgeben (wie cron/fachmiete.php)
$systemId = (int)$db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
$_SESSION = ['benutzer' => ['id' => $systemId]];

$svc    = new KassenService();
$heute  = date('Y-m-d');
$anzahl = 0;
$log('=== Automatischer Z-Bon gestartet' . ($dryRun ? ' (Probelauf)' : '') . ' ===');

foreach ($db->query("SELECT id, kasse_nr, name FROM kassen WHERE aktiv = 1 ORDER BY id")->fetchAll() as $k) {
    foreach ($svc->offeneZTage((int)$k['id'], $heute) as $tag) {
        if ($dryRun) {
            $log(sprintf('%s (%s): Z-Bon für %s würde erstellt', $k['kasse_nr'], $k['name'], $tag));
            $anzahl++;
            continue;
        }
        $r = $svc->erstelleZBon((int)$k['id'], $systemId, $tag);
        if (!$r['erfolg']) {
            $log(sprintf('FEHLER %s, %s: %s', $k['kasse_nr'], $tag, $r['fehler']));
            break; // Reihenfolge: ohne diesen Tag keine späteren
        }
        $log(sprintf('%s: Z-Bon %s für %s, Umsatz € %.2f', $k['kasse_nr'], $r['bon_nr'], $tag, $r['kennzahlen']['umsatz_gesamt']));
        $anzahl++;
    }
}
$log('=== Fertig: ' . $anzahl . ' Z-Bon(s) ===');
