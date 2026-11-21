<?php
/**
 * Alt-Texte für Bilder nachziehen, die schon im Shop sind, aber im ERP keinen eigenen Alt-Text haben.
 * Setzt in der WordPress-Mediathek den Artikelnamen als Alt-Text (Google-Bildersuche, Barrierefreiheit).
 * Neue Bilder bekommen das beim normalen Abgleich automatisch; dieses Skript ist für den Bestand.
 *
 *   php scripts/alt_texte_nachziehen.php <shop-slug> --probelauf          → nur zählen
 *   php scripts/alt_texte_nachziehen.php <shop-slug> [--max=500]         → setzen (Standard max. 500 je Lauf)
 *
 * Pro Bild eine kleine Pause (wie beim Bild-Upload), damit das Hosting nicht überlastet —
 * große Mengen in mehreren Läufen, am besten abends.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Logger.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncRepository.php';
require_once __DIR__ . '/../src/modules/shop/ShopSyncService.php';

$slug      = $argv[1] ?? '';
$probelauf = in_array('--probelauf', $argv, true);
$max       = 500;
foreach ($argv as $a) if (str_starts_with($a, '--max=')) $max = max(1, (int)substr($a, 6));

$shop = null;
foreach ((new ShopSyncRepository())->findAktiveShops() as $s) {
    if ($s['slug'] === $slug) $shop = $s;
}
if (!$shop) {
    fwrite(STDERR, "Shop „$slug“ nicht gefunden oder nicht aktiv. Aufruf: php scripts/alt_texte_nachziehen.php <shop-slug> [--probelauf] [--max=500]\n");
    exit(1);
}

echo ($probelauf ? 'PROBELAUF — ' : '') . "Shop {$shop['name']}: Alt-Texte nachziehen (max. $max)\n";
$erg = (new ShopSyncService())->nachziehenAltTexte($shop, $probelauf, $max, fn($t) => print("  $t\n"));
echo "Fertig: {$erg['geprueft']} geprüft, {$erg['gesetzt']} " . ($probelauf ? 'würden gesetzt' : 'gesetzt') . ", {$erg['fehler']} Fehler\n";
