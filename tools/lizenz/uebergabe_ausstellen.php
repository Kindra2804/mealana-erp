<?php
// Superadmin-Übergabe-Schlüssel ausstellen (einmalig, 7 Tage gültig).
// VORHER Identität prüfen: schriftliche Anfrage vom Inhaber/Geschäftsführer!
//   php tools/lizenz/uebergabe_ausstellen.php --nr=U-2026-0001 --firma="MEALANA KG" --uid=ATU68078304
//   [--steuernr=…] [--tage=7] [--datei=uebergabe.key.txt]
require __DIR__ . '/LizenzSigner.php';

$o = getopt('', ['nr:', 'firma:', 'uid::', 'steuernr::', 'tage::', 'datei::']);
if (empty($o['nr']) || empty($o['firma'])) {
    fwrite(STDERR, "Pflicht: --nr und --firma (siehe Kopf der Datei)\n");
    exit(1);
}
$inhalt = LizenzSigner::signiereUebergabe($o['nr'], $o['firma'], $o['uid'] ?? '', $o['steuernr'] ?? '', (int)($o['tage'] ?? 7));
$ziel = $o['datei'] ?? (preg_replace('/[^A-Za-z0-9_-]/', '_', $o['nr']) . '_uebergabe.txt');
file_put_contents($ziel, $inhalt);
echo "Übergabe-Schlüssel geschrieben: $ziel (gültig " . (int)($o['tage'] ?? 7) . " Tage, nur einmal verwendbar)\n";
