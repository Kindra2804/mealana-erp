<?php
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';

$controller = new ArtikelController();
$artikel = $controller->index();
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Artikelliste – MeaLana ERP</title>
</head>

<body>
    <h1>Artikel</h1>
    <a href="neu.php">+ Neuer Artikel</a>
    <table>
        <tr>
            <th>Artikelnummer</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Hersteller</th>
            <th>bestand</th>
            <th>aktiv</th>
            <th>Aktion</th>
        </tr>
        <?php foreach ($artikel as $a): ?>
            <tr>
                <td><a href="detail.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['artikelnummer']) ?></a></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['artikeltyp']) ?></td>
                <td><?= htmlspecialchars($a['hersteller']) ?></td>
                <td>-</td>
                <td><?= $a['aktiv'] ? 'Ja' : 'Nein' ?></td>
                <td><a href="bearbeiten.php?id=<?= $a['id'] ?>">✏️</a></td>
                <td>
                    <a href="bearbeiten.php?id=<?= $a['id'] ?>">✏️</a>
                    <a href="delete.php?id=<?= $a['id'] ?>"
                        onclick="return confirm('Artikel wirklich deaktivieren?')">🗑️</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>

</html>