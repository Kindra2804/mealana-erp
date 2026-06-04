<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$service = new LieferantenService();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$lieferant = $service->findById($id);

if ($lieferant === false) {
    echo 'Lieferant nicht gefunden.';
    exit;
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($lieferant['name']) ?> – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1><?= htmlspecialchars($lieferant['name']) ?></h1>
    <p><strong>Lieferantennr.:</strong> <?= htmlspecialchars($lieferant['id']) ?></p>
    <p><strong>Land:</strong> <?= htmlspecialchars($lieferant['land'] ?? '') ?></p>
    <p><strong>Website:</strong> <?= htmlspecialchars($lieferant['website'] ?? '') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($lieferant['email'] ?? '') ?></p>
    <p><strong>Telefon:</strong> <?= htmlspecialchars($lieferant['telefon'] ?? '') ?></p>
    <p><strong>Aktiv:</strong> <?= $lieferant['aktiv'] ? 'Ja' : 'Nein' ?></p>
    <a href="liste.php">← Zurück zur Liste</a>

    <h2>Vertreter</h2>
    <?php if (empty($lieferant['vertreter'])): ?>
        <p>Noch keine Vertreter angelegt.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Telefon</th>
            </tr>
            <?php foreach ($lieferant['vertreter'] as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['vorname'] . ' ' . $v['nachname']) ?></td>
                    <td><?= htmlspecialchars($v['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($v['telefon'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>

</html>