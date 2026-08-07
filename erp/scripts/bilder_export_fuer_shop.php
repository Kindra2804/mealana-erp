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
 *   php scripts/bilder_export_fuer_shop.php <shop-slug>
 *
 * Beispiel:
 *   php scripts/bilder_export_fuer_shop.php bio-wolle
 *   -> kopiert nach storage/shop_export/bio-wolle/artikel/
 *
 * Kann jederzeit gefahrlos erneut laufen (überschreibt vorhandene Dateien im
 * Zielordner einfach neu, kein Löschen nicht mehr benötigter Altbestände --
 * bei einer im ERP entfernten Kanal-Zuweisung müsste der Zielordner von Hand
 * bereinigt werden, das Skript räumt dort nichts auf).
 *
 * Dünner Wrapper um ShopSyncService::exportiereBilderFuerShop() -- die
 * eigentliche Kopierlogik lebt dort, damit die Web-UI-Variante
 * (public/einstellungen/shop_sync_bilder_export_start.php) sie mitbenutzen
 * kann, ohne sie zu duplizieren.
 *
 * Setzt/löst dieselbe bulk_import_aktiv-Sperre wie komplettabgleich.php und
 * erstbefuellung_bilder.php -- verhindert eine Race Condition mit den beiden
 * anderen Skripten für denselben Shop UND lässt die Web-UI (die die Sperre
 * beim Start bereits gesetzt hat) den "läuft"-Status korrekt wieder freigeben.
 */

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';

ob_implicit_flush(true);

const FORTSCHRITT_SCHRITT = 100;

$shopSlug = $argv[1] ?? null;
if (!$shopSlug) {
    fwrite(STDERR, "Aufruf: php bilder_export_fuer_shop.php <shop-slug>\n");
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

$repo->setBulkImportAktiv((int)$shop['id'], true);

try {
    $artikelAnzahl = count($repo->findArtikelIdsFuerShop((int)$shop['id']));
    echo "Shop '{$shop['slug']}': $artikelAnzahl Artikel im Kanal aktiv.\n";

    $fortschritt = function (int $erledigt, int $gesamt) {
        if ($gesamt === 0) {
            return;
        }
        if ($erledigt % FORTSCHRITT_SCHRITT === 0 || $erledigt === $gesamt) {
            echo "  ... $erledigt/$gesamt geprüft\n";
        }
    };

    $service = new ShopSyncService();
    $ergebnis = $service->exportiereBilderFuerShop((int)$shop['id'], $shop['slug'], $fortschritt);

    $mb = round($ergebnis['bytes'] / 1024 / 1024, 1);
    echo "Fertig: {$ergebnis['ordner']} Artikel-Ordner, {$ergebnis['dateien']} Bilder, {$mb} MB kopiert nach:\n";
    echo "  {$ergebnis['ziel']}\n";
    echo "Diesen Ordner (nur den Inhalt, nicht den Ordner 'artikel' selbst nochmal verschachtelt)\n";
    echo "per FTP an die Bilder-Basis-URL von '{$shop['slug']}' hochladen.\n";
} finally {
    $repo->setBulkImportAktiv((int)$shop['id'], false);
    $repo->setAktuellerSyncLog((int)$shop['id'], null);
}
