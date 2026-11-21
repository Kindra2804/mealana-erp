<?php
/**
 * Demo-Datenbank neu erzeugen (nur in einer Demo-Installation, siehe src/core/Demo.php).
 *
 *   php scripts/demo/demo_erzeugen.php
 *
 * Löscht ALLE Tabellen der konfigurierten Datenbank, spielt Grundschema + Migrationen ein und
 * füllt sie mit erfundenen Daten der „Garnstube Muster GmbH“ (Datum immer relativ zu heute).
 * Läuft jede Nacht um 0:00 per Cron/Aufgabenplanung.
 *
 * Neue Seite oder Funktion im ERP? Dann hier passende Demo-Daten ergänzen
 * (Regel siehe Memory „feedback-demo-nachziehen“).
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Nur über die Kommandozeile.'); }

require_once __DIR__ . '/../../src/core/Demo.php';
if (!Demo::aktiv()) {
    fwrite(STDERR, "Abbruch: Das ist keine Demo-Installation (config/demo.php fehlt oder 'aktiv' => false).\n"
                 . "Dieses Skript löscht die komplette Datenbank und darf nur in der Demo laufen.\n");
    exit(1);
}

require_once __DIR__ . '/../../src/core/Lizenz.php';
require_once __DIR__ . '/../../src/modules/einrichtung/OnboardingService.php';
require_once __DIR__ . '/DemoBasis.php';
require_once __DIR__ . '/DemoStammdaten.php';
require_once __DIR__ . '/DemoArtikel.php';
require_once __DIR__ . '/DemoKunden.php';
require_once __DIR__ . '/DemoVerkauf.php';

$start = microtime(true);
// Fester Startwert: jeden Tag dieselbe „Geschichte“, nur zeitlich mitgewandert
$b = new DemoBasis(20261121);
$b->log('Demo-Datenbank wird neu erzeugt …');
$b->neuAufsetzen();
(new DemoStammdaten($b))->erzeugen();
(new DemoArtikel($b))->erzeugen();
(new DemoKunden($b))->erzeugen();
(new DemoVerkauf($b))->erzeugen();
$b->log(sprintf('Fertig in %.1f s', microtime(true) - $start));
