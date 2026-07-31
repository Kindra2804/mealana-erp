<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/import/JtlVaterKindImportService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['jtl_import_vorschau'])) {
    header('Location: jtl_import.php');
    exit;
}

$vorschauVaeter  = $_SESSION['jtl_import_vorschau'];
$kategorieId     = (int) ($_SESSION['jtl_import_kategorie_id'] ?? 0);
$artikeltypCode  = (string) ($_SESSION['jtl_import_artikeltyp_code'] ?? '');
$artikelGruppeId = (int) ($_SESSION['jtl_import_artikel_gruppe_id'] ?? 0);

// Korrekturen aus der Kontrollliste einsammeln, keyed nach der URSPRÜNGLICHEN Artikelnummer
// (die Formularfelder sind nach Listen-Index benannt, der 1:1 zur Session-Reihenfolge passt).
$korrekturen = [];
foreach ($vorschauVaeter as $vIdx => $vaterOrig) {
    $postVater = $_POST['vater'][$vIdx] ?? [];
    $eintrag = [
        'artikelnummer' => $postVater['artikelnummer'] ?? $vaterOrig['artikelnummer'],
        'name'          => $postVater['name'] ?? $vaterOrig['name'],
        'kinder'        => [],
    ];
    foreach ($vaterOrig['kinder'] as $kIdx => $kindOrig) {
        $postKind = $postVater['kinder'][$kIdx] ?? [];
        $eintrag['kinder'][$kindOrig['artikelnummer']] = [
            'artikelnummer' => $postKind['artikelnummer'] ?? $kindOrig['artikelnummer'],
            'name'          => $postKind['name'] ?? $kindOrig['name'],
        ];
    }
    $korrekturen[$vaterOrig['artikelnummer']] = $eintrag;
}

// Schlüssel wurden im Formular base64-kodiert übergeben (sonst würde ein leerer
// Verkaufseinheit-Wert als "einheit_mapping[]" ankommen und von PHP als numerischer
// Index statt als leerer String interpretiert werden).
$einheitMapping = [];
foreach ($_POST['einheit_mapping'] ?? [] as $encKey => $einheitId) {
    $einheitMapping[base64_decode($encKey)] = $einheitId;
}

$importService = new JtlVaterKindImportService();
$ergebnisse = $importService->fuehreImportDurch(
    $vorschauVaeter,
    $kategorieId,
    $artikeltypCode,
    $artikelGruppeId,
    $einheitMapping,
    $korrekturen
);

unset(
    $_SESSION['jtl_import_vorschau'], $_SESSION['jtl_import_einheiten_unbekannt'],
    $_SESSION['jtl_import_kategorie_id'], $_SESSION['jtl_import_artikeltyp_code'],
    $_SESSION['jtl_import_artikel_gruppe_id']
);

$vaterErfolgAnzahl = count(array_filter($ergebnisse, fn($e) => $e['erfolg']));
$vaterFehlerAnzahl = count($ergebnisse) - $vaterErfolgAnzahl;
$kinderErfolgAnzahl = 0;
foreach ($ergebnisse as $e) {
    if (!empty($e['kinder'])) {
        $kinderErfolgAnzahl += count(array_filter($e['kinder'], fn($k) => $k['erfolg']));
    }
}

$pageTitle    = 'JTL Vater+Kind-Import — Ergebnis';
$activeModule = 'artikel';
$actionBarContent = <<<HTML
<a href="liste.php" class="btn btn-secondary btn-sm">← Zurück zur Liste</a>
<a href="jtl_import.php" class="btn btn-secondary btn-sm">Neuer Import</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <div style="margin-bottom:16px;padding:12px 16px;border-radius:6px;
        background:<?= $vaterFehlerAnzahl === 0 ? '#f0fdf4' : ($vaterErfolgAnzahl === 0 ? '#fef2f2' : '#fffbeb') ?>;
        border:1px solid <?= $vaterFehlerAnzahl === 0 ? '#86efac' : ($vaterErfolgAnzahl === 0 ? '#fca5a5' : '#fcd34d') ?>">
        <strong><?= $vaterErfolgAnzahl ?> Väter importiert (<?= $kinderErfolgAnzahl ?> Kinder)</strong>
        <?php if ($vaterFehlerAnzahl > 0): ?>
            · <span style="color:#dc2626"><?= $vaterFehlerAnzahl ?> Väter mit Fehler</span>
        <?php endif; ?>
    </div>

    <table class="erp-table">
        <thead>
            <tr>
                <th style="width:160px">Artikelnummer</th>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ergebnisse as $e): ?>
            <tr>
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
                        <span style="color:#16a34a">✓ Vater importiert</span>
                    <?php else: ?>
                        <span style="color:#dc2626">✗ <?= htmlspecialchars(implode('; ', $e['fehler'])) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
                <?php foreach ($e['kinder'] ?? [] as $k): ?>
                <tr>
                    <td style="padding-left:24px;color:var(--color-text-muted)">
                        <?php if (!empty($k['erfolg']) && !empty($k['id'])): ?>
                            <a href="detail.php?id=<?= $k['id'] ?>"><?= htmlspecialchars($k['artikelnummer']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($k['artikelnummer'] ?: '–') ?>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($k['name'] ?: '–') ?></td>
                    <td>
                        <?php if (!empty($k['erfolg'])): ?>
                            <span style="color:#16a34a">✓ Kind importiert</span>
                        <?php else: ?>
                            <span style="color:#dc2626">✗ <?= htmlspecialchars(implode('; ', $k['fehler'] ?? [])) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
