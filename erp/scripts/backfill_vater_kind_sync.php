<?php
/**
 * Einmaliges Backfill-Werkzeug: ruft ArtikelRepository::propagiereZuKindern()
 * für alle Vater-Artikel auf, deren Kinder bei mindestens einem der normal
 * vererbten Felder vom Vater abweichen (siehe Docblock dort für die volle
 * Feldliste: hersteller/steuerklasse/artikelgruppe/artikeltyp/Beschreibungen/
 * Meta/einheit/inhalt/gewicht/Maße/herkunftsland/taric/grundpreis/
 * charge_pflicht/ueberverkauf_erlaubt).
 *
 * Gefunden 2026-08-11 (Nachfund zum Artikelgruppen-Bug): 841 JTL-importierte
 * Väter wurden seit ihrer Anlage nie einzeln in der UI gespeichert -- die
 * Vererbung lief für ihre Kinder deshalb nie, auch nicht für charge_pflicht
 * (1.177 Kinder ohne Chargenpflicht trotz Vater=Pflicht -- Farbkonsistenz-
 * Risiko beim Wareneingang!) und grundpreis_anzeigen (1.565 Kinder ohne
 * gesetzlich vorgeschriebene Grundpreisangabe).
 *
 * Nutzt bewusst die bestehende Produktions-Methode statt eigener Feldliste --
 * exakt dasselbe Ergebnis wie ein normales Vater-Speichern in der UI.
 *
 * Aufruf:
 *   php scripts/backfill_vater_kind_sync.php [--dry-run]
 */

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/artikel/ArtikelRepository.php';

$dryRun = in_array('--dry-run', $argv, true);
$db = Database::getInstance();
$repo = new ArtikelRepository();

$felder = [
    'hersteller_id', 'steuerklasse_id', 'artikel_gruppe_id', 'artikeltyp_id',
    'kurzbeschreibung', 'beschreibung', 'technische_details', 'beschreibung_intern',
    'meta_titel', 'meta_description', 'einheit_id', 'inhalt_menge', 'inhalt_einheit',
    'gewicht_artikel', 'gewicht_versand', 'laenge', 'breite', 'hoehe',
    'herkunftsland', 'taric_code', 'grundpreis_bezugsmenge', 'grundpreis_anzeigen',
    'charge_pflicht', 'ueberverkauf_erlaubt',
];
$bedingung = implode(' OR ', array_map(fn($f) => "NOT (a.$f <=> vater.$f)", $felder));

$vaterIds = $db->query("
    SELECT DISTINCT vater.id
    FROM artikel a JOIN artikel vater ON vater.id = a.vaterartikel_id
    WHERE $bedingung
")->fetchAll(PDO::FETCH_COLUMN);

echo count($vaterIds) . " Vater-Artikel mit mindestens einem abweichenden Kind gefunden.\n";

if (!$dryRun) {
    foreach ($vaterIds as $vaterId) {
        $repo->propagiereZuKindern((int)$vaterId);
    }
}

echo ($dryRun ? '[DRY RUN] ' : '') . count($vaterIds) . " Väter " . ($dryRun ? 'würden' : 'wurden') . " propagiert.\n";
