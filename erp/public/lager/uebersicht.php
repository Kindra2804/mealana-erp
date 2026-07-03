<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service     = new LagerService();
$lagerinhalt = $service->getUebersicht();

$grouped = [];
foreach ($lagerinhalt as $row) {
    $id = $row['artikel_id'];
    if ($row['zeilentyp'] === 'vater' || $row['zeilentyp'] === 'standalone') {
        $grouped[$id]['kopf'] = $row;
    } else {
        $grouped[$id]['kinder'][] = $row;
    }
}

$pageTitle        = 'Lagerübersicht';
$activeModule     = 'lager';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lager/wareneingang.php" class="btn btn-primary btn-sm">+ Wareneingang</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<div class="card">
    <?php if (empty($grouped)): ?>
        <p style="color:var(--color-text-muted);padding:24px 0">Noch kein Lagerbestand erfasst.</p>
    <?php else: ?>
    <table class="erp-table">
        <thead>
            <tr>
                <th>ARTIKELNUMMER</th>
                <th>NAME / VARIANTE</th>
                <th>LAGER</th>
                <th style="text-align:right">BESTAND</th>
                <th>CHARGE</th>
                <th>CHARGE-STATUS</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($grouped as $gruppe):
            $kopf   = $gruppe['kopf'];
            $kinder = $gruppe['kinder'] ?? []; ?>

            <tr style="background:var(--color-bg-subtle)">
                <td><strong><?= htmlspecialchars($kopf['vater_artikelnummer']) ?></strong></td>
                <td><strong><?= htmlspecialchars($kopf['artikel_name']) ?></strong></td>
                <td><?= $kopf['zeilentyp'] === 'standalone' ? htmlspecialchars($kopf['lager_name']) : '' ?></td>
                <td style="text-align:right"><strong><?= $kopf['bestand'] ?></strong></td>
                <td><?= $kopf['zeilentyp'] === 'standalone' ? htmlspecialchars($kopf['charge'] ?? '–') : '' ?></td>
                <td><?= $kopf['zeilentyp'] === 'standalone' && $kopf['charge'] ? htmlspecialchars($kopf['charge_status'] ?? '') : '' ?></td>
            </tr>

            <?php foreach ($kinder as $kind): ?>
                <tr>
                    <td style="padding-left:28px;color:var(--color-text-muted)"><?= htmlspecialchars($kind['varianten_artikelnummer']) ?></td>
                    <td style="padding-left:28px"><?= htmlspecialchars($kind['farbe'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($kind['lager_name']) ?></td>
                    <td style="text-align:right"><?= $kind['bestand'] ?></td>
                    <td><?= htmlspecialchars($kind['charge'] ?? '–') ?></td>
                    <td><?= htmlspecialchars($kind['charge_status'] ?? '–') ?></td>
                </tr>
            <?php endforeach; ?>

        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
