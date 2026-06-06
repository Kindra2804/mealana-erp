<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$service = new LieferantenService();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$lieferant = $service->findByIdMitVertretern($id);

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
    <a href="bearbeiten.php?id=<?= $id  ?>">Bearbeiten</a>
    <a href="delete.php?id=<?= $id  ?>">Löschen</a>

    <h2>Vertreter</h2>
    <?php if (empty($lieferant['vertreter'])): ?>
        <p>Noch keine Vertreter angelegt.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Telefon</th>
                <th>Mobil</th>
                <th>Notizen</th>
                <th>Aktionen</th>
            </tr>
            <?php foreach ($lieferant['vertreter'] as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['vorname'] . ' ' . $v['nachname']) ?></td>
                    <td><?= htmlspecialchars($v['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars($v['telefon'] ?? '') ?></td>
                    <td><?= htmlspecialchars($v['mobil'] ?? '') ?></td>
                    <td title="<?= htmlspecialchars($v['notizen'] ?? '') ?>">
                        <?= htmlspecialchars(mb_strimwidth($v['notizen'] ?? '', 0, 40, '…')) ?>
                    </td>
                    <td>
                        <a href="vertreter_bearbeiten.php?id=<?= $v['id']  ?>">Bearbeiten</a>
                        <a href="vertreter_delete.php?lieferant_id=<?= $id ?>&id=<?= $v['id']  ?>">Löschen</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

    <?php endif; ?>
    <p>
        <a href="vertreter_neu.php?lieferant_id=<?= $id ?>">+ neuer Vertreter</a>
    </p>
</body>

</html>