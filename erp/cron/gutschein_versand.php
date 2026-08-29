<?php
/**
 * Gutschein-Versand-Cronjob
 *
 * Läuft täglich (empfohlen: gemeinsam mit Mahnwesen, 06:00 Uhr).
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\gutschein_versand.php
 *
 * Verschickt Gutscheine mit geplantem Zustelldatum (gutscheine.zustellung_am,
 * z.B. "erst am Geburtstag zustellen") -- alle sofort zu versendenden Gutscheine
 * (zustellung_am = NULL) laufen bereits direkt über GutscheinService::versende()
 * am Entstehungspunkt (Kasse/ERP/Bestellungs-Sync), landen NIE hier.
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/gutscheine/GutscheinRepository.php';
require_once __DIR__ . '/../src/modules/gutscheine/GutscheinService.php';

$jarvisId = (int)Database::getInstance()
    ->query("SELECT id FROM benutzer WHERE username = 'system'")
    ->fetchColumn();

$repo = new GutscheinRepository();
$service = new GutscheinService();

$faellige = $repo->findFaelligeZustellungen();
$erfolg = 0;
$fehler = 0;

foreach ($faellige as $gutschein) {
    try {
        $service->versende((int)$gutschein['id']);
        $erfolg++;
    } catch (Throwable $e) {
        Logger::log('gutschein.versand_fehler', 'gutscheine', (int)$gutschein['id'], [
            'fehler' => $e->getMessage(),
        ], $jarvisId, 'error');
        $fehler++;
    }
}

echo "Gutschein-Versand: $erfolg erfolgreich, $fehler Fehler\n";
