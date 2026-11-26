<?php
// Pflegesymbole: alle Schlüssel aus merkmal_werte (Pflege) haben eine Zeichnung, Plugin-Kopie ist identisch.
// Aufruf: php tests/pflege_symbole_test.php
require __DIR__ . '/../src/modules/schilder/PflegeSymbole.php';
require __DIR__ . '/../src/core/Database.php';

$fehler = 0;
$symbole = Database::getInstance()->query("SELECT w.symbol FROM merkmal_werte w JOIN merkmale m ON m.id = w.merkmal_id
    WHERE m.slug = 'pflege' AND w.symbol IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
foreach ($symbole as $s) {
    if (PflegeSymbole::svg($s) === null) { echo "FEHL Pflegesymbol '$s' hat keine Zeichnung\n"; $fehler++; }
}
echo "OK   " . (count($symbole) - $fehler) . " von " . count($symbole) . " Pflegesymbolen gezeichnet\n";

$kopie = __DIR__ . '/../../shop/wp-plugin/nahtlos-connector/includes/PflegeSymbole.php';
if (!is_file($kopie) || md5_file($kopie) !== md5_file(__DIR__ . '/../src/modules/schilder/PflegeSymbole.php')) {
    echo "FEHL Plugin-Kopie weicht ab → Datei nach shop/wp-plugin/nahtlos-connector/includes/ kopieren\n";
    $fehler++;
} else {
    echo "OK   Plugin-Kopie identisch\n";
}
exit($fehler ? 1 : 0);
