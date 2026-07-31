<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/import/JtlVaterKindImportService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service = new ArtikelService();
$db      = Database::getInstance();

$alleKategorien = $service->getAlleKategorien();
$alleArtikeltypen = $service->getAllArtikelTypen();
$alleArtikelgruppen = $db->query("
    SELECT id, konto_nr, name FROM artikel_gruppen WHERE aktiv = 1 ORDER BY sortierung, konto_nr
")->fetchAll();
$alleEinheiten = $service->getAllEinheiten();

$vorschau = null;
$fehler   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_stammdaten']['tmp_name']) && !empty($_FILES['csv_kombinationen']['tmp_name'])) {
    $kategorieId  = (int) ($_POST['kategorie_id'] ?? 0);
    $artikeltyp   = trim($_POST['artikeltyp_code'] ?? '');
    $artikelGruppeId = (int) ($_POST['artikel_gruppe_id'] ?? 0);

    if ($kategorieId <= 0 || $artikeltyp === '' || $artikelGruppeId <= 0) {
        $fehler[] = 'Bitte Kategorie, Artikeltyp und Artikelgruppe auswählen.';
    } else {
        $importService = new JtlVaterKindImportService();
        $mitKindern     = $importService->parseMitKindern($_FILES['csv_stammdaten']['tmp_name']);
        $kombisProVater = $importService->parseVariationskombinationen($_FILES['csv_kombinationen']['tmp_name']);
        $vorschauDaten  = $importService->baueVorschau($mitKindern, $kombisProVater);

        if (empty($vorschauDaten['vaeter'])) {
            $fehler[] = 'Keine Vater-Artikel erkannt. Bitte Dateien prüfen (Stammdaten-CSV + Variationskombinationen-CSV vertauscht?).';
        } else {
            $_SESSION['jtl_import_vorschau']          = $vorschauDaten['vaeter'];
            $_SESSION['jtl_import_einheiten_unbekannt'] = $vorschauDaten['einheiten_unbekannt'];
            $_SESSION['jtl_import_kategorie_id']      = $kategorieId;
            $_SESSION['jtl_import_artikeltyp_code']   = $artikeltyp;
            $_SESSION['jtl_import_artikel_gruppe_id'] = $artikelGruppeId;
            $vorschau = $vorschauDaten;
        }
    }
} elseif (!empty($_SESSION['jtl_import_vorschau'])) {
    // Zurück von der Kontrollliste (z.B. Validierungsfehler beim Commit) -> Vorschau erneut zeigen
    $vorschau = [
        'vaeter'              => $_SESSION['jtl_import_vorschau'],
        'einheiten_unbekannt' => $_SESSION['jtl_import_einheiten_unbekannt'] ?? [],
    ];
}

if (isset($_GET['neu'])) {
    unset($_SESSION['jtl_import_vorschau'], $_SESSION['jtl_import_einheiten_unbekannt'],
          $_SESSION['jtl_import_kategorie_id'], $_SESSION['jtl_import_artikeltyp_code'], $_SESSION['jtl_import_artikel_gruppe_id']);
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
        mit ihren Kind-Varianten inkl. erkannter Achsen an. Kategorie, Artikeltyp und Artikelgruppe gelten
        für den kompletten Import-Durchlauf, da sie in den CSV-Dateien nicht enthalten sind.
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
                <label class="erp-label">Variationskombinationen-CSV *</label>
                <input type="file" name="csv_kombinationen" accept=".csv,text/csv" required
                    style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%">
            </div>

            <div>
                <label class="erp-label">Kategorie *</label>
                <select name="kategorie_id" class="erp-select" style="width:100%" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($alleKategorien as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikeltyp *</label>
                <select name="artikeltyp_code" class="erp-select" style="width:100%" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($alleArtikeltypen as $t): ?>
                        <option value="<?= htmlspecialchars($t['code']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="erp-label">Artikelgruppe (Kontenzuordnung) *</label>
                <select name="artikel_gruppe_id" class="erp-select" style="width:100%" required>
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
$einheitenUnbekannt = $vorschau['einheiten_unbekannt'];
$vaeter             = $vorschau['vaeter'];
$anzahlKinder       = array_sum(array_map(fn($v) => count($v['kinder']), $vaeter));
?>

<div class="card">
    <h2 style="margin:0 0 8px">Kontrollliste — <?= count($vaeter) ?> Väter, <?= $anzahlKinder ?> Kinder</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Artikelnummern und Namen können hier noch korrigiert werden. Es wird noch nichts in die Datenbank geschrieben —
        der eigentliche Import startet erst mit Klick auf "Import starten" unten.
        <a href="jtl_import.php?neu=1">Abbrechen &amp; neu beginnen</a>
    </p>

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
                                <?php if ($k['stammdaten_fehlen']): ?>
                                    <span style="background:#fef2f2;color:#dc2626;padding:2px 6px;border-radius:4px;font-size:11px">Stammdaten fehlen</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    <?php endforeach; ?>

        <div style="margin-top:16px">
            <button type="submit" class="btn btn-primary btn-sm">Import starten</button>
            <a href="jtl_import.php?neu=1" class="btn btn-secondary btn-sm">Abbrechen</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
