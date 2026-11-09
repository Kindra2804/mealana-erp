<?php
/**
 * Datentransfer ERP → ERP (Kommandozeile)
 *
 * Export (im Quell-ERP):
 *   php scripts/datentransfer.php export --teile=kategorien,artikel,slider,menue --shop=IND
 *   php scripts/datentransfer.php export --teile=artikel --artikel-kategorie=420 --datei=D:/transfer/indra.tar
 *   php scripts/datentransfer.php export --teile=kategorien,hersteller
 *
 *   --teile=             kategorien | hersteller | artikel | slider | menue (Komma-Liste)
 *   --shop=              Kanal (Slug, Kürzel oder ID): Artikel dieses Kanals, dessen Slider/Menü
 *   --artikel-kategorie= nur Artikel aus diesem Kategorie-Ast (mit --shop: beides muss passen)
 *   --alle-artikel       wirklich alle Artikel
 *   --kategorie-baum=    bei Teil „kategorien“ nur diesen Ast statt des ganzen Baums
 *   --datei=             Zieldatei (.tar), Standard: exports/datentransfer_<Datum>.tar
 *
 * Import (im Ziel-ERP — vorher DB sichern!):
 *   php scripts/datentransfer.php import --datei=D:/transfer/indra.tar --probelauf
 *   php scripts/datentransfer.php import --datei=D:/transfer/indra.tar
 *
 *   --probelauf  alles durchspielen und zählen, dann zurückrollen (keine Änderung, keine Dateien)
 *
 * Vorhandenes wird nie überschrieben: Kategorien/Hersteller/Achsen werden über den Namen
 * wiederverwendet, Artikel mit gleicher Artikelnummer bleiben unverändert. Ein zweiter Lauf
 * mit demselben Paket legt darum nichts doppelt an.
 */

if (PHP_SAPI !== 'cli') exit("Nur über die Kommandozeile.\n");

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/datentransfer/DatentransferExport.php';
require_once __DIR__ . '/../src/modules/datentransfer/DatentransferImport.php';

$befehl = $argv[1] ?? '';
$opt = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z\-]+)(?:=(.*))?$/', $arg, $m)) $opt[$m[1]] = $m[2] ?? true;
}

$ausgabe = function (array $r): void {
    if (!$r['erfolg']) {
        fwrite(STDERR, "FEHLER:\n  - " . implode("\n  - ", $r['fehler']) . "\n");
        foreach ($r['hinweise'] ?? [] as $h) echo "  Hinweis: $h\n";
        exit(1);
    }
    if (!empty($r['probelauf'])) echo "PROBELAUF — nichts wurde gespeichert.\n";
    if (!empty($r['datei'])) echo "Paket: {$r['datei']}\n";
    foreach ($r['zahlen'] ?? [] as $k => $n) printf("  %-24s %6d\n", $k, $n);
    foreach ($r['hinweise'] ?? [] as $h) echo "  Hinweis: $h\n";
};

if ($befehl === 'export') {
    $ausgabe((new DatentransferExport())->exportiere([
        'teile'             => array_map('trim', explode(',', (string)($opt['teile'] ?? ''))),
        'shop'              => $opt['shop'] ?? null,
        'artikel_kategorie' => $opt['artikel-kategorie'] ?? null,
        'alle_artikel'      => !empty($opt['alle-artikel']),
        'kategorie_baum'    => $opt['kategorie-baum'] ?? null,
        'datei'             => $opt['datei'] ?? null,
    ]));
} elseif ($befehl === 'import') {
    if (empty($opt['datei']) || $opt['datei'] === true) exit("Bitte --datei=<paket.tar> angeben.\n");
    $ausgabe((new DatentransferImport())->importiere($opt['datei'], !empty($opt['probelauf'])));
} else {
    echo "Aufruf: php scripts/datentransfer.php export|import [Optionen] — Details siehe Kopf dieser Datei.\n";
    exit(1);
}
