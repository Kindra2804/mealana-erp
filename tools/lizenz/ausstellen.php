<?php
// Lizenzdatei ausstellen (bis die Oberfläche "Lizenzverwaltung" fertig ist).
//   php tools/lizenz/ausstellen.php --nr=L-2026-0001 --firma="MEALANA KG" --uid=ATU68078304 --haus
//   php tools/lizenz/ausstellen.php --nr=L-2026-0002 --firma="Muster e.U." --steuernr=12/345 \
//        --module=kasse:2,shop:1,gutscheine --wartung=2027-11-01 --datei=muster.lic
// --haus = Hauslizenz: alle Module (Kasse/Shop je 99), Wartung unbegrenzt
require __DIR__ . '/LizenzSigner.php';
require __DIR__ . '/../../erp/src/core/Lizenz.php';

$o = getopt('', ['nr:', 'firma:', 'uid::', 'steuernr::', 'module::', 'wartung::', 'haus', 'datei::']);
if (empty($o['nr']) || empty($o['firma'])) {
    fwrite(STDERR, "Pflicht: --nr und --firma (siehe Kopf der Datei)\n");
    exit(1);
}
$module = [];
if (isset($o['haus'])) {
    foreach (Lizenz::MODULE as $code => $m) $module[$code] = $m['menge'] ? 99 : 1;
} else {
    foreach (array_filter(explode(',', $o['module'] ?? '')) as $teil) {
        [$code, $anz] = array_pad(explode(':', trim($teil)), 2, 1);
        if (!isset(Lizenz::MODULE[$code])) { fwrite(STDERR, "Unbekanntes Modul: $code\n"); exit(1); }
        $module[$code] = max(1, (int)$anz);
    }
}
$inhalt = LizenzSigner::signiere([
    'lizenz_nr'    => $o['nr'],
    'firma'        => $o['firma'],
    'uid'          => $o['uid'] ?? '',
    'steuernummer' => $o['steuernr'] ?? '',
    'module'       => $module,
    'wartung_bis'  => isset($o['haus']) ? null : ($o['wartung'] ?? date('Y-m-d', strtotime('+1 year'))),
    'hauslizenz'   => isset($o['haus']),
]);
$ziel = $o['datei'] ?? (preg_replace('/[^A-Za-z0-9_-]/', '_', $o['nr']) . '.lic');
file_put_contents($ziel, $inhalt);
echo "Lizenzdatei geschrieben: $ziel\n";
