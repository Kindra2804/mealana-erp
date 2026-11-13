<?php
/**
 * Shop-Test gegen das Dev-ERP: NUR Bestellungen holen + Status an den Shop melden.
 *
 * Für Live-Tests (Bestellung → Rechnung → Versand → Gutschrift/Widerruf), solange ein Shop
 * hinter der Wartungsseite steht: Shop kurz ans Dev-ERP hängen, testen, wieder trennen —
 * so entstehen im Live-ERP keine Testbelege.
 *
 * Anders als cron/shop_sync.php wird hier NICHTS an den Shop übertragen außer dem
 * Bestellstatus (keine Artikel, kein Bestand, keine Gutschein-Codes, kein Slider/Menü).
 *
 *   php scripts/shop_test_bestellungen.php --shop=sockenwolle
 *   php scripts/shop_test_bestellungen.php --shop=sockenwolle --seit="2026-11-13 14:00"
 *
 *   --seit=  setzt vorher den Abholzeitpunkt (nur Bestellungen, die danach geändert wurden)
 *
 * Voraussetzung (Schutz): Kanal im Dev-ERP mit sync_pausiert = 1 UND bulk_import_aktiv = 1,
 * und die Windows-Aufgabe „Shop-Sync-Cronjob“ ist während des Tests deaktiviert.
 */
if (PHP_SAPI !== 'cli') exit("Nur über die Kommandozeile.\n");

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopBestellungSyncService.php';
require_once __DIR__ . '/../src/modules/shop/WiderrufService.php';

$opt = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $arg, $m)) $opt[$m[1]] = $m[2] ?? true;
}
$db = Database::getInstance();
$systemId = (int)$db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
$_SESSION = ['benutzer' => ['id' => $systemId]];

$stmt = $db->prepare("
    SELECT id, slug, name, wc_url, wc_key, wc_secret, wp_username, wp_app_password,
           bestellungen_letzter_sync, bulk_import_aktiv, sync_pausiert
    FROM shops WHERE slug = ? OR nr_kuerzel = ?
");
$stmt->execute([$opt['shop'] ?? '', $opt['shop'] ?? '']);
$shop = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$shop) exit("Shop nicht gefunden (--shop=slug oder Kürzel).\n");
if (!$shop['wc_url'] || !$shop['wc_key'] || !$shop['wc_secret']) exit("[{$shop['slug']}] Woo-Zugang fehlt (Einstellungen → Kanäle).\n");
if ((int)$shop['sync_pausiert'] !== 1 || (int)$shop['bulk_import_aktiv'] !== 1) {
    exit("[{$shop['slug']}] Schutz fehlt: Kanal muss sync_pausiert = 1 UND bulk_import_aktiv = 1 haben,\n"
       . "sonst würde der normale Abgleich Artikel/Gutscheine/Menü in den Shop schreiben.\n");
}

if (!empty($opt['seit'])) {
    $seit = date('Y-m-d H:i:s', strtotime((string)$opt['seit']));
    $db->prepare("UPDATE shops SET bestellungen_letzter_sync = ? WHERE id = ?")->execute([$seit, $shop['id']]);
    $shop['bestellungen_letzter_sync'] = $seit;
    echo "Abholzeitpunkt gesetzt: $seit\n";
}

echo "[{$shop['slug']}] {$shop['wc_url']} — Bestellungen seit " . ($shop['bestellungen_letzter_sync'] ?: 'Anfang') . "\n";
$e = (new ShopBestellungSyncService())->syncBestellungen($shop);
echo "  Bestellungen: {$e['erfolg']} erfolgreich, {$e['fehler']} Fehler\n";

$st = (new ShopBestellungSyncService())->meldeOffeneStatusAnShop($shop);
echo "  Status an Shop gemeldet: {$st['gemeldet']}, Fehler: {$st['fehler']}\n";

$ungeklaert = (new WiderrufService())->zaehleUngeklaerte($shop);
if ($ungeklaert) echo "  Widerrufe ohne Zuordnung: $ungeklaert (Verkauf → Widerrufe)\n";

// Neueste Aufträge dieses Shops zur Kontrolle
$a = $db->prepare("SELECT auftrag_nr, kanal_auftrag_id, zahlungsstatus, lieferstatus, bruttobetrag, erstellt_am
                   FROM auftraege WHERE shop_id = ? ORDER BY id DESC LIMIT 5");
$a->execute([$shop['id']]);
foreach ($a->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("  %s (Woo #%s) %s / %s € %s  %s\n", $r['auftrag_nr'], $r['kanal_auftrag_id'], $r['zahlungsstatus'], $r['lieferstatus'],
        number_format((float)$r['bruttobetrag'], 2, ',', '.'), $r['erstellt_am']);
}
