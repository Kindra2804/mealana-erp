<?php
/**
 * Newsletter-Versand-Cronjob
 *
 * Läuft alle 5 Minuten.
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\newsletter_versand.php
 *
 * Verschickt gestartete Newsletter aus der Warteschlange — höchstens „newsletter_max_pro_stunde“ (Standard 100)
 * je Postfach, gleichmäßig über die Stunde verteilt — und holt die Klick-Summen von der Belegseite.
 * Ohne laufenden Newsletter passiert nichts. Mailversand muss eingeschaltet sein (Einstellungen → Mail).
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Logger.php';
require_once __DIR__ . '/../src/modules/newsletter/NewsletterVersandService.php';

$jarvisId = (int)Database::getInstance()->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();

// nie zwei Läufe gleichzeitig (sonst doppelte Mails)
$sperre = fopen(__DIR__ . '/../storage/newsletter_versand.lock', 'c');
if (!$sperre || !flock($sperre, LOCK_EX | LOCK_NB)) {
    echo "Newsletter-Versand läuft bereits.\n";
    exit(0);
}

try {
    $svc = new NewsletterVersandService();
    $erg = $svc->senden(function (string $zeile) { echo $zeile . "\n"; });
    echo "Newsletter-Versand: {$erg['gesendet']} verschickt, {$erg['fehler']} Fehler, {$erg['uebersprungen']} übersprungen (abgemeldet)\n";
    $k = $svc->klicksHolen();
    if ($k) echo "Klicks für $k Newsletter aktualisiert\n";
} catch (Throwable $e) {
    Logger::log('newsletter.cron_fehler', null, null, ['fehler' => $e->getMessage()], $jarvisId, 'error');
    echo "Newsletter-Versand abgebrochen: {$e->getMessage()}\n";
} finally {
    flock($sperre, LOCK_UN);
    fclose($sperre);
}
