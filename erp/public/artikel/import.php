<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Database.php';

// ── Vorlage-Download ───────────────────────────────────────────────────────
if (isset($_GET['download_vorlage'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="artikel_import_vorlage.csv"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM damit Excel korrekt öffnet
    fputs($out, "\xEF\xBB\xBF");

    $kopfzeile = [
        'artikelnummer', 'name', 'artikeltyp', 'steuerklasse_satz', 'einheit_kuerzel',
        'hersteller_name', 'ean', 'brutto_vk', 'kurzbeschreibung', 'beschreibung',
        'inhalt_menge', 'inhalt_einheit', 'gewicht_artikel_g', 'herkunftsland',
        'grundpreis_bezugsmenge', 'ist_vater', 'aktiv', 'charge_pflicht', 'meldebestand',
    ];
    fputcsv($out, $kopfzeile, ';');

    // Beispielzeilen
    fputcsv($out, [
        'DROPS-ALPACA-0100', 'DROPS Alpaca Natur', 'GARN', '20', 'Kn',
        'DROPS Design', '4048514143861', '3.99', 'Weiches Alpaca-Garn', 'DROPS Alpaca ist ein weiches, leichtes Garn aus 65% Alpaca und 35% Wolle.',
        '50', 'g', '55', 'NO', '100', '0', '1', '1', '5',
    ], ';');
    fputcsv($out, [
        'DROPS-FLORA-0100', 'DROPS Flora Natur', 'GARN', '20', 'Kn',
        'DROPS Design', '', '4.49', '', '',
        '50', 'g', '55', 'NO', '100', '0', '1', '0', '',
    ], ';');
    fputcsv($out, [
        'RUNDNADEL-60-3MM', 'Rundnadel 60cm 3mm', 'NADEL', '20', 'Stk',
        '', '', '8.90', '', '',
        '', '', '25', 'DE', '', '0', '1', '0', '',
    ], ';');

    fclose($out);
    exit;
}

// ── Lookup-Tabellen einmal laden ───────────────────────────────────────────
$db = Database::getInstance();

$herstellerMap = [];
foreach ($db->query("SELECT id, name FROM hersteller WHERE aktiv = 1")->fetchAll() as $h) {
    $herstellerMap[mb_strtolower(trim($h['name']))] = (int)$h['id'];
}

$steuerMap = [];
foreach ($db->query("SELECT id, satz FROM steuerklassen WHERE aktiv = 1")->fetchAll() as $s) {
    $steuerMap[(string)(int)$s['satz']] = ['id' => (int)$s['id'], 'satz' => (float)$s['satz']];
}

$einheitMap = [];
foreach ($db->query("SELECT id, kuerzel FROM einheiten")->fetchAll() as $e) {
    $einheitMap[mb_strtolower(trim($e['kuerzel']))] = (int)$e['id'];
}

$erlaubteTypen = [];
foreach ($db->query("SELECT code FROM artikel_typen")->fetchAll() as $t) {
    $erlaubteTypen[] = $t['code'];
}

// ── CSV verarbeiten ────────────────────────────────────────────────────────
$ergebnisse   = [];
$importiert   = 0;
$fehlerAnzahl = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_datei']['tmp_name'])) {
    $tmpPfad = $_FILES['csv_datei']['tmp_name'];

    // BOM entfernen falls vorhanden (UTF-8 BOM = EF BB BF)
    $rohdaten = file_get_contents($tmpPfad);
    if (str_starts_with($rohdaten, "\xEF\xBB\xBF")) {
        $rohdaten = substr($rohdaten, 3);
    }
    // Windows-1252 → UTF-8 wenn kein gültiges UTF-8 (Standard bei Excel-Export auf DE-Windows)
    if (!mb_check_encoding($rohdaten, 'UTF-8')) {
        $rohdaten = mb_convert_encoding($rohdaten, 'UTF-8', 'Windows-1252');
    }
    // Windows-Zeilenenden normalisieren
    $rohdaten = str_replace("\r\n", "\n", $rohdaten);
    $rohdaten = str_replace("\r", "\n", $rohdaten);

    // Delimiter auto-detect: Tab oder Semikolon
    $ersteLinie = strtok($rohdaten, "\n");
    $delimiter  = str_contains($ersteLinie, "\t") ? "\t" : ';';

    $tmpNorm = tmpfile();
    fwrite($tmpNorm, $rohdaten);
    rewind($tmpNorm);

    $kopfzeile = fgetcsv($tmpNorm, 0, $delimiter);
    if (!$kopfzeile) {
        $_SESSION['fehler'] = ['CSV-Datei konnte nicht gelesen werden'];
    } else {
        $kopfzeile = array_map('trim', $kopfzeile);
        $service   = new ArtikelService();
        $zeileNr   = 1;

        while (($zeile = fgetcsv($tmpNorm, 0, $delimiter)) !== false) {
            $zeileNr++;
            if (count(array_filter($zeile, fn($v) => $v !== '')) === 0) continue;

            $row = [];
            foreach ($kopfzeile as $i => $spalte) {
                $row[$spalte] = isset($zeile[$i]) ? trim($zeile[$i]) : '';
            }

            $zeileFehler = [];

            // ── Pflichtfelder ──
            $artikelnummer = $row['artikelnummer'] ?? '';
            $name          = $row['name']          ?? '';
            $typCode       = strtoupper($row['artikeltyp'] ?? '');
            $steuerSatzStr = (string)(int)($row['steuerklasse_satz'] ?? '');
            $einheitKuerzel= mb_strtolower($row['einheit_kuerzel'] ?? '');

            if (empty($artikelnummer)) $zeileFehler[] = 'Artikelnummer fehlt';
            if (empty($name))          $zeileFehler[] = 'Name fehlt';
            if (empty($typCode))       $zeileFehler[] = 'Artikeltyp fehlt';
            elseif (!in_array($typCode, $erlaubteTypen))
                $zeileFehler[] = 'Unbekannter Artikeltyp "' . $typCode . '" (erlaubt: ' . implode('/', $erlaubteTypen) . ')';

            if (!isset($steuerMap[$steuerSatzStr]))
                $zeileFehler[] = 'Steuerklasse "' . $steuerSatzStr . '%" nicht gefunden (erlaubt: ' . implode('/', array_keys($steuerMap)) . ')';

            if (!isset($einheitMap[$einheitKuerzel]))
                $zeileFehler[] = 'Einheit "' . ($row['einheit_kuerzel'] ?? '') . '" nicht gefunden (erlaubt: ' . implode('/', array_keys($einheitMap)) . ')';

            if (!empty($zeileFehler)) {
                $ergebnisse[] = ['zeile' => $zeileNr, 'artikelnummer' => $artikelnummer, 'name' => $name, 'fehler' => $zeileFehler];
                $fehlerAnzahl++;
                continue;
            }

            // ── IDs auflösen ──
            $steuerklasseId = $steuerMap[$steuerSatzStr]['id'];
            $einheitId      = $einheitMap[$einheitKuerzel];

            $herstellerName = mb_strtolower(trim($row['hersteller_name'] ?? ''));
            $herstellerId   = $herstellerMap[$herstellerName] ?? null;

            // ── Optionale Felder ──
            $bruttoVk = $row['brutto_vk'] !== '' ? str_replace(',', '.', $row['brutto_vk']) : null;
            $nettoVk  = null;
            if ($bruttoVk !== null && is_numeric($bruttoVk)) {
                $bruttoVk = (float)$bruttoVk;
                $satz     = $steuerMap[$steuerSatzStr]['satz'];
                $nettoVk  = round($bruttoVk / (1 + $satz / 100), 4);
            } else {
                $bruttoVk = null;
            }

            $beschreibung   = $row['beschreibung'] !== '' ? $row['beschreibung'] : null;
            $inhaltMenge    = $row['inhalt_menge'] !== '' ? (float)str_replace(',', '.', $row['inhalt_menge']) : null;
            $inhaltEinheit  = $row['inhalt_einheit'] !== '' ? $row['inhalt_einheit'] : null;
            $gewichtArtikel = $row['gewicht_artikel_g'] !== '' ? (float)str_replace(',', '.', $row['gewicht_artikel_g']) : null;
            $herkunft       = $row['herkunftsland'] !== '' ? strtoupper(substr($row['herkunftsland'], 0, 2)) : null;
            $grundpreis     = $row['grundpreis_bezugsmenge'] !== '' ? (float)str_replace(',', '.', $row['grundpreis_bezugsmenge']) : null;
            $grundpreisAnzeigen = $grundpreis !== null ? 1 : 0;
            $istVater      = isset($row['ist_vater']) && $row['ist_vater'] !== '' ? (int)$row['ist_vater'] : 0;
            $aktiv         = $row['aktiv'] !== '' ? (int)$row['aktiv'] : 1;
            $chargePflicht = isset($row['charge_pflicht']) && $row['charge_pflicht'] !== '' ? (int)$row['charge_pflicht'] : 0;
            $meldebestand  = isset($row['meldebestand']) && $row['meldebestand'] !== '' ? (int)$row['meldebestand'] : null;

            $artikelData = [
                'artikelnummer'          => $artikelnummer,
                'name'                   => $name,
                'artikeltyp'             => $typCode,
                'steuerklasse_id'        => $steuerklasseId,
                'einheit_id'             => $einheitId,
                'hersteller_id'          => $herstellerId,
                'kurzbeschreibung'       => $row['kurzbeschreibung'] !== '' ? $row['kurzbeschreibung'] : null,
                'beschreibung'           => $beschreibung,
                'inhalt_menge'           => $inhaltMenge,
                'inhalt_einheit'         => $inhaltEinheit,
                'gewicht_artikel'        => $gewichtArtikel,
                'herkunftsland'          => $herkunft,
                'grundpreis_bezugsmenge' => $grundpreis,
                'grundpreis_anzeigen'    => $grundpreisAnzeigen,
                'aktiv'                  => $aktiv,
                'charge_pflicht'         => $chargePflicht,
                'meldebestand'           => $meldebestand,
                'brutto_vk'              => $bruttoVk,
                'netto_vk'               => $nettoVk,
                'ean_gtin13'             => $row['ean'] !== '' ? $row['ean'] : null,
            ];

            // meldebestand + ist_vater sind nicht im repo->insert(), daher nach save() separat
            unset($artikelData['meldebestand']);
            $result = $service->save($artikelData);

            if ($result['erfolg']) {
                $postUpdate = [];
                if ($meldebestand !== null) $postUpdate['meldebestand'] = $meldebestand;
                if ($istVater)              $postUpdate['ist_vater']    = 1;
                if (!empty($postUpdate)) {
                    $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($postUpdate)));
                    $postUpdate['id'] = $result['id'];
                    $db->prepare("UPDATE artikel SET $sets WHERE id = :id")->execute($postUpdate);
                }
                $ergebnisse[] = ['zeile' => $zeileNr, 'artikelnummer' => $artikelnummer, 'name' => $name, 'erfolg' => true, 'id' => $result['id']];
                $importiert++;
            } else {
                $ergebnisse[] = ['zeile' => $zeileNr, 'artikelnummer' => $artikelnummer, 'name' => $name, 'fehler' => $result['fehler']];
                $fehlerAnzahl++;
            }
        }
        fclose($tmpNorm);
    }
}

$pageTitle    = "Artikel importieren";
$activeModule = "artikel";

$actionBarContent = <<<HTML
<a href="liste.php" class="btn btn-secondary btn-sm">← Zurück zur Liste</a>
<a href="import.php?download_vorlage=1" class="btn btn-secondary btn-sm">⬇ CSV-Vorlage herunterladen</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <h2 style="margin:0 0 16px">Artikel-Import (CSV)</h2>

    <?php if (!empty($ergebnisse)): ?>
        <div style="margin-bottom:16px;padding:12px 16px;border-radius:6px;
            background:<?= $fehlerAnzahl === 0 ? '#f0fdf4' : ($importiert === 0 ? '#fef2f2' : '#fffbeb') ?>;
            border:1px solid <?= $fehlerAnzahl === 0 ? '#86efac' : ($importiert === 0 ? '#fca5a5' : '#fcd34d') ?>">
            <strong><?= $importiert ?> Artikel importiert</strong>
            <?php if ($fehlerAnzahl > 0): ?>
                · <span style="color:#dc2626"><?= $fehlerAnzahl ?> Zeilen mit Fehler</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Lade eine CSV-Datei mit Semikolon (;) als Trennzeichen hoch.
        Pflichtfelder: <strong>artikelnummer, name, artikeltyp, steuerklasse_satz, einheit_kuerzel</strong>.
        <a href="import.php?download_vorlage=1">Vorlage herunterladen</a> für die korrekte Spaltenreihenfolge und Beispieldaten.
    </p>

    <div style="background:#f8fafc;border:1px solid var(--color-border);border-radius:6px;padding:12px 16px;margin-bottom:20px">
        <strong>Erlaubte Werte:</strong><br>
        <span style="font-size:12px;color:var(--color-text-muted)">
            <strong>artikeltyp:</strong> GARN · NADEL · METERWARE · DOWNLOAD · SET · STANDARD<br>
            <strong>steuerklasse_satz:</strong> 20 · 10 · 0<br>
            <strong>einheit_kuerzel:</strong> Kn · m · g · Stk · Set<br>
            <strong>hersteller_name:</strong> <?= implode(' · ', array_keys($herstellerMap)) ?>
        </span>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
            <input type="file" name="csv_datei" accept=".csv,text/csv" required
                style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px">
            <button type="submit" class="btn btn-primary btn-sm">Importieren</button>
        </div>
    </form>
</div>

<?php if (!empty($ergebnisse)): ?>
<div class="card">
    <h3 style="margin:0 0 12px">Ergebnisse</h3>
    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:60px">Zeile</th>
                <th style="width:140px">Artikelnummer</th>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ergebnisse as $e): ?>
            <tr>
                <td style="color:var(--color-text-muted)"><?= $e['zeile'] ?></td>
                <td>
                    <?php if (!empty($e['erfolg']) && !empty($e['id'])): ?>
                        <a href="detail.php?id=<?= $e['id'] ?>"><?= htmlspecialchars($e['artikelnummer']) ?></a>
                    <?php else: ?>
                        <?= htmlspecialchars($e['artikelnummer'] ?: '–') ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($e['name'] ?: '–') ?></td>
                <td>
                    <?php if (!empty($e['erfolg'])): ?>
                        <span style="color:#16a34a">✓ Importiert</span>
                    <?php else: ?>
                        <span style="color:#dc2626">✗ <?= htmlspecialchars(implode('; ', $e['fehler'])) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
