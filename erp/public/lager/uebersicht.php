<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$service = new LagerService();

$lagerinhalt = $service->getUebersicht();

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
            <th>Artikel</th>
            <th>Variante</th>
            <th>Name</th>
            <th>Farbe</th>
            <th>Lager</th>
            <th>Bestand</th>
            <th>Charge</th>
            <th>Status</th>
        </tr>
        <?php foreach ($lagerinhalt as $li): ?>
            <tr>
                <td><?= htmlspecialchars($li['artikelnummer'] ?? '-') ?></td>
                <td><?= htmlspecialchars($li['artikelnummer_variante'] ?? '-') ?></td>
                <td><?= htmlspecialchars($li['artikel_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($li['farbe']) ?></td>
                <td><?= htmlspecialchars($li['lager_name'] ?? '-') ?></td>
                <td><?= $li['bestand'] ?></td>
                <td><?= htmlspecialchars($li['charge'] ?? '-') ?></td>
                <td><?= htmlspecialchars($li['charge_status'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>

    </table>
</body>

</html>