<?php
/**
 * Mahnwesen-Cronjob
 *
 * Läuft täglich (empfohlen: 06:00 Uhr).
 *
 * Windows Task Scheduler:
 *   Programm:  C:\xampp\php\php.exe
 *   Argumente: D:\ERP\mealana\erp\cron\mahnwesen.php
 *
 * Linux crontab (crontab -e):
 *   0 6 * * * php /var/www/mealana/erp/cron/mahnwesen.php >> /var/log/mealana_cron.log 2>&1
 *
 * Logik (Details siehe MahnwesenService):
 *   Vorkasse (ab Bestelldatum):
 *     14+ Tage ohne Zahlung → Erinnerungsmail (einmal)
 *     30+ Tage ohne Zahlung → automatische Stornierung (Reservierungen frei, kein Lagerbuchen)
 *   Rechnung (ab Fälligkeit der Rechnung, Fristen unter Einstellungen → System → Mahnwesen):
 *     Erinnerung automatisch (Vorgabe 7 Tage), 1. und 2. Mahnung nur als VORSCHLAG
 *     (je 14 Tage nach der Vorstufe) — Freigabe auf Verkauf → Mahnwesen. Kein Auto-Storno.
 *
 * Dieselbe Logik (MahnwesenService) wird auch vom manuellen "Erinnerung senden"/
 * "Stornieren?"-Button im Dashboard verwendet (public/auftraege/mahnung_manuell_ajax.php).
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/auftraege/MahnwesenService.php';

$db  = Database::getInstance();
$mahnwesen = new MahnwesenService();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

// Logger::log() faellt ohne expliziten Wert auf $_SESSION zurueck, die es im
// Cron-Kontext nicht gibt -> Jarvis-ID muss hier immer explizit mitgegeben werden.
$jarvisId = (int) $db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();

$log('=== Mahnwesen-Cronjob gestartet ===');

// Vorkasse: offene Aufträge nach Bestellalter
$offene = $db->query("
    SELECT
        a.id,
        a.auftrag_nr,
        a.zahlungsart,
        DATEDIFF(NOW(), a.erstellt_am) AS tage_offen,
        (SELECT COUNT(*) FROM mahnungen m WHERE m.auftrag_id = a.id AND m.typ = 'erinnerung')   AS erinnerung_gesendet,
        (SELECT COUNT(*) FROM mahnungen m WHERE m.auftrag_id = a.id AND m.typ = 'stornierung')  AS stornierung_gesendet
    FROM auftraege a
    WHERE a.zahlungsart = 'vorkasse'
      AND a.zahlungsstatus IN ('ausstehend', 'teilbezahlt')
      AND a.lieferstatus NOT IN ('storniert', 'abgeschlossen')
    ORDER BY a.erstellt_am ASC
")->fetchAll(PDO::FETCH_ASSOC);

$log('Offene Vorkasse-Aufträge: ' . count($offene));

foreach ($offene as $auftrag) {
    $tage    = (int)$auftrag['tage_offen'];
    $id      = (int)$auftrag['id'];
    $nummer  = $auftrag['auftrag_nr'];

    // ─── 30+ Tage, VORKASSE → AUTOMATISCHE STORNIERUNG ──────────────────────
    if ($tage >= 30 && !$auftrag['stornierung_gesendet']) {
        $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — STORNIERUNG");
        $ergebnis = $mahnwesen->storniere($id, $jarvisId, 'cronjob');
        $log($ergebnis['erfolg']
            ? '  → storniert' . ($ergebnis['mail_gesendet'] ? ', Mail gesendet' : '')
            : '  → FEHLER: ' . $ergebnis['fehler']);
        continue;
    }

    // ─── 14+ Tage → ERINNERUNG (nur einmal) ──────────────────────────────────
    if ($tage >= 14 && !$auftrag['erinnerung_gesendet']) {
        $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — ERINNERUNGSMAIL");
        $ergebnis = $mahnwesen->sendeErinnerung($id, $jarvisId, 'cronjob');
        $log($ergebnis['erfolg']
            ? '  → Erinnerung gesendet' . ($ergebnis['mail_gesendet'] ? ' (Mail raus)' : ' (keine Mail-Adresse)')
            : '  → FEHLER: ' . $ergebnis['fehler']);
        continue;
    }

    $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — noch keine Aktion nötig");
}

// Rechnung: Erinnerung automatisch, Mahnstufen als Vorschlag zur Freigabe
$r = $mahnwesen->pruefeRechnungen($jarvisId);
$log("Rechnungen: {$r['erinnerungen']} Erinnerung(en) gesendet, {$r['vorschlaege']} Mahnung(en) zur Freigabe vorgeschlagen");

$log('=== Mahnwesen-Cronjob abgeschlossen ===');
