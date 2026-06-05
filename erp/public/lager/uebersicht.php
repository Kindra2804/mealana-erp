<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service = new LagerService();

$lagerinhalt = $service->getUebersicht();

$grouped = [];
foreach ($lagerinhalt as $row) {
    $id = $row['artikel_id'];
    if ($row['zeilentyp'] === 'vater' || $row['zeilentyp'] === 'standalone') {
        $grouped[$id]['kopf'] = $row;
    } else {
        // 'kind' UND 'standalone_kind' → beides sind Kindzeilen
        $grouped[$id]['kinder'][] = $row;
    }
}


// echo '<pre>';
// var_dump($lagerinhalt);
// echo '</pre>';
// exit;


?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Lagerliste – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Lagerinhalt</h1>
    <table>
        <tr>
            <th>Artikelnummer</th>
            <th>Name / Variante</th>
            <th>Lager</th>
            <th>Bestand</th>
            <th>Charge</th>
            <th>Status</th>
        </tr>
        <?php foreach ($grouped as $gruppe):
            $kopf = $gruppe['kopf'];
            $kinder = $gruppe['kinder'] ?? []; ?>

            <!-- 1. Kopfzeile (fett, kein Lager/Charge bei Vater) -->
            <tr style="background:#f0f0f0">
                <td><strong><?= htmlspecialchars($kopf['vater_artikelnummer']) ?></strong></td>
                <td><strong><?= htmlspecialchars($kopf['artikel_name']) ?></strong></td>
                <td><?= $kopf['zeilentyp'] === 'standalone' ? htmlspecialchars($kopf['lager_name']) : '' ?></td>
                <td><strong><?= $kopf['bestand'] ?></strong></td>
                <td><?= $kopf['zeilentyp'] === 'standalone' ? htmlspecialchars($kopf['charge'] ?? '-') : '' ?></td>
                <td><?= $kopf['charge'] ? htmlspecialchars($kopf['charge_status'] ?? '') : '-' ?></td>
            </tr>

            <!-- 2. Kindzeilen (eingerückt) -->
            <?php foreach ($kinder as $kind): ?>
                <tr>
                    <td style="padding-left:20px"><?= htmlspecialchars($kind['varianten_artikelnummer']) ?></td>
                    <td style="padding-left:20px"><?= htmlspecialchars($kind['farbe'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($kind['lager_name']) ?></td>
                    <td><?= $kind['bestand'] ?></td>
                    <td><?= htmlspecialchars($kind['charge'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($kind['charge_status'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>

        <?php endforeach; ?>

    </table>
</body>

</html>