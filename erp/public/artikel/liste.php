<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';

$controller = new ArtikelController();

$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$artikel = $controller->index($zeigeInaktive);

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $artikel = $controller->search($q);
} else {
    $artikel = $controller->index($zeigeInaktive);
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Artikelliste – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Artikel</h1>
    <?php if ($zeigeInaktive): ?>
        <a href="liste.php">Nur aktive anzeigen</a>
    <?php else: ?>
        <a href="liste.php?inaktive=1">Auch deaktivierte anzeigen</a>
    <?php endif; ?>
    <a href="neu.php">+ Neuer Artikel</a>
    <form method="GET" action="liste.php">
        <input type="text" name="q"
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            placeholder="Artikel suchen...">
        <button type="submit">🔍</button>
    </form>
    <table>
        <tr>
            <th>Artikelnummer</th>
            <th>Name</th>
            <th>Typ</th>
            <th>Hersteller</th>
            <th>Bestand</th>
            <th>aktiv</th>
            <th>Aktion</th>
        </tr>
        <?php foreach ($artikel as $a): ?>
            <?php
            if (!$a['aktiv']) {
                $zeilenstil = 'background:#fff3cd; color:#999;';
            } elseif ($a['ist_auslaufartikel']) {
                $zeilenstil = 'background:#ffe0b2; color:#e65100;'; // #ff8201
            } else {
                $zeilenstil = '';
            }
            ?>
            <tr style="<?= $zeilenstil ?>">
                <td><a href="detail.php?id=<?= $a['id'] ?>"><?= htmlspecialchars($a['artikelnummer']) ?></a></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><?= htmlspecialchars($a['artikeltyp']) ?></td>
                <td><?= htmlspecialchars($a['hersteller']) ?></td>
                <td><?= $a['gesamtbestand'] ?></td>
                <td><?= $a['aktiv'] ? 'Ja' : 'Nein' ?></td>
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