<?php
/**
 * Nimmt einen missglückten JTL-Import wieder zurück: löscht Artikel (Väter/normale + ihre
 * Kinder), die SEIT einem Zeitpunkt angelegt wurden UND für einen bestimmten Shop-Kanal
 * eingeschaltet sind -- samt Kategorien, Achsen, Preisen, Codes usw.
 *
 * Anlass (2026-11-11): Erster SOW-Import auf Live mit einer Stammdaten-CSV ohne Kinderzeilen
 * ("Stammdaten fehlen") -> Kinder ohne EAN/Gewicht/Einheit; ein erneuter Import ergänzt bei
 * vorhandenen Artikeln nur Kategorien. Also: Artikel weg, sauber neu importieren.
 *
 * SICHERUNGEN -- bricht komplett ab (löscht nichts), wenn bei einem der Artikel:
 *   - der Artikel schon im Shop ist (artikel_shops.external_id gesetzt),
 *   - es Geschäftsdaten gibt (Aufträge, Rechnungen, Bons, Bestellungen, Lagerbewegungen, Inventur, ...),
 *   - Bestand > 0 eingetragen ist.
 * Alles läuft in EINER Transaktion. Ohne --ausfuehren nur Probelauf (zeigt, was gelöscht würde).
 * Vorher trotzdem eine DB-Sicherung machen.
 *
 * Aufruf:
 *   php scripts/import_zuruecknehmen.php --shop=sockenwolle-online --seit="2026-11-11 00:00"
 *   php scripts/import_zuruecknehmen.php --shop=sockenwolle-online --seit="2026-11-11 00:00" --ausfuehren
 * --shop = slug oder Name des Shops (Teil genügt, muss eindeutig sein).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';

$opt = getopt('', ['shop:', 'seit:', 'ausfuehren']);
$ausfuehren = isset($opt['ausfuehren']);
if (empty($opt['shop']) || empty($opt['seit']) || strtotime($opt['seit']) === false) {
    fwrite(STDERR, "Aufruf: php scripts/import_zuruecknehmen.php --shop=<slug|name> --seit=\"JJJJ-MM-TT HH:MM\" [--ausfuehren]\n");
    exit(1);
}
$seit = date('Y-m-d H:i:s', strtotime($opt['seit']));
$db = Database::getInstance();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$st = $db->prepare("SELECT id, slug, name FROM shops WHERE slug = :s OR name = :s OR slug LIKE :l OR name LIKE :l");
$st->execute(['s' => $opt['shop'], 'l' => '%' . $opt['shop'] . '%']);
$shops = $st->fetchAll(PDO::FETCH_ASSOC);
if (count($shops) !== 1) {
    fwrite(STDERR, count($shops) . " Shops passen zu \"{$opt['shop']}\": " . implode(', ', array_column($shops, 'slug')) . " -- bitte eindeutig angeben.\n");
    exit(1);
}
$shop = $shops[0];

// Väter + normale Artikel: seit $seit angelegt und für den Shop eingeschaltet
$st = $db->prepare("
    SELECT a.id, a.artikelnummer, a.name, a.ist_vater FROM artikel a
    WHERE a.vaterartikel_id IS NULL AND a.erstellt_am >= :seit
      AND EXISTS (SELECT 1 FROM artikel_shops s WHERE s.artikel_id = a.id AND s.shop_id = :shop)
    ORDER BY a.artikelnummer");
$st->execute(['seit' => $seit, 'shop' => $shop['id']]);
$haupt = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$haupt) { echo "Keine Artikel für Shop {$shop['slug']} seit $seit gefunden.\n"; exit(0); }

$hauptIds = array_map('intval', array_column($haupt, 'id'));
$kinderIds = array_map('intval', $db->query("SELECT id FROM artikel WHERE vaterartikel_id IN (" . implode(',', $hauptIds) . ")")->fetchAll(PDO::FETCH_COLUMN));
$alle = array_merge($kinderIds, $hauptIds);
$in = implode(',', $alle);

echo "Shop: {$shop['name']} ({$shop['slug']}) · angelegt seit $seit\n";
echo count($haupt) . " Väter/normale Artikel, " . count($kinderIds) . " Kinder = " . count($alle) . " Artikel\n";
foreach (array_slice($haupt, 0, 15) as $h) echo "  {$h['artikelnummer']}  {$h['name']}\n";
if (count($haupt) > 15) echo "  ... und " . (count($haupt) - 15) . " weitere\n";

$tabelleDa = function (string $t) use ($db): bool {
    $s = $db->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $s->execute(['t' => $t]); return (bool) $s->fetchColumn();
};
$zaehle = fn(string $sql) => (int) $db->query($sql)->fetchColumn();

// --- Sicherungen ---
$sperren = [];
$imShop = $zaehle("SELECT COUNT(*) FROM artikel_shops WHERE artikel_id IN ($in) AND external_id IS NOT NULL AND external_id <> ''");
if ($imShop) $sperren[] = "$imShop Artikel sind schon im Shop (external_id) -- dort zuerst löschen";
foreach (['auftrag_positionen', 'rechnung_positionen', 'gutschrift_positionen', 'kassen_bon_positionen', 'bestellung_positionen',
          'haendler_beleg_positionen', 'partner_beleg_positionen', 'inventur_positionen', 'lager_bewegungen', 'reservierungen',
          'kassen_messe_umbuchungen', 'packplatz_ruecklagerungen', 'spenden_log', 'aktionen_artikel_preise', 'preis_aktionen_positionen'] as $t) {
    if (!$tabelleDa($t)) continue;
    $n = $zaehle("SELECT COUNT(*) FROM `$t` WHERE artikel_id IN ($in)");
    if ($n) $sperren[] = "$t: $n Zeilen";
}
if ($tabelleDa('position_konfiguration')) {
    $n = $zaehle("SELECT COUNT(*) FROM position_konfiguration WHERE wert_id IN (SELECT id FROM varianten_achse_werte WHERE artikel_id IN ($in))");
    if ($n) $sperren[] = "position_konfiguration: $n Zeilen";
}
$bestand = $zaehle("SELECT COUNT(*) FROM lagerbestand WHERE artikel_id IN ($in) AND bestand <> 0");
if ($bestand) $sperren[] = "$bestand Lagerbestands-Zeilen mit Bestand <> 0";
if ($sperren) {
    echo "\nABBRUCH -- es wird nichts gelöscht:\n  - " . implode("\n  - ", $sperren) . "\n";
    exit(2);
}

// --- Löschen (Reihenfolge wegen RESTRICT-Fremdschlüsseln) ---
$schritte = [
    ['varianten_kombination_werte', "kombination_id IN ($in)"],
    ['artikel_achsen',              "artikel_id IN ($in)"],
    ['varianten_achse_werte',       "artikel_id IN ($in)"],   // _shops per CASCADE
    ['artikel_kategorien',          "artikel_id IN ($in)"],
    ['artikel_codes',               "artikel_id IN ($in)"],
    ['artikel_externe_referenzen',  "artikel_id IN ($in)"],
    ['artikel_lieferanten',         "artikel_id IN ($in)"],
    ['artikel_merkmale',            "artikel_id IN ($in)"],
    ['artikel_preise',              "artikel_id IN ($in)"],
    ['artikel_staffelpreise',       "artikel_id IN ($in)"],
    ['lagerbestand',                "artikel_id IN ($in)"],   // nur Bestand 0 (geprüft), Lagerplätze per CASCADE
    ['lagerwert_snapshot_positionen', "artikel_id IN ($in)"],
    ['artikel',                     "id IN (" . ($kinderIds ? implode(',', $kinderIds) : '0') . ")"],
    ['artikel',                     "id IN (" . implode(',', $hauptIds) . ")"], // Bilder/Kanäle per CASCADE
];

echo "\n" . ($ausfuehren ? "LÖSCHE:" : "PROBELAUF -- würde löschen:") . "\n";
$db->beginTransaction();
try {
    foreach ($schritte as [$t, $wo]) {
        if (!$tabelleDa($t)) continue;
        $n = $zaehle("SELECT COUNT(*) FROM `$t` WHERE $wo");
        if ($ausfuehren && $n) $db->exec("DELETE FROM `$t` WHERE $wo");
        if ($n) printf("  %-32s %6d\n", $t, $n);
    }
    if ($ausfuehren) { $db->commit(); echo "\nFertig. " . count($alle) . " Artikel gelöscht.\n"; }
    else { $db->rollBack(); echo "\nNichts geändert. Zum Ausführen --ausfuehren anhängen.\n"; }
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, "\nFEHLER, alles zurückgerollt: " . $e->getMessage() . "\n");
    exit(3);
}
