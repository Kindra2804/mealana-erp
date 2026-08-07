<?php
/**
 * Kopiert aus public/uploads/artikel/ nur die Bilder-Ordner der Artikel, die
 * für einen bestimmten Shop tatsächlich im Kanal aktiv sind -- Alternative
 * zum kompletten "gesamten Ordner per FTP hochladen" aus dem Infokasten der
 * Shop-Synchronisierung-Seite, gedacht für kleinere Shops, die nur einen
 * Bruchteil der insgesamt ~23.000 Artikel führen (z.B. 600 statt 23.000 --
 * Übertragungszeit/Speicherplatz auf dem Webserver skaliert entsprechend).
 *
 * Kopiert nur lokal (kein FTP/keine Netzwerk-Calls) in einen neuen Ordner --
 * DEN kopierst du danach wie gewohnt per FTP hoch (nur den Inhalt des
 * "artikel"-Unterordners, gleiche Struktur wie beim kompletten Ordner).
 *
 * Aufruf:
 *   php scripts/bilder_export_fuer_shop.php <shop-slug> [ziel-ordner]
 *
 * Beispiel:
 *   php scripts/bilder_export_fuer_shop.php bio-wolle
 *   -> kopiert nach storage/shop_export/bio-wolle/artikel/
 *
 * Kann jederzeit gefahrlos erneut laufen (überschreibt vorhandene Dateien im
 * Zielordner einfach neu, kein Löschen nicht mehr benötigter Altbestände --
 * bei einer im ERP entfernten Kanal-Zuweisung müsste der Zielordner von Hand
 * bereinigt werden, das Skript räumt dort nichts auf).
 */

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';

$shopSlug   = $argv[1] ?? null;
$zielOrdner = $argv[2] ?? null;

if (!$shopSlug) {
    fwrite(STDERR, "Aufruf: php bilder_export_fuer_shop.php <shop-slug> [ziel-ordner]\n");
    exit(1);
}

$repo = new ShopSyncRepository();
$shop = null;
foreach ($repo->findAktiveShops() as $s) {
    if ($s['slug'] === $shopSlug) {
        $shop = $s;
        break;
    }
}
if (!$shop) {
    fwrite(STDERR, "Shop '$shopSlug' nicht gefunden oder nicht aktiv.\n");
    exit(1);
}

$quellOrdner = realpath(__DIR__ . '/../public/uploads/artikel');
if (!$quellOrdner) {
    fwrite(STDERR, "Quellordner public/uploads/artikel/ existiert nicht.\n");
    exit(1);
}

$zielOrdner = $zielOrdner ?: (__DIR__ . '/../storage/shop_export/' . $shopSlug . '/artikel');
if (!is_dir($zielOrdner) && !mkdir($zielOrdner, 0777, true)) {
    fwrite(STDERR, "Konnte Zielordner '$zielOrdner' nicht anlegen.\n");
    exit(1);
}

$artikelIds = $repo->findArtikelIdsFuerShop((int)$shop['id']);
echo "Shop '{$shop['slug']}': " . count($artikelIds) . " Artikel im Kanal aktiv.\n";

$kopierteOrdner  = 0;
$kopierteDateien = 0;
$kopierteBytes   = 0;

foreach ($artikelIds as $artikelId) {
    $quellArtikelOrdner = $quellOrdner . '/' . $artikelId;
    if (!is_dir($quellArtikelOrdner)) {
        continue; // Artikel hat keine Bilder
    }

    $zielArtikelOrdner = $zielOrdner . '/' . $artikelId;
    if (!is_dir($zielArtikelOrdner)) {
        mkdir($zielArtikelOrdner, 0777, true);
    }

    $dateien = glob($quellArtikelOrdner . '/*');
    foreach ($dateien as $dateiPfad) {
        if (!is_file($dateiPfad)) {
            continue;
        }
        $zielPfad = $zielArtikelOrdner . '/' . basename($dateiPfad);
        copy($dateiPfad, $zielPfad);
        $kopierteDateien++;
        $kopierteBytes += filesize($dateiPfad);
    }
    $kopierteOrdner++;
}

$mb = round($kopierteBytes / 1024 / 1024, 1);
echo "Fertig: $kopierteOrdner Artikel-Ordner, $kopierteDateien Bilder, {$mb} MB kopiert nach:\n";
echo "  $zielOrdner\n";
echo "Diesen Ordner (nur den Inhalt, nicht den Ordner 'artikel' selbst nochmal verschachtelt)\n";
echo "per FTP an die Bilder-Basis-URL von '{$shop['slug']}' hochladen.\n";
