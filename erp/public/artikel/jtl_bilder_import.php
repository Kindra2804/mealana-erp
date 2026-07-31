<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/JtlBilderImportService.php';

$vorschau  = null;
$ordnerPfad = $_SESSION['jtl_bilder_ordner'] ?? 'D:\ERP\mealana\import\Bilder';
$fehler    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ordner_pfad'])) {
    $ordnerPfad = trim($_POST['ordner_pfad']);

    if (!is_dir($ordnerPfad)) {
        $fehler[] = 'Ordner nicht gefunden: ' . $ordnerPfad;
    } else {
        $csvDateien = glob(rtrim($ordnerPfad, '\\/') . DIRECTORY_SEPARATOR . '*.csv');
        if (empty($csvDateien)) {
            $fehler[] = 'Keine CSV-Datei in diesem Ordner gefunden.';
        } else {
            $service = new JtlBilderImportService();
            $rows     = $service->parseBilderCsv($csvDateien[0]);
            $zeilen   = $service->baueVorschau($rows, $ordnerPfad);

            if (empty($zeilen)) {
                $fehler[] = 'Keine Bilder-Zuordnungen in der CSV erkannt.';
            } else {
                $_SESSION['jtl_bilder_vorschau'] = $zeilen;
                $_SESSION['jtl_bilder_ordner']   = $ordnerPfad;
                $vorschau = $zeilen;
            }
        }
    }
} elseif (!empty($_SESSION['jtl_bilder_vorschau'])) {
    $vorschau = $_SESSION['jtl_bilder_vorschau'];
}

if (isset($_GET['neu'])) {
    unset($_SESSION['jtl_bilder_vorschau'], $_SESSION['jtl_bilder_ordner']);
    header('Location: jtl_bilder_import.php');
    exit;
}

$pageTitle    = 'JTL Bilder-Import';
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
    <h2 style="margin:0 0 16px">JTL Bilder-Import</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        Ordnet Bilder aus einem JTL-Wawi-Bildexport (Ordner mit CSV + Bilddateien) bereits vorhandenen
        Artikeln per Artikelnummer zu. Artikel, die bereits Bilder haben, werden übersprungen.
        Da der Ordner lokal auf diesem Server liegt, ist kein Datei-Upload über den Browser nötig.
    </p>

    <form method="POST" style="max-width:600px">
        <label class="erp-label">Ordnerpfad auf dem Server</label>
        <input type="text" name="ordner_pfad" value="<?= htmlspecialchars($ordnerPfad) ?>" required
            style="border:1px solid var(--color-border);padding:6px 10px;border-radius:4px;width:100%;margin-bottom:12px">
        <button type="submit" class="btn btn-primary btn-sm">Ordner einlesen &amp; Vorschau zeigen</button>
    </form>
</div>

<?php else: ?>

<?php
$anzahlOk           = count(array_filter($vorschau, fn($z) => $z['status'] === 'ok'));
$anzahlNichtGefunden = count(array_filter($vorschau, fn($z) => $z['status'] === 'artikel_nicht_gefunden'));
$anzahlBereitsBilder = count(array_filter($vorschau, fn($z) => $z['status'] === 'bereits_bilder'));
$anzahlBilderProblem = 0;
foreach ($vorschau as $z) {
    foreach ($z['bilder'] as $b) {
        if ($b['status'] !== 'ok') $anzahlBilderProblem++;
    }
}

$statusLabel = [
    'ok'                      => ['✓ bereit', '#16a34a'],
    'artikel_nicht_gefunden'  => ['Artikel nicht gefunden', '#dc2626'],
    'bereits_bilder'          => ['bereits Bilder — übersprungen', '#d97706'],
];
$bildStatusLabel = [
    'ok'                        => '',
    'datei_fehlt'               => ' (Datei fehlt)',
    'format_nicht_unterstuetzt' => ' (Format nicht unterstützt)',
];
?>

<div class="card">
    <h2 style="margin:0 0 8px">Kontrollliste — <?= count($vorschau) ?> Artikel</h2>
    <p style="color:var(--color-text-muted);margin:0 0 16px">
        <strong><?= $anzahlOk ?></strong> bereit ·
        <strong><?= $anzahlNichtGefunden ?></strong> Artikel nicht gefunden ·
        <strong><?= $anzahlBereitsBilder ?></strong> bereits mit Bildern (übersprungen) ·
        <strong><?= $anzahlBilderProblem ?></strong> Bilder mit Problem (fehlend/Format)
        <br><a href="jtl_bilder_import.php?neu=1">Abbrechen &amp; neu beginnen</a>
    </p>

    <form method="POST" action="jtl_bilder_import_commit.php">
        <table class="erp-table">
            <thead>
                <tr>
                    <th style="width:140px">Artikelnummer</th>
                    <th>Artikelname</th>
                    <th style="width:80px">Bilder</th>
                    <th style="width:220px">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vorschau as $z): [$label, $farbe] = $statusLabel[$z['status']]; ?>
                <tr>
                    <td><?= htmlspecialchars($z['artikelnummer']) ?></td>
                    <td><?= htmlspecialchars($z['artikelname']) ?></td>
                    <td>
                        <?php foreach ($z['bilder'] as $b): ?>
                            <div style="font-size:11px;color:<?= $b['status'] === 'ok' ? 'var(--color-text-muted)' : '#dc2626' ?>">
                                <?= htmlspecialchars($b['dateiname']) ?><?= $bildStatusLabel[$b['status']] ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td><span style="color:<?= $farbe ?>"><?= $label ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:16px">
            <button type="submit" class="btn btn-primary btn-sm">Import starten (<?= $anzahlOk ?> Artikel)</button>
            <a href="jtl_bilder_import.php?neu=1" class="btn btn-secondary btn-sm">Abbrechen</a>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
