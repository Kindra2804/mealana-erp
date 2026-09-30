<?php
/**
 * Importiert den "Eigenen Export" aus der JTL-Wawi: Lieferanten-EK-Preise
 * (-> artikel_lieferanten) und Lagerbestand/Charge (-> lagerbestand, IMMER
 * lager_id=1 "Ladengeschäft" — Jackys Vorgabe 2026-08-11, unabhängig vom
 * Warenlager-Feld in der CSV, das nur bei einem Bruchteil der Zeilen befüllt ist).
 *
 * Wiederholbar (Upsert): Lagerbestand über (artikel_id, lager_id, charge)
 * NULL-sicher abgeglichen (MySQLs UNIQUE-Constraint behandelt NULL-Chargen
 * sonst als "nie gleich" -> würde bei jedem Lauf neue Zeilen anlegen).
 * artikel_lieferanten über (artikel_id, lieferant_id) abgeglichen (kein
 * UNIQUE-Constraint in der Tabelle, Abgleich läuft hier in PHP).
 *
 * Lieferanten-Zuordnung ist eine feste Liste (siehe LIEFERANT_MAP_OVERRIDES),
 * mit Jacky am 2026-08-11 einzeln durchgesprochen. Neue, bisher unbekannte
 * Lieferantennamen in einem künftigen Export würden hier NICHT automatisch
 * erkannt (bewusst, siehe Fehlerliste am Ende).
 *
 * standard_lieferant wird nur gesetzt, wenn der Artikel noch KEINEN anderen
 * Standard-Lieferanten hat (überschreibt nie eine bestehende Zuordnung -- die
 * fließt in die Marge-Auswertung ein, siehe StatistikRepository).
 *
 * Aufruf:
 *   php scripts/jtl_eigener_export_import.php <csv-pfad> [--dry-run]
 */

ini_set('memory_limit', '2048M');

require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/modules/import/JtlCsvReader.php';

/** JTL-Lieferantname (Original-Schreibweise) -> unsere lieferanten.id, wo der Name nicht 1:1 matcht. */
const LIEFERANT_MAP_OVERRIDES = [
    'Strickimicki / HEITERE AUSSICHTEN - Jens Aldag' => 33, // = bestehender Lieferant "Strickimicki"
];

/** Lieferantnamen, die bewusst NICHT importiert werden (kein Lieferant, irrelevant). */
const LIEFERANT_IGNORIEREN = [
    'Mineraliengroßhandel.com',
];

$csvPfad = $argv[1] ?? null;
$dryRun  = in_array('--dry-run', $argv, true);

if (!$csvPfad || !is_file($csvPfad)) {
    fwrite(STDERR, "Nutzung: php scripts/jtl_eigener_export_import.php <csv-pfad> [--dry-run]\n");
    exit(1);
}

$db = Database::getInstance();

// Lieferanten-Namen (kleingeschrieben) -> id, für den direkten Namens-Match
$lieferantByName = [];
foreach ($db->query("SELECT id, name FROM lieferanten")->fetchAll() as $l) {
    $lieferantByName[mb_strtolower(trim($l['name']))] = (int)$l['id'];
}

// Artikelnummer -> id, einmalig geladen (schneller als 26k Einzel-Queries)
$artikelByNr = [];
$chargePflicht = [];
foreach ($db->query("SELECT id, artikelnummer, charge_pflicht FROM artikel")->fetchAll() as $a) {
    $artikelByNr[$a['artikelnummer']] = (int)$a['id'];
    if ((int)$a['charge_pflicht'] === 1) $chargePflicht[(int)$a['id']] = true;
}

/**
 * Status einer Lagerbestand-Zeile -- gleiche Regel wie LagerService::wareneingang():
 * mit Charge 'erfasst'; ohne Charge bei Charge-Pflicht UND vorhandenem Bestand
 * 'nachzutragen' (erscheint in der Nachtragsliste, Kasse fragt die Charge ab), sonst
 * 'unbekannt'. Leere Zeilen (Bestand <= 0, im JTL-Export zu Tausenden enthalten)
 * bewusst nicht -- da gibt es nichts nachzutragen. Früher stand hier fest 'unbekannt'
 * -> 527 Pflicht-Artikel mit Bestand waren weder in der Nachtragsliste noch an der
 * Kasse nachtragbar (behoben 2026-09-30).
 */
function chargeStatus(?string $charge, bool $pflicht, float $bestand): string
{
    if ($charge !== null) return 'erfasst';
    return ($pflicht && $bestand > 0) ? 'nachzutragen' : 'unbekannt';
}

// Artikel, die bereits einen Standard-Lieferanten haben -> nie überschreiben
$hatStandardLieferant = array_flip($db->query("
    SELECT DISTINCT artikel_id FROM artikel_lieferanten WHERE standard_lieferant = 1
")->fetchAll(PDO::FETCH_COLUMN));

echo ($dryRun ? '[DRY RUN] ' : '') . "Importiere $csvPfad ...\n";
$rows = JtlCsvReader::lese($csvPfad);
echo count($rows) . " Zeilen gelesen.\n";

$lagerNeu = 0;
$lagerAktualisiert = 0;
$liefNeu = 0;
$liefAktualisiert = 0;
$artikelNichtGefunden = [];
$lieferantNichtGefunden = [];
$lieferantIgnoriert = 0;

$lagerSelect = $db->prepare("
    SELECT id, bestand, charge_status FROM lagerbestand WHERE artikel_id = :aid AND lager_id = 1 AND charge <=> :charge
");
$lagerInsert = $db->prepare("
    INSERT INTO lagerbestand (artikel_id, lager_id, charge, charge_status, bestand, mindestbestand)
    VALUES (:aid, 1, :charge, :charge_status, :bestand, 0)
");
$lagerUpdate = $db->prepare("UPDATE lagerbestand SET bestand = :bestand, charge_status = :charge_status WHERE id = :id");

$liefSelect = $db->prepare("SELECT id FROM artikel_lieferanten WHERE artikel_id = :aid AND lieferant_id = :lid");
$liefInsert = $db->prepare("
    INSERT INTO artikel_lieferanten
        (artikel_id, lieferant_id, artikelnummer_lieferant, netto_ek, brutto_ek, waehrung,
         vpe_menge, lieferzeit_tage, mindestabnahme, standard_lieferant, aktiv)
    VALUES
        (:aid, :lid, :artikelnummer_lieferant, :netto_ek, :brutto_ek, :waehrung,
         :vpe_menge, :lieferzeit_tage, :mindestabnahme, :standard_lieferant, 1)
");
$liefUpdate = $db->prepare("
    UPDATE artikel_lieferanten SET
        artikelnummer_lieferant = :artikelnummer_lieferant,
        netto_ek = :netto_ek, brutto_ek = :brutto_ek, waehrung = :waehrung,
        vpe_menge = :vpe_menge, lieferzeit_tage = :lieferzeit_tage,
        mindestabnahme = :mindestabnahme
    WHERE id = :id
");

function komma(?string $wert): ?float
{
    $wert = trim((string)$wert);
    return $wert === '' ? null : (float)str_replace(',', '.', $wert);
}

foreach ($rows as $row) {
    $nr = trim($row['Artikelnummer'] ?? '');
    $artikelId = $artikelByNr[$nr] ?? null;
    if ($artikelId === null) {
        $artikelNichtGefunden[$nr] = true;
        continue;
    }

    // --- Lagerbestand (immer lager_id=1) ---
    $charge = trim($row['Charge'] ?? '') ?: null;
    $bestand = komma($row['BestandAktuell'] ?? '0') ?? 0.0;

    $status = chargeStatus($charge, isset($chargePflicht[$artikelId]), $bestand);

    $lagerSelect->execute(['aid' => $artikelId, 'charge' => $charge]);
    $bestehend = $lagerSelect->fetch();
    if ($bestehend) {
        // Status nur von 'unbekannt' auf den korrekten Wert heben -- ein
        // 'nachzutragen'/'erfasst' aus dem laufenden Betrieb nie zurückstufen
        $neuerStatus = $bestehend['charge_status'] === 'unbekannt' ? $status : $bestehend['charge_status'];
        if (!$dryRun && ((float)$bestehend['bestand'] !== $bestand || $neuerStatus !== $bestehend['charge_status'])) {
            $lagerUpdate->execute(['bestand' => $bestand, 'charge_status' => $neuerStatus, 'id' => $bestehend['id']]);
        }
        $lagerAktualisiert++;
    } else {
        if (!$dryRun) {
            $lagerInsert->execute([
                'aid' => $artikelId,
                'charge' => $charge,
                'charge_status' => $status,
                'bestand' => $bestand,
            ]);
        }
        $lagerNeu++;
    }

    // --- Lieferant/EK-Preis ---
    $liefName = trim($row['LieferantName'] ?? '');
    if ($liefName === '') {
        continue;
    }
    if (in_array($liefName, LIEFERANT_IGNORIEREN, true)) {
        $lieferantIgnoriert++;
        continue;
    }

    $lieferantId = LIEFERANT_MAP_OVERRIDES[$liefName] ?? ($lieferantByName[mb_strtolower($liefName)] ?? null);
    if ($lieferantId === null) {
        $lieferantNichtGefunden[$liefName] = true;
        continue;
    }

    $istStandard = ((string)($row['LieferantIstStandard'] ?? '0') === '1') && !isset($hatStandardLieferant[$artikelId]);

    $liefData = [
        'aid' => $artikelId,
        'lid' => $lieferantId,
        'artikelnummer_lieferant' => trim($row['LieferantArtikelnummer'] ?? '') ?: null,
        'netto_ek' => komma($row['LieferantEKNetto'] ?? ''),
        'brutto_ek' => komma($row['LieferantEKBrutto'] ?? ''),
        'waehrung' => trim($row['LieferantWaehrung'] ?? '') ?: 'EUR',
        'vpe_menge' => (int)round(komma($row['LieferantVPEMenge'] ?? '') ?? 0),
        'lieferzeit_tage' => (int)round(komma($row['LieferantLieferzeit'] ?? '') ?? 0),
        'mindestabnahme' => komma($row['LieferantMindestabnahme'] ?? ''),
    ];

    $liefSelect->execute(['aid' => $artikelId, 'lid' => $lieferantId]);
    $bestehend = $liefSelect->fetch();
    if ($bestehend) {
        if (!$dryRun) {
            $updateData = $liefData;
            unset($updateData['aid'], $updateData['lid']);
            $updateData['id'] = $bestehend['id'];
            $liefUpdate->execute($updateData);
        }
        $liefAktualisiert++;
    } else {
        if (!$dryRun) {
            $liefData['standard_lieferant'] = $istStandard ? 1 : 0;
            $liefInsert->execute($liefData);
            if ($istStandard) {
                $hatStandardLieferant[$artikelId] = true;
            }
        }
        $liefNeu++;
    }
}

echo "\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "Lagerbestand: $lagerNeu neu, $lagerAktualisiert bereits vorhanden/aktualisiert\n";
echo ($dryRun ? '[DRY RUN] ' : '') . "Lieferant-EK: $liefNeu neu, $liefAktualisiert bereits vorhanden/aktualisiert\n";
echo "$lieferantIgnoriert Zeilen mit ignoriertem Lieferanten übersprungen\n";
echo count($artikelNichtGefunden) . " distinct Artikelnummern ohne Treffer in unserer artikel-Tabelle (übersprungen)\n";

if (!empty($lieferantNichtGefunden)) {
    echo "\n" . count($lieferantNichtGefunden) . " Lieferantennamen ohne Zuordnung (nicht importiert):\n";
    foreach (array_keys($lieferantNichtGefunden) as $n) {
        echo "  - $n\n";
    }
}

echo "\nFertig.\n";
