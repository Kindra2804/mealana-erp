<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$service = new LieferantenService();

$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $lieferanten = $service->search($q);
} else {
    $lieferanten = $service->findAll($zeigeInaktive);
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Lieferantenliste – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Lieferanten</h1>
    <?php if ($zeigeInaktive): ?>
        <a href="liste.php">Nur aktive anzeigen</a>
    <?php else: ?>
        <a href="liste.php?inaktive=1">Auch deaktivierte anzeigen</a>
    <?php endif; ?>
    <a href="neu.php">+ Neuer Lieferant</a>
    <form method="GET" action="liste.php">
        <input type="text" name="q"
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            placeholder="Lieferanten suchen...">
        <button type="submit">🔍</button>
    </form>
    <table>
        <tr>
            <th>Lieferantennr.</th>
            <th>Name</th>
            <th>Land</th>
            <th>Website</th>
            <th>Email</th>
            <th>Telefon</th>
            <th>aktiv</th>
            <th>Aktion</th>
        </tr>
        <?php foreach ($lieferanten as $l): ?>
            <tr style="<?= $l['aktiv'] ? '' : 'background:#fff3cd; color:#999;' ?>">
                <td><a href="detail.php?id=<?= $l['id'] ?>"><?= htmlspecialchars($l['id']) ?></a></td>
                <td><a href="detail.php?id=<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></a></td>
                <td><?= htmlspecialchars($l['land'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['website'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($l['telefon'] ?? '') ?></td>
                <td><?= $l['aktiv'] ? 'Ja' : 'Nein' ?></td>
                <td>
                    <a href="bearbeiten.php?id=<?= $l['id'] ?>">✏️</a>
                    <a href="delete.php?id=<?= $l['id'] ?>"
                        onclick="return confirm('Lieferant wirklich deaktivieren?')">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>

</html>