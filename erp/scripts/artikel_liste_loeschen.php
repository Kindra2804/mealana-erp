<?php
/**
 * Räumt eine Liste von Hauptartikeln (Väter/Einzelartikel) samt ihren Kindern weg – z.B. die von
 * Babsi ausgeschlossenen JTL-Artikel, die vor ihrer Liste schon importiert waren (2026-11-26).
 *
 * Je Artikel (Kinder zuerst, dann der Vater):
 *   - nie benutzt (keine Bewegung, kein Beleg, kein Bestand …, siehe ArtikelRepository::findLoeschSperren)
 *       → ENDGÜLTIG löschen, im Woo-Shop ebenfalls (ArtikelService::loescheEndgueltig)
 *   - schon benutzt → nur DEAKTIVIEREN (der nächste Shop-Abgleich setzt ihn im Shop auf Entwurf)
 * Ist ein Vater samt allen Kindern löschbar, wird er im Shop in EINEM Aufruf gelöscht (Woo nimmt die
 * Variationen mit) statt jede Variation einzeln – sonst dauert das bei tausenden Kindern ewig.
 *
 * Eingabe: CSV mit einer Spalte „artikelnummer_live“ oder „artikelnummer“ (sonst erste Spalte),
 *          Trenner ; oder , – z.B. transfer/live_aber_ausgeschlossen.csv.
 *
 * Aufruf (ohne --ausfuehren = Probelauf, ändert nichts):
 *   php scripts/artikel_liste_loeschen.php --datei="D:/ERP/transfer/live_aber_ausgeschlossen.csv"
 *   php scripts/artikel_liste_loeschen.php --datei="…" --ausfuehren
 * Vorher DB-Sicherung machen. Bericht landet neben der CSV als <datei>_bericht.txt.
 */

ini_set('memory_limit', '1024M');
set_time_limit(0);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../src/modules/artikel/ArtikelRepository.php';
require_once __DIR__ . '/../src/modules/shop/WooCommerceClient.php';

// Unbekannte Schalter (z.B. „--ausführen“ mit ü) nicht still ignorieren – sonst läuft nur ein Probelauf
foreach (array_slice($argv, 1) as $arg) {
    if (!preg_match('/^--(datei=.+|ausfuehren)$/', $arg)) {
        fwrite(STDERR, "Unbekannter Schalter: $arg (erlaubt: --datei=<csv> und --ausfuehren mit ue)\n");
        exit(1);
    }
}
$opt = getopt('', ['datei:', 'ausfuehren']);
$ausfuehren = isset($opt['ausfuehren']);
$pfad = $opt['datei'] ?? '';
if ($pfad === '' || !is_file($pfad)) {
    if ($pfad !== '') fwrite(STDERR, "Datei nicht gefunden: $pfad (gesucht von " . getcwd() . " aus)\n");
    fwrite(STDERR, "Aufruf: php scripts/artikel_liste_loeschen.php --datei=<csv> [--ausfuehren]\n");
    exit(1);
}

$db = Database::getInstance();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Logger braucht ohne Session einen Benutzer (wie Shop-Sync/Cron)
$systemId = (int)$db->query("SELECT id FROM benutzer WHERE username = 'system'")->fetchColumn();
if (!$systemId) { fwrite(STDERR, "Benutzer 'system' fehlt.\n"); exit(1); }
$_SESSION['benutzer'] = ['id' => $systemId];

// --- CSV lesen -------------------------------------------------------------------------------
$roh = file_get_contents($pfad);
if (str_starts_with($roh, "\xEF\xBB\xBF")) $roh = substr($roh, 3);
if (!mb_check_encoding($roh, 'UTF-8')) $roh = mb_convert_encoding($roh, 'UTF-8', 'Windows-1252');
$zeilen = array_values(array_filter(preg_split('/\r\n|\r|\n/', $roh), fn($z) => trim($z) !== ''));
$trenner = substr_count($zeilen[0] ?? '', ';') >= substr_count($zeilen[0] ?? '', ',') ? ';' : ',';
$kopf = array_map(fn($s) => mb_strtolower(trim($s)), str_getcsv(array_shift($zeilen), $trenner, '"', ''));
$spalte = array_search('artikelnummer_live', $kopf, true);
if ($spalte === false) $spalte = array_search('artikelnummer', $kopf, true);
if ($spalte === false) $spalte = 0;
$nummern = [];
foreach ($zeilen as $z) {
    $nr = trim(str_getcsv($z, $trenner, '"', '')[$spalte] ?? '');
    if ($nr !== '') $nummern[$nr] = true;
}
$nummern = array_keys($nummern);

$repo    = new ArtikelRepository();
$service = new ArtikelService();
$stHaupt = $db->prepare("SELECT id, artikelnummer, name, vaterartikel_id, aktiv FROM artikel WHERE artikelnummer = :nr");
$stKinder = $db->prepare("SELECT id, artikelnummer, name, aktiv FROM artikel WHERE vaterartikel_id = :id ORDER BY artikelnummer");

$z = ['liste' => count($nummern), 'nicht_gefunden' => 0, 'ist_kind' => 0,
      'geloescht' => 0, 'deaktiviert' => 0, 'schon_inaktiv' => 0, 'fehler' => 0, 'shop_vater_geloescht' => 0];
$bericht = [];
$sperrGruende = [];

/** Woo: ganzen Vater löschen und die Shop-IDs im ERP leeren, damit die Einzel-Löschungen den Shop nicht mehr anfassen */
function vaterImShopLoeschen(PDO $db, ArtikelRepository $repo, int $vaterId, array $kinderIds): void
{
    foreach ($repo->findShopReferenzenZumLoeschen($vaterId) as $ref) {
        $client = new WooCommerceClient($ref['wc_url'], (string)$ref['wc_key'], (string)$ref['wc_secret'], $ref['wp_username'], $ref['wp_app_password']);
        try {
            $client->loescheProdukt((string)$ref['external_id']);
        } catch (WooCommerceNotFoundException $e) {
            // schon weg
        }
        $ids = array_merge([$vaterId], $kinderIds);
        $in = implode(',', array_map('intval', $ids));
        $db->prepare("UPDATE artikel_shops SET external_id = NULL WHERE shop_id = :s AND artikel_id IN ($in)")
           ->execute(['s' => $ref['shop_id']]);
    }
}

foreach ($nummern as $nr) {
    $stHaupt->execute(['nr' => $nr]);
    $a = $stHaupt->fetch(PDO::FETCH_ASSOC);
    if (!$a) { $z['nicht_gefunden']++; $bericht[] = "NICHT GEFUNDEN  $nr"; continue; }
    if ($a['vaterartikel_id']) { $z['ist_kind']++; $bericht[] = "IST EIN KIND    $nr (übersprungen, nur Hauptartikel angeben)"; continue; }

    $stKinder->execute(['id' => $a['id']]);
    $kinder = $stKinder->fetchAll(PDO::FETCH_ASSOC);

    // Was ist löschbar?
    $kindSperren = [];
    foreach ($kinder as $k) $kindSperren[$k['id']] = $repo->findLoeschSperren((int)$k['id']);
    $vaterSperren = array_values(array_filter($repo->findLoeschSperren((int)$a['id']),
        fn($g) => !str_starts_with($g, 'Varianten')));   // Kinder werden ja vorher gelöscht
    $alleKinderFrei = !array_filter($kindSperren);
    $ganzLoeschbar = $alleKinderFrei && !$vaterSperren;

    $nKindLoesch = count(array_filter($kindSperren, fn($s) => !$s));
    $nKindBleib  = count($kinder) - $nKindLoesch;
    foreach (array_merge($vaterSperren, ...array_values($kindSperren)) as $g) {
        $sperrGruende[preg_replace('/\s*\(\d+\)$/', '', $g)] = ($sperrGruende[preg_replace('/\s*\(\d+\)$/', '', $g)] ?? 0) + 1;
    }

    $zeile = sprintf('%-11s %-22s %s – Kinder: %d löschen, %d deaktivieren',
        $ganzLoeschbar ? 'LÖSCHEN' : 'DEAKTIVIEREN', $nr, mb_substr($a['name'], 0, 50), $nKindLoesch, $nKindBleib);
    if ($vaterSperren) $zeile .= ' | benutzt: ' . implode(', ', $vaterSperren);
    elseif (!$alleKinderFrei) $zeile .= ' | Kinder benutzt: ' . implode(', ', array_unique(array_map(fn($g) => preg_replace('/\s*\(\d+\)$/', '', $g), array_merge(...array_values($kindSperren)))));
    $bericht[] = $zeile;

    if (!$ausfuehren) {
        $z[$ganzLoeschbar ? 'geloescht' : 'deaktiviert']++;
        continue;
    }

    try {
        if ($ganzLoeschbar && $kinder) {
            vaterImShopLoeschen($db, $repo, (int)$a['id'], array_column($kinder, 'id'));
            $z['shop_vater_geloescht']++;
        }
        // Kinder: freie löschen, benutzte deaktivieren
        foreach ($kinder as $k) {
            if (!$kindSperren[$k['id']]) {
                $r = $service->loescheEndgueltig((int)$k['id']);
                if (!$r['erfolg']) { $z['fehler']++; $bericht[] = "  FEHLER Kind {$k['artikelnummer']}: {$r['fehler']}"; }
            } elseif ($k['aktiv']) {
                $repo->deactivate((int)$k['id']);
            }
        }
        if ($ganzLoeschbar) {
            $r = $service->loescheEndgueltig((int)$a['id']);
            if ($r['erfolg']) $z['geloescht']++;
            else { $z['fehler']++; $bericht[] = "  FEHLER $nr: {$r['fehler']}"; }
        } else {
            if (!$a['aktiv']) $z['schon_inaktiv']++;
            $service->delete((int)$a['id']);   // Vater + restliche Kinder deaktivieren
            $z['deaktiviert']++;
        }
    } catch (Throwable $e) {
        $z['fehler']++;
        $bericht[] = "  FEHLER $nr: " . $e->getMessage();
    }
}

$text  = ($ausfuehren ? "AUSGEFÜHRT\n" : "PROBELAUF – nichts geändert\n") . str_repeat('=', 40) . "\n";
$text .= "Nummern in der Liste:        {$z['liste']}\n";
$text .= "  nicht gefunden:            {$z['nicht_gefunden']}\n";
$text .= "  sind Kinder (übersprungen): {$z['ist_kind']}\n";
$text .= ($ausfuehren ? "Endgültig gelöscht:          " : "Würden gelöscht:             ") . "{$z['geloescht']}\n";
$text .= ($ausfuehren ? "Nur deaktiviert (benutzt):   " : "Nur deaktiviert (benutzt):   ") . "{$z['deaktiviert']}\n";
if ($ausfuehren) $text .= "Fehler:                      {$z['fehler']}\n";
if ($sperrGruende) {
    $text .= "Warum nicht löschbar (Anzahl Artikel inkl. Kinder):\n";
    arsort($sperrGruende);
    foreach ($sperrGruende as $g => $n) $text .= "  $g: $n\n";
}
echo $text;
$berichtPfad = preg_replace('/\.csv$/i', '', $pfad) . '_bericht.txt';
file_put_contents($berichtPfad, $text . "\n" . implode("\n", $bericht) . "\n");
echo "Bericht: $berichtPfad\n";
