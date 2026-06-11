<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

$service = new AchsenService();
$achsen = $service->findAll();

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Achsenliste – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Achsen</h1>
    <a href="neu.php">+ Neue Achse</a>
    <table>
        <tr>
            <th>Name</th>
            <th>Code</th>
            <th>Darstellungsform</th>
            <th>Reihenfolge</th>
            <th>Aktionen</th>
        </tr>
        <?php foreach ($achsen as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['code']) ?></td>
                <td><?= htmlspecialchars($a['darstellungsform']) ?></td>
                <td><?= htmlspecialchars($a['sort_order']) ?></td>
                <td>
                    <a href="bearbeiten.php?id=<?= $a['id'] ?>">✏️</a>

                    <form action="loeschen.php" method="POST"
                        onsubmit="return confirm('Achse wirklich löschen?')">
                        <input type="hidden" name="id" value="<?= $a['id'] ?>">
                        <button type="submit">🗑️</button>
                    </form>

                </td>
            </tr>

        <?php endforeach; ?>
    </table>
</body>

</html>