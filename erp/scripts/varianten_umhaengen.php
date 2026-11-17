<?php
/**
 * Varianten (Kinder) von einem Vater an einen anderen Vater umhängen.
 *
 * Anlass 2026-11-17: Beim Lang-Import zeigte „Über die EAN gefunden“ 9 Farben, die im ERP unter
 * LY-945 LUSSO hängen, laut EAN bei Lang aber 845 MILLE COLORI BABY sind (schon in JTL falsch
 * zugeordnet). Der Lang-Import hat LY-845 neu angelegt -- die 9 Kinder gehören dorthin.
 *
 * Was je Kind passiert (alles in EINER Transaktion, Fehler = nichts geändert):
 *   - vaterartikel_id → neuer Vater, Artikelnummer VON-xxxx → NACH-xxxx
 *   - Farbwert: wird beim neuen Vater als „xxxx alter Wert“ angelegt (Format der Lang-Farben,
 *     z. B. „0016 apfel/smaragd/prato“), Kombination umgestellt, alter Wert gelöscht, wenn ihn
 *     sonst niemand mehr nutzt
 *   - Name = Vatername + Farbwert
 *   - Stammdaten vom neuen Vater (propagiereZuKindern), Kategorien + Merkmale = die des Vaters
 *   - Standardpreis 0,00 → Standardpreis des neuen Vaters
 * Bleibt am Artikel: Bestand, Chargen, Lagerbewegungen, Verkäufe, Belege, EAN, Bilder, Lieferanten.
 *
 * Bricht ab, wenn ein Kind oder sein Farbwert schon in einem Woo-Shop angelegt ist
 * (dort müsste die Variante beim alten Produkt entfernt werden -- vorher klären).
 *
 * Aufruf:
 *   php scripts/varianten_umhaengen.php --von=LY-945 --nach=LY-845                 (Probelauf)
 *   php scripts/varianten_umhaengen.php --von=LY-945 --nach=LY-845 --ausfuehren
 * Auswahl der Kinder: ohne --nummern alle Kinder von VON, deren Lieferanten-Artikelnummer mit der
 * Nummer von NACH beginnt (LY-845 → „845.“), sonst --nummern=LY-945-0016,LY-945-0050,…
 * Vorher Datenbank-Sicherung!
 */

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/logger.php';
require_once __DIR__ . '/../src/modules/artikel/ArtikelRepository.php';

$opt = getopt('', ['von:', 'nach:', 'nummern::', 'ausfuehren']);
$vonNr  = trim($opt['von'] ?? '');
$nachNr = trim($opt['nach'] ?? '');
$ausfuehren = isset($opt['ausfuehren']);
if ($vonNr === '' || $nachNr === '') {
    exit("Aufruf: php scripts/varianten_umhaengen.php --von=LY-945 --nach=LY-845 [--nummern=…] [--ausfuehren]\n");
}

$db = Database::getInstance();
$vaterHolen = $db->prepare("SELECT id, artikelnummer, name, ist_vater FROM artikel WHERE artikelnummer = ? AND vaterartikel_id IS NULL");
$vaterHolen->execute([$vonNr]);  $von  = $vaterHolen->fetch(PDO::FETCH_ASSOC);
$vaterHolen->execute([$nachNr]); $nach = $vaterHolen->fetch(PDO::FETCH_ASSOC);
if (!$von)  exit("Vater $vonNr nicht gefunden.\n");
if (!$nach) exit("Vater $nachNr nicht gefunden (bei Lang: zuerst den Lang-Import laufen lassen).\n");

// Kinder auswählen
if (!empty($opt['nummern'])) {
    $nummern = array_filter(array_map('trim', explode(',', $opt['nummern'])));
    $in = implode(',', array_fill(0, count($nummern), '?'));
    $st = $db->prepare("SELECT id, artikelnummer, name FROM artikel WHERE vaterartikel_id = ? AND artikelnummer IN ($in) ORDER BY artikelnummer");
    $st->execute(array_merge([$von['id']], $nummern));
} else {
    $praefix = preg_replace('/^[A-Z]+-/', '', $nachNr) . '.';   // LY-845 → "845."
    $st = $db->prepare("SELECT DISTINCT a.id, a.artikelnummer, a.name FROM artikel a
                        JOIN artikel_lieferanten al ON al.artikel_id = a.id
                        WHERE a.vaterartikel_id = ? AND al.artikelnummer_lieferant LIKE ? ORDER BY a.artikelnummer");
    $st->execute([$von['id'], $praefix . '%']);
}
$kinder = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$kinder) exit("Keine passenden Kinder unter $vonNr gefunden.\n");

// Farbwert je Kind (genau eine Achse erwartet)
$wertSt = $db->prepare("SELECT w.id, w.achse_id, w.wert FROM varianten_kombination_werte k
                        JOIN varianten_achse_werte w ON w.id = k.wert_id WHERE k.kombination_id = ?");
$nachAchsen = $db->prepare("SELECT achse_id FROM artikel_achsen WHERE artikel_id = ?");
$nachAchsen->execute([$nach['id']]);
$nachAchsen = array_map('intval', $nachAchsen->fetchAll(PDO::FETCH_COLUMN));
$nrBelegt = $db->prepare("SELECT COUNT(*) FROM artikel WHERE artikelnummer = ?");
$shopKind = $db->prepare("SELECT shop_id FROM artikel_shops WHERE artikel_id = ? AND external_id IS NOT NULL");
$shopWert = $db->prepare("SELECT shop_id FROM varianten_achse_werte_shops WHERE wert_id = ? AND externe_term_id IS NOT NULL");

$plan = []; $fehler = [];
foreach ($kinder as $k) {
    $code = substr($k['artikelnummer'], strlen($vonNr) + 1);           // LY-945-0016 → 0016
    $neueNr = $nachNr . '-' . $code;
    $wertSt->execute([$k['id']]);
    $werte = $wertSt->fetchAll(PDO::FETCH_ASSOC);
    if (count($werte) !== 1) { $fehler[] = "{$k['artikelnummer']}: hat " . count($werte) . " Achswerte (erwartet 1)"; continue; }
    $w = $werte[0];
    if (!in_array((int)$w['achse_id'], $nachAchsen, true)) { $fehler[] = "{$k['artikelnummer']}: Achse {$w['achse_id']} gibt es bei $nachNr nicht"; continue; }
    $nrBelegt->execute([$neueNr]);
    if ($nrBelegt->fetchColumn() > 0) { $fehler[] = "{$k['artikelnummer']}: $neueNr ist schon vergeben"; continue; }
    $shopKind->execute([$k['id']]); $shopWert->execute([$w['id']]);
    $shops = array_unique(array_merge($shopKind->fetchAll(PDO::FETCH_COLUMN), $shopWert->fetchAll(PDO::FETCH_COLUMN)));
    if ($shops) { $fehler[] = "{$k['artikelnummer']}: ist schon in Shop " . implode(', ', $shops) . " angelegt -- erst klären"; continue; }
    $neuerWert = preg_match('/^\d{4} /', $w['wert']) ? $w['wert'] : $code . ' ' . $w['wert'];
    $plan[] = ['kind' => $k, 'code' => $code, 'neue_nr' => $neueNr, 'wert' => $w, 'neuer_wert' => $neuerWert,
               'neuer_name' => $nach['name'] . ' ' . $neuerWert];
}

echo ($ausfuehren ? "AUSFÜHREN" : "PROBELAUF (ändert nichts)") . ": $vonNr {$von['name']} → $nachNr {$nach['name']}\n\n";
foreach ($plan as $p) {
    printf("  %-14s → %-14s  %s\n", $p['kind']['artikelnummer'], $p['neue_nr'], $p['neuer_name']);
}
foreach ($fehler as $f) echo "  ✗ $f\n";
echo "\n" . count($plan) . " umhängen, " . count($fehler) . " Problem(e).\n";
if ($fehler) exit("Abbruch wegen Problemen -- nichts geändert.\n");
if (!$ausfuehren) exit("Zum Ausführen mit --ausfuehren starten (vorher Datenbank-Sicherung).\n");

// ── Ausführen ──────────────────────────────────────────────────────────────
$repo = new ArtikelRepository();
$db->beginTransaction();
try {
    $wertSuchen = $db->prepare("SELECT id FROM varianten_achse_werte WHERE artikel_id = ? AND achse_id = ? AND wert = ?");
    $wertNeu    = $db->prepare("INSERT INTO varianten_achse_werte (artikel_id, achse_id, wert, sort_order) VALUES (?, ?, ?, 0)");
    $kombiUm    = $db->prepare("UPDATE varianten_kombination_werte SET wert_id = ? WHERE kombination_id = ? AND wert_id = ?");
    $wertFrei   = $db->prepare("SELECT COUNT(*) FROM varianten_kombination_werte WHERE wert_id = ?");
    $wertWeg    = $db->prepare("DELETE FROM varianten_achse_werte WHERE id = ?");
    $wertShopWeg = $db->prepare("DELETE FROM varianten_achse_werte_shops WHERE wert_id = ?");
    $artikelUm  = $db->prepare("UPDATE artikel SET vaterartikel_id = ?, artikelnummer = ?, name = ?, aktualisiert_am = NOW() WHERE id = ?");
    $katWeg     = $db->prepare("DELETE FROM artikel_kategorien WHERE artikel_id = ?");
    $katNeu     = $db->prepare("INSERT INTO artikel_kategorien (artikel_id, kategorie_id) SELECT ?, kategorie_id FROM artikel_kategorien WHERE artikel_id = ?");
    $mrkWeg     = $db->prepare("DELETE FROM artikel_merkmale WHERE artikel_id = ?");
    $mrkNeu     = $db->prepare("INSERT INTO artikel_merkmale (artikel_id, merkmal_id, merkmal_wert_id) SELECT ?, merkmal_id, merkmal_wert_id FROM artikel_merkmale WHERE artikel_id = ?");
    $vaterPreis = $db->prepare("SELECT kundengruppen_id, brutto_vk, netto_vk FROM artikel_preise
                                WHERE artikel_id = ? AND gueltig_ab IS NULL AND gueltig_bis IS NULL AND brutto_vk > 0");
    $preisSetzen = $db->prepare("UPDATE artikel_preise SET brutto_vk = ?, netto_vk = ?
                                 WHERE artikel_id = ? AND kundengruppen_id = ? AND gueltig_ab IS NULL AND gueltig_bis IS NULL AND brutto_vk = 0");
    $vaterPreis->execute([$nach['id']]);
    $preiseNach = $vaterPreis->fetchAll(PDO::FETCH_ASSOC);

    foreach ($plan as $p) {
        $kid = (int)$p['kind']['id'];
        $wertSuchen->execute([$nach['id'], $p['wert']['achse_id'], $p['neuer_wert']]);
        $neueWertId = $wertSuchen->fetchColumn();
        if (!$neueWertId) { $wertNeu->execute([$nach['id'], $p['wert']['achse_id'], $p['neuer_wert']]); $neueWertId = $db->lastInsertId(); }
        $kombiUm->execute([$neueWertId, $kid, $p['wert']['id']]);
        $wertFrei->execute([$p['wert']['id']]);
        if ((int)$wertFrei->fetchColumn() === 0) { $wertShopWeg->execute([$p['wert']['id']]); $wertWeg->execute([$p['wert']['id']]); }
        $artikelUm->execute([$nach['id'], $p['neue_nr'], $p['neuer_name'], $kid]);
        $katWeg->execute([$kid]); $katNeu->execute([$kid, $nach['id']]);
        $mrkWeg->execute([$kid]); $mrkNeu->execute([$kid, $nach['id']]);
        foreach ($preiseNach as $pr) $preisSetzen->execute([$pr['brutto_vk'], $pr['netto_vk'], $kid, $pr['kundengruppen_id']]);
    }

    // Farbwerte des neuen Vaters nach Farbnummer sortieren
    $alle = $db->prepare("SELECT id FROM varianten_achse_werte WHERE artikel_id = ? ORDER BY achse_id, wert");
    $alle->execute([$nach['id']]);
    $sort = $db->prepare("UPDATE varianten_achse_werte SET sort_order = ? WHERE id = ?");
    foreach ($alle->fetchAll(PDO::FETCH_COLUMN) as $i => $wid) $sort->execute([($i + 1) * 10, $wid]);

    $repo->propagiereZuKindern((int)$nach['id']);
    $db->prepare("UPDATE artikel SET aktualisiert_am = NOW() WHERE id IN (?, ?)")->execute([$von['id'], $nach['id']]);

    $system = (int)$db->query("SELECT id FROM benutzer WHERE username = 'system' LIMIT 1")->fetchColumn();
    Logger::log('artikel.varianten_umgehaengt', 'artikel', (int)$nach['id'], [
        'von' => $vonNr, 'nach' => $nachNr,
        'artikel' => array_map(fn($p) => $p['kind']['artikelnummer'] . ' → ' . $p['neue_nr'], $plan),
    ], $system ?: null);

    $db->commit();
    echo "Fertig: " . count($plan) . " Varianten umgehängt.\n";
} catch (Throwable $e) {
    $db->rollBack();
    exit("FEHLER, nichts geändert: " . $e->getMessage() . "\n");
}
