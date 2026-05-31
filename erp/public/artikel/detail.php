<?php
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';

$id = (int) ($_GET['id'] ?? 0);
$controller = new ArtikelController();
$artikel = $controller->detail($id);

if ($artikel === false) {
    echo 'Artikel nicht gefunden!';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($artikel['name']) ?> – MeaLana ERP</title>
</head>

<body>
    <h1><?= htmlspecialchars($artikel['name']) ?></h1>
    <p>Artikelnummer: <?= htmlspecialchars($artikel['artikelnummer']) ?></p>
    <p>Typ: <?= htmlspecialchars($artikel['artikeltyp']) ?></p>
    <p>Hersteller: <?= htmlspecialchars($artikel['hersteller']) ?></p>
    <p>Steuersatz: <?= $artikel['steuersatz'] ?>%</p>

    <h2>Varianten</h2>
    <?php if (empty($artikel['varianten'])): ?>
        <p>Noch keine Varianten angelegt.</p>
    <?php else: ?>
        <?php foreach ($artikel['varianten'] as $v): ?>
            <p><?= htmlspecialchars($v['farbe_name']) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="liste.php">Liste</a>
    <a href="bearbeiten.php?id=<?= $artikel['id'] ?>">✏️ Bearbeiten</a>
    <a href="neu.php">Neuer Artikel</a>
</body>

</html>