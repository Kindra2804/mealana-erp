<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);
$controller = new ArtikelController();
$artikel = $controller->detail($id);
$artikelService = new ArtikelService();
$kategorien = $artikelService->getKategorienFuerArtikel($id);

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
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1><?= htmlspecialchars($artikel['name']) ?></h1>
    <p>Artikelnummer: <?= htmlspecialchars($artikel['artikelnummer']) ?></p>
    <p>Typ: <?= htmlspecialchars($artikel['artikeltyp']) ?></p>
    <p>Hersteller: <?= htmlspecialchars($artikel['hersteller']) ?></p>
    <p>Steuersatz: <?= $artikel['steuersatz'] ?>%</p>

    <h2>Varianten</h2>
    <?php if (empty($artikel['varianten'])): ?>
        <p>Noch keine Varianten angelegt.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Artikelnummer</th>
                <th>Farbe</th>
                <th>GTIN</th>
                <th>Preis</th>
                <th>Bestand</th>
                <th>Aktiv</th>
                <th>Aktionen</th>
            </tr>
            <?php foreach ($artikel['varianten'] as $v): ?>
                <tr>
                    <td><?= htmlspecialchars($v['artikelnummer']) ?></td>
                    <td>
                        <?php if ($v['farbe_hex']): ?>
                            <span style="display:inline-block; width:16px; height:16px; 
                                     background:<?= htmlspecialchars($v['farbe_hex']) ?>; 
                                     border:1px solid #ccc; vertical-align:middle;">
                            </span>
                        <?php endif; ?>
                        <?= htmlspecialchars($v['farbe_name']) ?>
                    </td>
                    <td><?= htmlspecialchars($v['gtin'] ?? '–') ?></td>
                    <td><?= $v['brutto_vk'] ? number_format($v['brutto_vk'], 2, ',', '.') . ' €' : '–' ?></td>
                    <td>–</td>
                    <td><?= $v['aktiv'] ? '✅' : '❌' ?></td>
                    <td><a href="variante_bearbeiten.php?id=<?= $v['id'] ?>">✏️</a></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>Kategorien</h2>
    <?php if (empty($kategorien)): ?>
        <p>Keine Kategorien zugewiesen.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($kategorien as $k): ?>
                <li><?= htmlspecialchars($k['name']) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p>
        <a href="variante_neu.php?artikel_id=<?= $artikel['id'] ?>">+ Variante hinzufügen</a>
    </p>
    <p>
        <a href="liste.php">Liste</a>
        <a href="bearbeiten.php?id=<?= $artikel['id'] ?>">✏️ Bearbeiten</a>
        <a href="neu.php">Neuer Artikel</a>
    </p>

</body>

</html>