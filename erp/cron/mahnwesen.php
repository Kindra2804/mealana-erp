<?php
/**
 * Mahnwesen-Cronjob
 *
 * Läuft täglich (empfohlen: 06:00 Uhr).
 *
 * Windows Task Scheduler:
 *   Programm:  C:\laragon\bin\php\php-8.3.x\php.exe
 *   Argumente: C:\laragon\htdocs\mealana\erp\cron\mahnwesen.php
 *
 * Linux crontab (crontab -e):
 *   0 6 * * * php /var/www/mealana/erp/cron/mahnwesen.php >> /var/log/mealana_cron.log 2>&1
 *
 * Logik:
 *   14+ Tage ohne Zahlung → Erinnerungsmail (einmal)
 *   30+ Tage ohne Zahlung → Automatische Stornierung + Lagerrückbuchung
 */

define('CRON_RUN', true);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Mailer.php';
require_once __DIR__ . '/../src/core/Logger.php';

$db  = Database::getInstance();
$log = fn(string $msg) => print('[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL);

$log('=== Mahnwesen-Cronjob gestartet ===');

// Alle offenen Aufträge mit Zahlungsrückstand (Vorkasse + Rechnung)
// Vorkasse:  30 Tage → Auto-Stornierung (Ware noch nicht weg)
// Rechnung:  30 Tage → nur Hinweis, KEIN Auto-Storno (Ware evtl. schon versendet!)
$offene = $db->query("
    SELECT
        a.id,
        a.auftragsnummer,
        a.erstellt_am,
        a.bruttobetrag,
        a.zahlungsart,
        a.kunden_id,
        a.kunden_snapshot,
        DATEDIFF(NOW(), a.erstellt_am) AS tage_offen,
        k.email AS kunden_email,
        (SELECT COUNT(*) FROM mahnungen m WHERE m.auftrag_id = a.id AND m.typ = 'erinnerung')   AS erinnerung_gesendet,
        (SELECT COUNT(*) FROM mahnungen m WHERE m.auftrag_id = a.id AND m.typ = 'stornierung')  AS stornierung_gesendet,
        (SELECT COUNT(*) FROM mahnungen m WHERE m.auftrag_id = a.id AND m.typ = 'hinweis')      AS hinweis_gesendet
    FROM auftraege a
    LEFT JOIN kunden k ON k.id = a.kunden_id
    WHERE a.zahlungsart IN ('vorkasse', 'rechnung')
      AND a.zahlungsstatus IN ('offen', 'ausstehend')
      AND a.lieferstatus NOT IN ('storniert', 'abgeschlossen')
    ORDER BY a.erstellt_am ASC
")->fetchAll(PDO::FETCH_ASSOC);

$log('Gefundene überfällige Aufträge: ' . count($offene));

$firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);

foreach ($offene as $auftrag) {
    $tage    = (int)$auftrag['tage_offen'];
    $id      = (int)$auftrag['id'];
    $nummer  = $auftrag['auftragsnummer'];

    // Kunden-Snapshot für Namen + Fallback-Email
    $snapshot   = !empty($auftrag['kunden_snapshot']) ? json_decode($auftrag['kunden_snapshot'], true) : [];
    $kundeName  = trim(($snapshot['vorname'] ?? '') . ' ' . ($snapshot['nachname'] ?? ''));
    if (!$kundeName) $kundeName = $snapshot['firma'] ?? 'Kunde';
    $email      = $auftrag['kunden_email'] ?: ($snapshot['email'] ?? '');

    $istVorkasse = $auftrag['zahlungsart'] === 'vorkasse';
    $istRechnung = $auftrag['zahlungsart'] === 'rechnung';

    // ─── 30+ Tage, VORKASSE → AUTOMATISCHE STORNIERUNG ──────────────────────
    // Bei Rechnung: Ware evtl. schon versendet → kein Auto-Storno, nur Hinweis!
    if ($tage >= 30 && $istVorkasse && !$auftrag['stornierung_gesendet']) {
        $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — STORNIERUNG");

        $db->beginTransaction();
        try {
            // Auftrag stornieren
            $db->prepare("
                UPDATE auftraege
                SET lieferstatus = 'storniert', zahlungsstatus = 'storniert', aktualisiert_am = NOW()
                WHERE id = ?
            ")->execute([$id]);

            // Lagerbestand zurückbuchen
            $positionen = $db->prepare("SELECT artikel_id, menge FROM auftrag_positionen WHERE auftrag_id = ? AND artikel_id IS NOT NULL");
            $positionen->execute([$id]);
            foreach ($positionen->fetchAll(PDO::FETCH_ASSOC) as $pos) {
                $db->prepare("
                    UPDATE lagerbestand SET bestand = bestand + ? WHERE artikel_id = ? AND lager_id = 1
                ")->execute([$pos['menge'], $pos['artikel_id']]);
                $db->prepare("
                    INSERT INTO lager_bewegungen (artikel_id, lager_id, typ, menge, referenz_typ, referenz_id, erstellt_am)
                    VALUES (?, 1, 'eingang', ?, 'auftrag', ?, NOW())
                ")->execute([$pos['artikel_id'], $pos['menge'], $id]);
            }

            // Mahnung-Log
            $db->prepare("
                INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
                VALUES (?, 'stornierung', ?, 'cronjob')
            ")->execute([$id, $email]);

            // Statuslog
            $db->prepare("
                INSERT INTO auftrag_status_log (auftrag_id, aktion, erstellt_am)
                VALUES (?, 'Automatisch storniert (30 Tage unbezahlt — Mahnwesen-Cronjob)', NOW())
            ")->execute([$id]);

            $db->commit();

            // Mail senden
            if ($email) {
                try {
                    $mailer = new Mailer();
                    $mailer->sendeTemplate(
                        empfaenger:  $email,
                        betreff:     'Ihr Auftrag ' . $nummer . ' wurde storniert',
                        templatePfad: 'mails/mahnwesen/stornierung.html.twig',
                        variablen: [
                            'kunde_name'    => $kundeName,
                            'auftrag_nummer'=> $nummer,
                            'auftrag_datum' => date('d.m.Y', strtotime($auftrag['erstellt_am'])),
                            'betrag'        => number_format((float)$auftrag['bruttobetrag'], 2, ',', '.'),
                            'firma_email'   => $firma['email'] ?? '',
                        ]
                    );
                    $log("  → Stornierungsmail gesendet an: {$email}");
                } catch (Throwable $e) {
                    $log("  → FEHLER beim Mailversand: " . $e->getMessage());
                }
            } else {
                $log("  → Keine E-Mail-Adresse — Mail übersprungen");
            }

            Logger::log('mahnwesen.stornierung', 'auftraege', $id, ['nummer' => $nummer, 'tage' => $tage]);
        } catch (Throwable $e) {
            $db->rollBack();
            $log("  → FEHLER bei Stornierung: " . $e->getMessage());
        }
        continue;
    }

    // ─── 30+ Tage, RECHNUNG → nur Hinweis im Log (kein Auto-Storno!) ────────
    if ($tage >= 30 && $istRechnung && !$auftrag['hinweis_gesendet']) {
        $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — RECHNUNG ÜBERFÄLLIG (manuell prüfen, kein Auto-Storno)");

        $db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
            VALUES (?, 'hinweis', ?, 'cronjob')
        ")->execute([$id, $email]);

        $db->prepare("
            INSERT INTO auftrag_status_log (auftrag_id, aktion, erstellt_am)
            VALUES (?, 'Rechnung 30+ Tage unbezahlt — bitte manuell prüfen (kein Auto-Storno bei Rechnungszahlern)', NOW())
        ")->execute([$id]);

        Logger::log('mahnwesen.rechnung_ueberfaellig', 'auftraege', $id, ['nummer' => $nummer, 'tage' => $tage]);
        continue;
    }

    // ─── 14+ Tage → ERINNERUNG (nur einmal, gilt für Vorkasse + Rechnung) ───
    if ($tage >= 14 && !$auftrag['erinnerung_gesendet']) {
        $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — ERINNERUNGSMAIL");

        // Fälligkeitsdatum = +30 Tage ab Auftragsdatum
        $faelligAm = date('d.m.Y', strtotime($auftrag['erstellt_am'] . ' +30 days'));

        // Mahnung-Log
        $db->prepare("
            INSERT INTO mahnungen (auftrag_id, typ, mail_an, erstellt_von)
            VALUES (?, 'erinnerung', ?, 'cronjob')
        ")->execute([$id, $email]);

        if ($email) {
            try {
                $mailer = new Mailer();
                $mailer->sendeTemplate(
                    empfaenger:  $email,
                    betreff:     'Zahlungserinnerung: Auftrag ' . $nummer,
                    templatePfad: 'mails/mahnwesen/erinnerung.html.twig',
                    variablen: [
                        'kunde_name'    => $kundeName,
                        'auftrag_nummer'=> $nummer,
                        'auftrag_datum' => date('d.m.Y', strtotime($auftrag['erstellt_am'])),
                        'betrag'        => number_format((float)$auftrag['bruttobetrag'], 2, ',', '.'),
                        'zahlungsart'   => 'Rechnung',
                        'faellig_am'    => $faelligAm,
                        'firma_email'   => $firma['email'] ?? '',
                    ]
                );
                $log("  → Erinnerungsmail gesendet an: {$email}");
            } catch (Throwable $e) {
                $log("  → FEHLER beim Mailversand: " . $e->getMessage());
            }
        } else {
            $log("  → Keine E-Mail-Adresse — Erinnerung nur geloggt");
        }

        Logger::log('mahnwesen.erinnerung', 'auftraege', $id, ['nummer' => $nummer, 'tage' => $tage]);
        continue;
    }

    $log("Auftrag #{$id} ({$nummer}): {$tage} Tage — noch keine Aktion nötig");
}

$log('=== Mahnwesen-Cronjob abgeschlossen ===');
