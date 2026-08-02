<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/import/JtlVaterKindImportService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service = new ArtikelService();
$db      = Database::getInstance();

$kategorienBaum = $service->getKategorienBaum();
$alleArtikeltypen = $service->getAllArtikelTypen();

/** Rendert die Kategorien als eingerückte <option>-Liste, damit gleichnamige Unterkategorien
 *  (z.B. mehrere "Zubehör" unter verschiedenen Hauptkategorien) unterscheidbar bleiben. */
function renderKategorieOptionen(array $knoten, int $tiefe = 0): void
{
    foreach ($knoten as $k) {
        $praefix = str_repeat('— ', $tiefe);
        echo '<option value="' . $k['id'] . '">' . htmlspecialchars($praefix . $k['name']) . '</option>';
        if (!empty($k['kinder'])) {
            renderKategorieOptionen($k['kinder'], $tiefe + 1);
        }
    }
}
$alleArtikelgruppen = $db->query("
    SELECT id, konto_nr, name FROM artikel_gruppen WHERE aktiv = 1 ORDER BY sortierung, konto_nr
")->fetchAll();
$alleEinheiten = $service->getAllEinheiten();

/** Rendert das Artikeltyp+Artikelgruppe-Dropdown-Paar für eine einzelne Zeile in der
 *  Kontrollliste (Vater ODER normaler Artikel) -- gemischte Kategorien können pro Artikel
 *  unterschiedliche Werte brauchen, daher nicht mehr global für den ganzen Import. */
function renderArtikeltypGruppeFelder(string $namePrefix, array $alleArtikeltypen, array $alleArtikelgruppen, string $standardTyp, int $standardGruppeId): void
{
    ?>
    <div>
        <label class="erp-label" style="font-size:11px">Artikeltyp</label>
        <select name="<?= $namePrefix ?>[artikeltyp_code]" class="erp-select" style="width:100%">
            <option value="">– wählen –</option>
            <?php foreach ($alleArtikeltypen as $t): ?>
                <option value="<?= htmlspecialchars($t['code']) ?>" <?= $t['code'] === $standardTyp ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="erp-label" style="font-size:11px">Artikelgruppe</label>
        <select name="<?= $namePrefix ?>[artikel_gruppe_id]" class="erp-select" style="width:100%">
            <option value="">– wählen –</option>
            <?php foreach ($alleArtikelgruppen as $g): ?>
                <option value="<?= $g['id'] ?>" <?= (int) $g['id'] === $standardGruppeId ? 'selected' : '' ?>><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

$vorschau = null;
$fehler   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_stammdaten']['tmp_name'])) {
    $kategorieId  = (int) ($_POST['kategorie_id'] ?? 0);
    // Nur noch eine VORAUSWAHL zum Vorbefüllen der Pro-Artikel-Dropdowns in der Kontrollliste
    // (siehe unten) -- gemischte Kategorien können Väter/normale Artikel mit unterschiedlichem
    // Artikeltyp/Artikelgruppe enthalten (z.B. Garn + Zubehör in derselben "Sale"-Kategorie),
    // daher nicht mehr global für den ganzen Import bindend.
    $artikeltypStandard      = trim($_POST['artikeltyp_code'] ?? '');
    $artikelGruppeIdStandard = (int) ($_POST['artikel_gruppe_id'] ?? 0);

    if ($kategorieId <= 0) {
        $fehler[] = 'Bitte eine Kategorie auswählen.';
    } else {
        $importService = new JtlVaterKindImportService();
        $mitKindern     = $importService->parseMitKindern($_FILES['csv_stammdaten']['tmp_name']);
        // Variationskombinationen-CSV ist optional -- fehlt sie (z.B. Kategorie besteht
        // nur aus normalen Artikeln ohne jede Vater/Kind-Variante), gibt es einfach keine
        // Väter/Kinder zu erkennen, die Stammdaten-Zeilen laufen komplett über den
        // "normale Artikel"-Zweig.
        $kombisProVater = !empty($_FILES['csv_kombinationen']['tmp_name'])
            ? $importService->parseVariationskombinationen($_FILES['csv_kombinationen']['tmp_name'])
            : [];
        $vorschauDaten  = $importService->baueVorschau($mitKindern, $kombisProVater);

        if (empty($vorschauDaten['vaeter']) && empty($vorschauDaten['normale'])) {
            if ($vorschauDaten['vater_ohne_kombidatei_anzahl'] > 0) {
                // Stammdaten wurden erkannt (Väter gefunden), aber KEINE ihrer Artikelnummern
                // kommt in der Kombinationen-Datei vor -- typischerweise weil die falsche
                // Kombinationen-CSV hochgeladen wurde (z.B. Export einer anderen Kategorie).
                $fehler[] = "Es wurden {$vorschauDaten['vater_ohne_kombidatei_anzahl']} Vater-Artikel in der Stammdaten-CSV gefunden, "
                    . 'aber keiner davon kommt in der Variationskombinationen-CSV vor. Die beiden Dateien passen '
                    . 'vermutlich nicht zusammen (falsche/veraltete Kombinationen-Datei hochgeladen, z.B. Export einer anderen Kategorie?). '
                    . 'Bitte die Variationskombinationen-CSV gezielt für diese Kategorie neu aus JTL exportieren.';
            } else {
                $fehler[] = 'Keine Artikel erkannt. Bitte Dateien prüfen (Stammdaten-CSV + Variationskombinationen-CSV vertauscht?).';
            }
        } else {
            $_SESSION['jtl_import_vorschau']            = $vorschauDaten['vaeter'];
            $_SESSION['jtl_import_vorschau_normale']    = $vorschauDaten['normale'];
            $_SESSION['jtl_import_einheiten_unbekannt'] = $vorschauDaten['einheiten_unbekannt'];
            $_SESSION['jtl_import_verwaiste_kinder']    = $vorschauDaten['verwaiste_kinder_anzahl'];
            $_SESSION['jtl_import_vater_ohne_kombidatei'] = $vorschauDaten['vater_ohne_kombidatei_anzahl'];
            $_SESSION['jtl_import_kategorie_id']      = $kategorieId;
            $_SESSION['jtl_import_artikeltyp_standard']      = $artikeltypStandard;
            $_SESSION['jtl_import_artikel_gruppe_id_standard'] = $artikelGruppeIdStandard;
            $vorschau = $vorschauDaten;
        }
    }
} elseif (!empty($_SESSION['jtl_import_vorschau']) || !empty($_SESSION['jtl_import_vorschau_normale'])) {
    // Zurück von der Kontrollliste (z.B. Validierungsfehler beim Commit) -> Vorschau erneut zeigen
    $vorschau = [
        'vaeter'                       => $_SESSION['jtl_import_vorschau'] ?? [],
        'normale'                      => $_SESSION['jtl_import_vorschau_normale'] ?? [],
        'einheiten_unbekannt'          => $_SESSION['jtl_import_einheiten_unbekannt'] ?? [],
        'verwaiste_kinder_anzahl'      => $_SESSION['jtl_import_verwaiste_kinder'] ?? 0,
        'vater_ohne_kombidatei_anzahl' => $_SESSION['jtl_import_vater_ohne_kombidatei'] ?? 0,
    ];
}

$artikeltypStandard      = $_SESSION['jtl_import_artikeltyp_standard'] ?? '';
$artikelGruppeIdStandard = (int) ($_SESSION['jtl_import_artikel_gruppe_id_standard'] ?? 0);

if (isset($_GET['neu'])) {
    unset($_SESSION['jtl_import_vorschau'], $_SESSION['jtl_import_vorschau_normale'], $_SESSION['jtl_import_einheiten_unbekannt'],
          $_SESSION['jtl_import_verwaiste_kinder'], $_SESSION['jtl_import_vater_ohne_kombidatei'],
          $_SESSION['jtl_import_kategorie_id'], $_SESSION['jtl_import_artikeltyp_standard'], $_SESSION['jtl_import_artikel_gruppe_id_standard']);
    header('Location: jtl_import.php');
    exit;
}

$pageTitle    = 'JTL Vater+Kind-Import';
$activeModule = 'artikel';
$actionBarContent = <<<HTML
<a href="liste.php" class="btn btn-secondary btn-sm">← Zurück zur Liste</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="margin-bottom:16px;border-color:#fca5a5;background:#fef2f2">
        <strong style="color:#dc2626">Fehler:</strong>
        <ul style="margin:8px 0 0 20px">
            <?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($vorschau === null): ?>

<div class="card">
    <h2 style="margin:0 0 16px">JTL Vater+Kind-Import mit Achsenerkennung</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Legt aus zwei JTL-Exporten (Stammdaten "mit Kindern" + "Variationskombinationen") neue Vater-Artikel
        mit ihren Kind-Varianten inkl. erkannter Achsen an. Erkennt außerdem "normale" Einzelartikel in
        derselben Stammdaten-CSV (kein Vater, kein Kind) und legt sie separat an. Die Kategorie gilt für
        den kompletten Import-Durchlauf. Artikeltyp/Artikelgruppe hier sind nur eine Vorauswahl zum
        Vorbefüllen — da gemischte Kategorien Väter/Artikel mit unterschiedlichem Artikeltyp oder
        Artikelgruppe enthalten können (z.B. Garn + Zubehör in derselben Kategorie), lässt sich das
        pro Artikel in der Kontrollliste noch ändern.
        <strong>Artikel, die bereits existieren (gleiche Artikelnummer), werden nicht erneut angelegt —
        es wird nur die Kategorie zusätzlich zugewiesen, alle anderen Daten bleiben unangetastet.</strong>
        Vor dem eigentlichen Import wird eine Kontrollliste zur Korrektur angezeigt.
    </p>

    <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:800px;margin-bottom:20px">
            <div>
                <label class="erp-label">Stammdaten-CSV ("mit Kindern") *</label>
                <input type="file" name="csv_stammdaten" accept=".csv,text/csv" required
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
            </div>
            <div>
                <label class="erp-label">Variationskombinationen-CSV (optional — nur bei Vater+Kind-Artikeln nötig)</label>
                <input type="file" name="csv_kombinationen" accept=".csv,text/csv"
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
            </div>

            <div>
                <label class="erp-label">Kategorie *</label>
                <select name="kategorie_id" class="erp-select" style="width:100%" required>
                    <option value="">– wählen –</option>
                    <?php renderKategorieOptionen($kategorienBaum); ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikeltyp (Vorauswahl, pro Artikel änderbar)</label>
                <select name="artikeltyp_code" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach ($alleArtikeltypen as $t): ?>
                        <option value="<?= htmlspecialchars($t['code']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikelgruppe (Vorauswahl, pro Artikel änderbar)</label>
                <select name="artikel_gruppe_id" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach ($alleArtikelgruppen as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-sm">Dateien einlesen &amp; Vorschau zeigen</button>
    </form>
</div>

<?php else: ?>

<?php
$einheitenUnbekannt   = $vorschau['einheiten_unbekannt'];
$vaeter               = $vorschau['vaeter'];
$normale              = $vorschau['normale'] ?? [];
$verwaisteKinderAnzahl = $vorschau['verwaiste_kinder_anzahl'] ?? 0;
$vaterOhneKombidatei   = $vorschau['vater_ohne_kombidatei_anzahl'] ?? 0;
$anzahlKinder         = array_sum(array_map(fn($v) => count($v['kinder']), $vaeter));
?>

<div class="card">
    <h2 style="margin:0 0 8px">Kontrollliste — <?= count($vaeter) ?> Väter, <?= $anzahlKinder ?> Kinder, <?= count($normale) ?> normale Artikel</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Artikelnummern und Namen können hier noch korrigiert werden. Es wird noch nichts in die Datenbank geschrieben —
        der eigentliche Import startet erst mit Klick auf "Import starten" unten. Artikel mit dem Badge
        "bereits vorhanden" existieren schon (gleiche Artikelnummer) — bei ihnen wird beim Import nur die
        Kategorie zusätzlich zugewiesen, alle anderen Felder bleiben unangetastet.
        <a href="jtl_import.php?neu=1">Abbrechen &amp; neu beginnen</a>
    </p>

    <?php if ($verwaisteKinderAnzahl > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong><?= $verwaisteKinderAnzahl ?> Kind-Zeile(n) übersprungen</strong> — Stammdaten-CSV markiert sie als
            Kind eines Vaters, aber die Variationskombinationen-CSV enthält für sie keine Zeile (Achsen unbekannt).
            Sie wurden weder als Vater/Kind noch als normaler Artikel importiert.
        </div>
    <?php endif; ?>

    <?php if ($vaterOhneKombidatei > 0): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong><?= $vaterOhneKombidatei ?> Vater-Artikel übersprungen</strong> — als Vater markiert, aber keine
            Variationskombinationen-CSV hochgeladen (oder sie enthält diese Väter nicht). Ohne Kombinationsdaten
            sind die Achsen/Kinder unbekannt, daher wurden sie nicht importiert.
        </div>
    <?php endif; ?>

    <form method="POST" action="jtl_import_commit.php">
    <?php if (!empty($einheitenUnbekannt)): ?>
        <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin-bottom:20px">
            <strong>Einheiten-Mapping nötig</strong> — diese Verkaufseinheiten aus der CSV entsprechen keiner bestehenden Einheit:
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px,1fr));gap:10px;margin-top:10px">
                <?php foreach ($einheitenUnbekannt as $roh): ?>
                    <div>
                        <label class="erp-label"><?= $roh === '' ? '(leer)' : htmlspecialchars($roh) ?></label>
                        <select name="einheit_mapping[<?= base64_encode($roh) ?>]" class="erp-select" style="width:100%">
                            <option value="">– nicht zuordnen –</option>
                            <?php foreach ($alleEinheiten as $e): ?>
                                <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name'] . ' (' . $e['kuerzel'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php foreach ($vaeter as $vIdx => $v): ?>
        <?php
        $hatWarnung = !$v['hersteller_treffer'] || array_filter($v['kinder'], fn($k) => $k['stammdaten_fehlen']);
        $achsenText = implode(', ', array_map(fn($a) => $a['name'] . ': ' . $a['wert'], $v['achsen']));
        ?>
        <details style="margin-bottom:10px;border:1px solid var(--color-border);border-radius:6px" <?= $hatWarnung ? 'open' : '' ?>>
            <summary style="padding:10px 14px;cursor:pointer;display:flex;align-items:center;gap:10px">
                <strong><?= htmlspecialchars($v['artikelnummer']) ?></strong>
                <span><?= htmlspecialchars($v['name']) ?></span>
                <span style="color:var(--color-text-muted);font-size:12px"><?= count($v['kinder']) ?> Kinder</span>
                <?php if (!empty($v['bereits_vorhanden'])): ?>
                    <span style="background:#eff6ff;color:#2563eb;padding:2px 8px;border-radius:4px;font-size:12px">bereits vorhanden → nur Kategorie</span>
                <?php endif; ?>
                <?php if (!$v['hersteller_treffer']): ?>
                    <span style="background:#fef2f2;color:#dc2626;padding:2px 8px;border-radius:4px;font-size:12px">Hersteller unbekannt: "<?= htmlspecialchars($v['hersteller_name']) ?>"</span>
                <?php endif; ?>
            </summary>
            <div style="padding:0 14px 14px">
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:10px;margin:10px 0">
                    <div>
                        <label class="erp-label">Vater-Artikelnummer</label>
                        <input type="text" name="vater[<?= $vIdx ?>][artikelnummer]" value="<?= htmlspecialchars($v['artikelnummer']) ?>" class="erp-select" style="width:100%">
                    </div>
                    <div>
                        <label class="erp-label">Vater-Name</label>
                        <input type="text" name="vater[<?= $vIdx ?>][name]" value="<?= htmlspecialchars($v['name']) ?>" class="erp-select" style="width:100%">
                    </div>
                </div>
                <?php if (empty($v['bereits_vorhanden'])): ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:0 0 10px">
                    <?php renderArtikeltypGruppeFelder("vater[$vIdx]", $alleArtikeltypen, $alleArtikelgruppen, $artikeltypStandard, $artikelGruppeIdStandard); ?>
                </div>
                <?php endif; ?>
                <p style="font-size:12px;color:var(--color-text-muted);margin:0 0 10px">Erkannte Achsen: <?= $achsenText !== '' ? htmlspecialchars($achsenText) : '–' ?></p>

                <table class="erp-table">
                    <thead>
                        <tr>
                            <th style="width:160px">Kind-Artikelnummer</th>
                            <th>Kind-Name</th>
                            <th>Achsenwerte</th>
                            <th style="width:90px">Preis brutto</th>
                            <th style="width:60px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($v['kinder'] as $kIdx => $k): ?>
                        <tr>
                            <td><input type="text" name="vater[<?= $vIdx ?>][kinder][<?= $kIdx ?>][artikelnummer]" value="<?= htmlspecialchars($k['artikelnummer']) ?>" class="erp-select" style="width:100%"></td>
                            <td><input type="text" name="vater[<?= $vIdx ?>][kinder][<?= $kIdx ?>][name]" value="<?= htmlspecialchars($k['name']) ?>" class="erp-select" style="width:100%"></td>
                            <td style="font-size:12px"><?= htmlspecialchars(implode(', ', array_map(fn($a) => $a['name'] . ': ' . $a['wert'], $k['achsen']))) ?></td>
                            <td><?= $k['brutto_vk'] !== null ? number_format($k['brutto_vk'], 2, ',', '.') : '–' ?></td>
                            <td>
                                <?php if (!empty($k['bereits_vorhanden'])): ?>
                                    <span style="background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-size:11px">bereits vorhanden → nur Kategorie</span>
                                <?php elseif ($k['stammdaten_fehlen']): ?>
                                    <span style="background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px">Stammdaten fehlen</span>
                                <?php endif; ?>
                                <?php if (!empty($k['ist_auslauf'])): ?>
                                    <span style="background:#fff7ed;color:#c2410c;padding:2px 6px;border-radius:4px;font-size:11px" title="Barbaras &quot;#&quot;-Markierung erkannt">🔶 Auslauf</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endforeach; ?>

    <?php if (!empty($normale)): ?>
        <h3 style="margin:20px 0 10px">Normale Artikel (kein Vater, kein Kind)</h3>
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:160px">Artikelnummer</th>
                    <th>Name</th>
                    <th style="width:150px">Artikeltyp</th>
                    <th style="width:180px">Artikelgruppe</th>
                    <th style="width:90px">Preis brutto</th>
                    <th style="width:220px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($normale as $nIdx => $n): ?>
                <tr>
                    <td><input type="text" name="normal[<?= $nIdx ?>][artikelnummer]" value="<?= htmlspecialchars($n['artikelnummer']) ?>" class="erp-select" style="width:100%"></td>
                    <td><input type="text" name="normal[<?= $nIdx ?>][name]" value="<?= htmlspecialchars($n['name']) ?>" class="erp-select" style="width:100%"></td>
                    <?php if (empty($n['bereits_vorhanden'])): ?>
                        <td><select name="normal[<?= $nIdx ?>][artikeltyp_code]" class="erp-select" style="width:100%">
                            <option value="">– wählen –</option>
                            <?php foreach ($alleArtikeltypen as $t): ?>
                                <option value="<?= htmlspecialchars($t['code']) ?>" <?= $t['code'] === $artikeltypStandard ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select></td>
                        <td><select name="normal[<?= $nIdx ?>][artikel_gruppe_id]" class="erp-select" style="width:100%">
                            <option value="">– wählen –</option>
                            <?php foreach ($alleArtikelgruppen as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= (int) $g['id'] === $artikelGruppeIdStandard ? 'selected' : '' ?>><?= htmlspecialchars($g['konto_nr'] . ' — ' . $g['name']) ?></option>
                            <?php endforeach; ?>
                        </select></td>
                    <?php else: ?>
                        <td colspan="2" style="color:var(--color-text-muted);font-size:12px">–</td>
                    <?php endif; ?>
                    <td><?= $n['brutto_vk'] !== null ? number_format($n['brutto_vk'], 2, ',', '.') : '–' ?></td>
                    <td>
                        <?php if (!empty($n['bereits_vorhanden'])): ?>
                            <span style="background:#eff6ff;color:#2563eb;padding:2px 6px;border-radius:4px;font-size:11px">bereits vorhanden → nur Kategorie</span>
                        <?php endif; ?>
                        <?php if (!$n['hersteller_treffer']): ?>
                            <span style="background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px">Hersteller unbekannt: "<?= htmlspecialchars($n['hersteller_name']) ?>"</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

        <div style="margin-top:16px">
            <button type="submit" class="btn btn-primary btn-sm">Import starten</button>
            <a href="jtl_import.php?neu=1" class="btn btn-secondary btn-sm">Abbrechen</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
