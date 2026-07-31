<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/JtlBilderImportService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['jtl_bilder_vorschau'])) {
    header('Location: jtl_bilder_import.php');
    exit;
}

$vorschau   = $_SESSION['jtl_bilder_vorschau'];
$ordnerPfad = $_SESSION['jtl_bilder_ordner'] ?? '';

$service    = new JtlBilderImportService();
$ergebnisse = $service->fuehreImportDurch($vorschau, $ordnerPfad);

unset($_SESSION['jtl_bilder_vorschau'], $_SESSION['jtl_bilder_ordner']);

$erfolgAnzahl = count(array_filter($ergebnisse, fn($e) => $e['erfolg']));
$bilderGesamt = 0;
foreach ($ergebnisse as $e) {
    $bilderGesamt += count(array_filter($e['bilder'], fn($b) => $b['erfolg']));
}

$pageTitle    = 'JTL Bilder-Import — Ergebnis';
$activeModule = 'artikel';
$actionBarContent = <<<HTML
<a href="liste.php" class="btn btn-secondary btn-sm">← Zurück zur Liste</a>
<a href="jtl_bilder_import.php" class="btn btn-secondary btn-sm">Neuer Import</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <div style="margin-bottom:16px;padding:12px 16px;border-radius:6px;
        background:<?= $erfolgAnzahl > 0 ? '#f0fdf4' : '#fef2f2' ?>;
        border:1px solid <?= $erfolgAnzahl > 0 ? '#86efac' : '#fca5a5' ?>">
        <strong><?= $erfolgAnzahl ?> Artikel mit neuen Bildern (<?= $bilderGesamt ?> Bilder importiert)</strong>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:140px">Artikelnummer</th>
                <th>Artikelname</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ergebnisse as $e): ?>
            <tr>
                <td>
                    <?php if ($e['erfolg']): ?>
                        <?= htmlspecialchars($e['artikelnummer']) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($e['artikelnummer']) ?>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($e['artikelname']) ?></td>
                <td>
                    <?php if ($e['erfolg']): ?>
                        <span style="color:#16a34a">✓ <?= count(array_filter($e['bilder'], fn($b) => $b['erfolg'])) ?> Bild(er) importiert</span>
                    <?php else: ?>
                        <span style="color:#dc2626">✗ <?= htmlspecialchars(implode('; ', $e['fehler'])) ?></span>
                    <?php endif; ?>
                    <?php foreach ($e['bilder'] as $b): if ($b['erfolg']) continue; ?>
                        <div style="font-size:11px;color:#dc2626"><?= htmlspecialchars($b['dateiname']) ?>: <?= htmlspecialchars($b['fehler']) ?></div>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
