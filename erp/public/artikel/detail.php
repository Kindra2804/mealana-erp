<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);

$service = new ArtikelService();

$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$varianten = $service->getVariantenFuerArtikel($id, $zeigeInaktive);

$artikel = $service->getDetailArtikel($id);
$kategorien = $service->getKategorienFuerArtikel($id);
$codes = $service->getCodesByArtikelId($id); // (für EAN);

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

    <link rel="stylesheet" href="/mealana/css/app.css">

</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>

    <h1><?= htmlspecialchars($artikel['name']) ?></h1>

    <div class="tabs">
        <button class="tab-btn aktiv" onclick="zeigeTab('stammdaten')">Stammdaten</button>
        <button class="tab-btn" onclick="zeigeTab('varianten')">Varianten</button>
        <button class="tab-btn" onclick="zeigeTab('lager')">Lager</button>
        <button class="tab-btn" onclick="zeigeTab('lieferanten')">Lieferanten</button>
    </div>

    <div id="tab-stammdaten" class="tab-inhalt">
        <h2>Stammdaten</h2>
        <p>Artikelnummer: <?= htmlspecialchars($artikel['artikelnummer']) ?></p>
        <p>Typ: <?= htmlspecialchars($artikel['artikeltyp']) ?></p>
        <p>Hersteller: <?= htmlspecialchars($artikel['hersteller']) ?></p>
        <p>Steuersatz: <?= $artikel['steuersatz'] ?>%</p>
        <p>Einheit: <?= htmlspecialchars($artikel['einheit_name'] ?? '–') ?></p>
        <p>EAN: <?= htmlspecialchars($codes[0]['code'] ?? '–') ?></p>

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


    </div>
    <div id="tab-varianten" class="tab-inhalt versteckt">
        <h2>Varianten</h2>
        <?php if ($zeigeInaktive): ?>
            <a href="detail.php?id=<?= $id  ?>">Nur aktive anzeigen</a>
        <?php else: ?>
            <a href="detail.php?id=<?= $id  ?>&inaktive=1">Auch deaktivierte anzeigen</a>
        <?php endif; ?>
        <?php if (empty($varianten)): ?>
            <p>Noch keine Varianten angelegt.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Artikelnummer</th>
                    <th>Farbe</th>
                    <th>GTIN</th>
                    <th>Preis</th>
                    <th>Bestand</th>
                    <th>Auslaufartikel</th>
                    <th>Aktiv</th>
                    <th>Aktionen</th>
                </tr>
                <?php foreach ($varianten as $v): ?>
                    <?php
                    if (!$v['aktiv']) {
                        $zeilenstil = 'background:#fff3cd; color:#999;';
                    } elseif ($v['ist_auslaufartikel']) {
                        $zeilenstil = 'background:#ffe0b2; color:#e65100;'; // #ff8201
                    } else {
                        $zeilenstil = '';
                    }
                    ?>
                    <tr style="<?= $zeilenstil ?>">
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
                        <td><?= $v['gesamtbestand'] ?></td>
                        <td><?= $v['ist_auslaufartikel'] ? '✅' : ''  ?></td>
                        <td><?= $v['aktiv'] ? '✅' : '❌' ?></td>
                        <td><a href="variante_bearbeiten.php?id=<?= $v['id'] ?>">✏️</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        <p>
            <a href="variante_neu.php?artikel_id=<?= $artikel['id'] ?>">+ Variante hinzufügen</a>
        </p>
    </div>
    <div id="tab-lager" class="tab-inhalt versteckt">
        <p>Lagerbestände werden hier angezeigt.</p>
    </div>
    <div id="tab-lieferanten" class="tab-inhalt versteckt">
        <p>Lieferanten werden hier angezeigt.</p>
    </div>

    <p>
        <a href="liste.php">Liste</a>
        <a href="bearbeiten.php?id=<?= $artikel['id'] ?>">✏️ Bearbeiten</a>
        <a href="neu.php">Neuer Artikel</a>
    </p>

    <script>
        function zeigeTab(name) {
            document.querySelectorAll('.tab-inhalt').forEach(d => d.classList.add('versteckt'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('aktiv'));
            document.getElementById('tab-' + name).classList.remove('versteckt');
            event.target.classList.add('aktiv');
        }
    </script>
</body>

</html>