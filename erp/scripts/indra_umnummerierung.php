<?php
/**
 * Indra-Design: Artikelnummern auf die neuen Katalog-Codes umstellen (Jacky 2026-11-18).
 *
 * Quelle ist die Katalog-Werkstatt (E:\Laser\Katalog-Werkstatt\daten\daten.json):
 * jedes Motiv hat seinen neuen Code (z. B. A25) und im Feld „erp“ die bisherigen
 * ERP-Vaterartikel (z. B. ID-U-A49, ID-SA-A58). Daraus wird
 *   ID-U-A49 → ID-U-A25,  ID-U-A49-KO10 → ID-U-A25-KO10 (Kinder mit)
 * Präfixe ID-U / ID-SA / ID-KP bleiben, nur der Motiv-Code ändert sich.
 * ID-W (Schilder) und ID-XM (Weihnachten) sind schon richtig und werden nur geprüft.
 *
 * Warum zwei Phasen? Es gibt Ringtausch (ID-U-K19 → A54, während ID-U-A54 → A30).
 * In der DB ginge das in einer Transaktion, aber WooCommerce lehnt beim Sync eine
 * SKU ab, die ein anderes Produkt noch hat. Darum:
 *   Phase 1: alle betroffenen Artikel → Zwischennummer „UMN-<alt>“   → Shop-Sync laufen lassen
 *   Phase 2: „UMN-<alt>“ → neue Nummer                               → Shop-Sync laufen lassen
 * Der Shop ordnet über artikel_shops.external_id zu, nicht über die SKU – es entstehen
 * keine neuen Produkte. aktualisiert_am springt beim UPDATE automatisch (ON UPDATE).
 *
 * Nicht angefasst: alte Belege / Händler- und Partner-Belegpositionen /
 * Lagerwert-Schnappschüsse (halten den damaligen Stand fest), JTL-Abgleich
 * (reine ERP-Artikel sind dort ausgenommen).
 *
 * Aufruf:
 *   php scripts/indra_umnummerierung.php                 Probelauf (ändert nichts)
 *   php scripts/indra_umnummerierung.php --phase=1       Zwischennummern setzen
 *   php scripts/indra_umnummerierung.php --phase=2       endgültige Nummern setzen
 *   Option --werkstatt=<Pfad zu daten.json>
 */

require_once __DIR__ . '/../src/core/Database.php';

$phase = 0;
$datei = 'E:/Laser/Katalog-Werkstatt/daten/daten.json';
foreach ($argv as $a) {
    if (preg_match('/^--phase=([12])$/', $a, $x)) $phase = (int) $x[1];
    if (str_starts_with($a, '--werkstatt=')) $datei = substr($a, 12);
}
const ZW = 'UMN-';

$w = json_decode((string) @file_get_contents($datei), true);
if (!$w || empty($w['motive'])) exit("Werkstatt-Daten nicht lesbar: $datei\n");

$db = Database::getInstance();
$artikel = [];   // artikelnummer (ohne Zwischen-Präfix) => row
$zwischen = 0;
foreach ($db->query("SELECT id, artikelnummer, name, vaterartikel_id, ist_vater FROM artikel") as $r) {
    if (str_starts_with($r['artikelnummer'], ZW)) { $r['artikelnummer'] = substr($r['artikelnummer'], strlen(ZW)); $zwischen++; }
    $artikel[$r['artikelnummer']] = $r;
}
if ($phase === 2 && !$zwischen) exit("Keine Zwischennummern gefunden – zuerst --phase=1.\n");
if ($phase !== 2 && $zwischen) exit("Es gibt schon $zwischen Zwischennummern (" . ZW . "…) – Phase 1 lief bereits, weiter mit --phase=2.\n");
$kinderVon = [];
foreach ($artikel as $r) if ($r['vaterartikel_id']) $kinderVon[(int) $r['vaterartikel_id']][] = $r;

$fehler = []; $hinweise = [];
$plan = [];      // alt => [neu, id, art('Vater'|'Kind'), name]

// ---------- 1. Väter aus der Werkstatt ----------
foreach ($w['motive'] as $m) {
    foreach (array_filter(array_map('trim', explode(',', $m['erp'] ?? ''))) as $alt) {
        if (!preg_match('/^(ID-(?:U|SA|KP)-)([A-Z]{1,2}\d+)$/', $alt, $x)) {
            // ID-W / ID-XM: nur prüfen, ob die Nummer schon zum Code passt
            $soll = str_starts_with($alt, 'ID-XM-') ? 'ID-XM-' . substr($m['code'], 1) : 'ID-' . $m['code'];
            if ($alt !== $soll) $hinweise[] = "$alt bei Motiv {$m['code']}: Muster unbekannt – bleibt unverändert";
            continue;
        }
        $neu = $x[1] . $m['code'];
        if ($alt === $neu) continue;
        if (!isset($artikel[$alt])) { $hinweise[] = "$alt (Motiv {$m['code']}) gibt es im ERP nicht – übersprungen"; continue; }
        $a = $artikel[$alt];
        if ($a['vaterartikel_id']) { $fehler[] = "$alt ist ein Kind-Artikel, erwartet war ein Vater"; continue; }
        $plan[$alt] = [$neu, (int) $a['id'], 'Vater', $a['name']];
        // ---------- 2. Kinder ----------
        foreach ($kinderVon[(int) $a['id']] ?? [] as $k) {
            if (str_starts_with($k['artikelnummer'], $alt . '-')) {
                $plan[$k['artikelnummer']] = [$neu . substr($k['artikelnummer'], strlen($alt)), (int) $k['id'], 'Kind', $k['name']];
            } else {
                $hinweise[] = "Kind {$k['artikelnummer']} von $alt folgt nicht dem Muster – bleibt unverändert";
            }
        }
    }
}

// ---------- 3. Kollisionen ----------
$ziele = [];
foreach ($plan as $alt => [$neu]) {
    if (isset($ziele[$neu])) $fehler[] = "$neu wäre doppelt: aus {$ziele[$neu]} und $alt";
    $ziele[$neu] = $alt;
    if (isset($artikel[$neu]) && !isset($plan[$neu])) $fehler[] = "$neu ist schon vergeben ({$artikel[$neu]['name']}) und wird nicht mit umbenannt";
}
$ring = array_filter(array_keys($plan), fn($alt) => isset($plan[$plan[$alt][0]]));

// ---------- 4. Shop-Zuordnung ----------
$ids = array_column($plan, 1);
$shop = [];
if ($ids) {
    $st = $db->query("SELECT s.artikel_id, s.shop_id, s.aktiv, s.external_id, s.sync_status, s.fehler_meldung
                      FROM artikel_shops s WHERE s.artikel_id IN (" . implode(',', $ids) . ")");
    foreach ($st as $r) {
        $shop[(int) $r['artikel_id']][] = $r;
        if ($r['aktiv'] && empty($r['external_id'])) $fehler[] = "Artikel-ID {$r['artikel_id']} ist im Shop {$r['shop_id']} aktiv, hat aber keine Shop-ID (external_id) – würde per SKU gesucht";
        if ($r['sync_status'] === 'fehler') $fehler[] = "Artikel-ID {$r['artikel_id']} hat Sync-Fehler im Shop {$r['shop_id']}: {$r['fehler_meldung']}";
    }
}

// ---------- Ausgabe ----------
$nV = count(array_filter($plan, fn($p) => $p[2] === 'Vater'));
printf("Werkstatt: %s\nUmzubenennen: %d Väter + %d Kinder = %d Artikel, davon %d im Ringtausch\n\n",
    $datei, $nV, count($plan) - $nV, count($plan), count($ring));
foreach ($plan as $alt => [$neu, $id, $art, $name]) {
    $sh = implode(' ', array_map(fn($s) => "Shop{$s['shop_id']}:" . ($s['external_id'] ?: '–') . ($s['aktiv'] ? '' : '(inaktiv)'), $shop[$id] ?? []));
    printf("  %-5s %-18s → %-18s %s%s\n", $art, $alt, $neu, mb_strimwidth(html_entity_decode($name), 0, 45, '…'), $sh ? "  [$sh]" : '');
}
if ($hinweise) echo "\nHinweise:\n  - " . implode("\n  - ", array_unique($hinweise)) . "\n";
if ($fehler)   echo "\nFEHLER (es wird nichts geändert):\n  - " . implode("\n  - ", array_unique($fehler)) . "\n";

// Liste als CSV zum Nachschlagen
$csv = __DIR__ . '/../storage/indra_umnummerierung_' . date('Y-m-d') . '.csv';
$f = fopen($csv, 'w');
fwrite($f, "\xEF\xBB\xBF");
fputcsv($f, ['alt', 'neu', 'art', 'name'], ';');
foreach ($plan as $alt => [$neu, , $art, $name]) fputcsv($f, [$alt, $neu, $art, $name], ';');
fclose($f);
echo "\nListe: " . realpath($csv) . "\n";

if ($phase === 0) exit("\nProbelauf – nichts geändert. Weiter mit --phase=1.\n");
if ($fehler) exit("\nAbbruch wegen Fehlern.\n");

// ---------- Schreiben ----------
$upd = $db->prepare("UPDATE artikel SET artikelnummer = :neu WHERE id = :id AND artikelnummer = :alt");
$db->beginTransaction();
try {
    $n = 0;
    foreach ($plan as $alt => [$neu, $id]) {
        [$von, $nach] = $phase === 1 ? [$alt, ZW . $alt] : [ZW . $alt, $neu];
        $upd->execute(['neu' => $nach, 'id' => $id, 'alt' => $von]);
        if ($upd->rowCount() !== 1) throw new RuntimeException("Artikel-ID $id hat nicht die Nummer $von – Phase $phase passt nicht zum Stand");
        $n++;
    }
    $db->commit();
    echo "\nPhase $phase: $n Artikel umbenannt.\n";
    echo $phase === 1
        ? "Jetzt den Shop-Sync laufen lassen (alle Zwischennummern müssen im Shop ankommen), dann --phase=2.\n"
        : "Jetzt den Shop-Sync laufen lassen – fertig.\n";
} catch (Throwable $e) {
    $db->rollBack();
    exit("\nAbbruch, nichts geändert: " . $e->getMessage() . "\n");
}
