<?php
/**
 * Prüffälle Mengen-Aktionen gegen den PHP-Rechenkern.  Start: php tests/mengen_aktion_test.php
 * Gleiche Fälle für JS: node tests/mengen_aktion_test.js
 */
require __DIR__ . '/../src/modules/aktionen/MengenAktionRechner.php';

$daten = json_decode(file_get_contents(__DIR__ . '/mengen_aktion_faelle.json'), true);
$fehler = 0;
foreach ($daten['faelle'] as $f) {
    $aktionen = array_map(fn($k) => $daten['aktionen'][$k], $f['aktionen']);
    $erg = MengenAktionRechner::berechne($aktionen, $f['positionen']);
    $probleme = [];

    $istKeys = array_keys($erg['positionen']); sort($istKeys);
    $sollKeys = array_keys($f['soll']); sort($sollKeys);
    if ($istKeys !== $sollKeys) $probleme[] = 'Positionen mit Rabatt: ' . json_encode($istKeys) . ' statt ' . json_encode($sollKeys);
    foreach ($f['soll'] as $key => $soll) {
        foreach ($soll as $feld => $wert) {
            $ist = $erg['positionen'][$key][$feld] ?? null;
            if ($ist === null || abs($ist - $wert) > 0.0001) $probleme[] = "$key.$feld = " . json_encode($ist) . " statt $wert";
        }
    }
    if (count($erg['hinweise']) !== count($f['hinweise'])) $probleme[] = 'Anzahl Hinweise ' . count($erg['hinweise']) . ' statt ' . count($f['hinweise']);
    foreach ($f['hinweise'] as $i => $soll) {
        foreach ($soll as $feld => $wert) {
            $ist = $erg['hinweise'][$i][$feld] ?? null;
            if ($ist === null || abs($ist - $wert) > 0.0001) $probleme[] = "Hinweis $i.$feld = " . json_encode($ist) . " statt $wert";
        }
    }
    // Summe muss exakt stimmen: neu = alt − Ersparnis
    $alt = array_sum(array_column($erg['positionen'], 'gesamt_alt'));
    $neu = array_sum(array_column($erg['positionen'], 'gesamt_neu'));
    $spar = array_sum(array_column($erg['hinweise'], 'ersparnis'));
    if (abs($alt - $neu - $spar) > 0.001) $probleme[] = "Summe passt nicht: alt $alt − neu $neu ≠ Ersparnis $spar";

    echo ($probleme ? 'FEHL ' : 'OK   ') . $f['name'] . "\n";
    foreach ($probleme as $p) echo "       $p\n";
    $fehler += $probleme ? 1 : 0;
}
// Kopie im WP-Plugin muss identisch sein (shop/wp-plugin/nahtlos-connector/includes/MengenAktionRechner.php)
$kopie = __DIR__ . '/../../shop/wp-plugin/nahtlos-connector/includes/MengenAktionRechner.php';
if (!is_file($kopie) || md5_file($kopie) !== md5_file(__DIR__ . '/../src/modules/aktionen/MengenAktionRechner.php')) {
    echo "FEHL Plugin-Kopie des Rechenkerns weicht ab → Datei nach shop/wp-plugin/nahtlos-connector/includes/ kopieren\n";
    $fehler++;
} else {
    echo "OK   Plugin-Kopie des Rechenkerns identisch\n";
}
echo $fehler ? "\n$fehler Fall/Fälle FALSCH\n" : "\nAlle " . count($daten['faelle']) . " Fälle richtig\n";
exit($fehler ? 1 : 0);
