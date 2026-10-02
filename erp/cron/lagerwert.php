<?php
/**
 * Lagerwert-Cronjob — hält am letzten Tag jedes Monats den Lagerwert fest.
 *
 * Läuft täglich, möglichst spät (empfohlen: 23:30 Uhr) — an allen anderen Tagen tut er nichts.
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\lagerwert.php
 *
 * Linux crontab (crontab -e):
 *   30 23 * * * php /var/www/mealana/erp/cron/lagerwert.php >> /var/log/mealana_cron.log 2>&1
 *
 * Manuell für Tests: php lagerwert.php --jetzt  (hält sofort fest, auch mitten im Monat,
 * und nur wenn für diesen Monat noch kein Monatsende-Wert existiert)
 *
 * Bewertung und Umfang: siehe LagerwertService.
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/statistik/LagerwertService.php';

$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$jetzt = in_array('--jetzt', $argv ?? [], true);
if (!$jetzt && date('j') !== date('t')) {
    exit(0); // nicht der letzte Tag des Monats
}

// Logger::log() fällt ohne expliziten Wert auf $_SESSION zurück, die es im Cron nicht gibt
$jarvisId = (int) $db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();

$service = new LagerwertService();
$monat   = date('Y-m');

if ($service->monatsendeVorhanden($monat)) {
    $log("Lagerwert für $monat ist schon festgehalten — nichts zu tun.");
    exit(0);
}

try {
    $id = $service->festhalten('monatsende', null, $jarvisId ?: null);
    $s  = $service->findSnapshot($id);
    $log(sprintf('Lagerwert %s festgehalten: %s € (davon Händler %s €), %d Artikel, %d ohne EK.',
        $monat,
        number_format((float)$s['wert_gesamt'], 2, ',', '.'),
        number_format((float)$s['wert_haendler'], 2, ',', '.'),
        $s['artikel_anzahl'],
        $s['artikel_ohne_ek']
    ));
} catch (Throwable $e) {
    Logger::log('lagerwert.fehler', null, null, ['anlass' => 'monatsende', 'fehler' => $e->getMessage()], $jarvisId ?: null, 'error');
    $log('FEHLER: ' . $e->getMessage());
    exit(1);
}
