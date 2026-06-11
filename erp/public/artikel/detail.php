<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);

$service = new ArtikelService();

$zeigeInaktive = isset($_GET['inaktive']) && $_GET['inaktive'] == '1';
$kinder        = $service->getKinderFuerArtikel($id, $zeigeInaktive);
$artikel       = $service->getDetailArtikel($id);
$kategorien    = $service->getKategorienFuerArtikel($id);
$codes         = $service->getCodesByArtikelId($id);
$lieferanten   = $service->getLieferantenFuerArtikel($id);

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

    <h1><?= htmlspecialchars($artikel['name']) ?> <?= ($artikel['aktiv'] != 1) ? '(deaktiviert !)' : ''; ?></h1>

    <?php if ($artikel['aktiv'] != 1): ?>
        <div style="background: #fff3cd; color: #856404; border: 1px solid #ffc107; padding: 10px; margin-bottom: 10px">
            (dieser Artikel ist deaktiviert !)
        </div>
    <?php endif; ?>

    <?php if ($artikel['ueberverkauf_erlaubt'] == 1): ?>
        <div style="background: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; padding: 10px; margin-bottom: 10px">
            Achtung: dieser Artikel hat Überverkauf aktiviert !
        </div>
    <?php endif; ?>


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
        <p>Hersteller: <?= htmlspecialchars($artikel['hersteller'] ?? '–') ?></p>
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
            <a href="detail.php?id=<?= $id ?>">Nur aktive anzeigen</a>
        <?php else: ?>
            <a href="detail.php?id=<?= $id ?>&inaktive=1">Auch deaktivierte anzeigen</a>
        <?php endif; ?>

        <?php if (empty($kinder)): ?>
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
                <?php foreach ($kinder as $k): ?>
                    <?php
                    if (!$k['aktiv']) {
                        $zeilenstil = 'background:#fff3cd; color:#999;';
                    } elseif ($k['ist_auslaufartikel']) {
                        $zeilenstil = 'background:#ffe0b2; color:#e65100;';
                    } else {
                        $zeilenstil = '';
                    }
                    ?>
                    <tr style="<?= $zeilenstil ?>">
                        <td><?= htmlspecialchars($k['artikelnummer']) ?></td>
                        <td><?= htmlspecialchars($k['name'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($k['gtin'] ?? '–') ?></td>
                        <td><?= $k['brutto_vk'] ? number_format($k['brutto_vk'], 2, ',', '.') . ' €' : '–' ?></td>
                        <td><?= $k['gesamtbestand'] ?></td>
                        <td><?= $k['ist_auslaufartikel'] ? '✅' : '' ?></td>
                        <td><?= $k['aktiv'] ? '✅' : '❌' ?></td>
                        <td><a href="variante_bearbeiten.php?id=<?= $k['id'] ?>">✏️</a></td>
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
        <?php if (empty($lieferanten)): ?>
            <p>Keine Lieferanten zugewiesen.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Lieferant</th>
                    <th>Lief.-Artnr.</th>
                    <th>Netto-EK</th>
                    <th>Währung</th>
                    <th>VPE</th>
                    <th>Lieferzeit</th>
                    <th>Mindestabnahme</th>
                    <th>Standard</th>
                </tr>
                <?php foreach ($lieferanten as $l): ?>
                    <tr>
                        <td><?= htmlspecialchars($l['lieferant_name']) ?></td>
                        <td><?= htmlspecialchars($l['artikelnummer_lieferant'] ?? '–') ?></td>
                        <td><?= $l['netto_ek'] ? number_format($l['netto_ek'], 2, ',', '.') : '–' ?></td>
                        <td><?= htmlspecialchars($l['waehrung'] ?? '–') ?></td>
                        <td><?= $l['vpe_menge'] ?? '–' ?></td>
                        <td><?= $l['lieferzeit_tage'] ? $l['lieferzeit_tage'] . ' Tage' : '–' ?></td>
                        <td><?= $l['mindestabnahme'] ?? '–' ?></td>
                        <td><?= $l['standard_lieferant'] ? '⭐' : '' ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

    <p>
        <a href="liste.php">Liste</a>
        <a href="bearbeiten.php?id=<?= $artikel['id'] ?>">✏️ Bearbeiten</a>
        <a href="kopieren.php?id=<?= $artikel['id'] ?>">copy</a>
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