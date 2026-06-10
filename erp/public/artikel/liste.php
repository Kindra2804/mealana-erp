<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/core/Database.php';

$controller = new ArtikelController();

$db = Database::getInstance();
$alleHersteller  = $db->query("SELECT id, name FROM hersteller WHERE aktiv = 1 ORDER BY name")->fetchAll();
$alleArtikeltypen = $db->query("SELECT id, name FROM artikel_typen ORDER BY name")->fetchAll();


$filter = [
    'q'             => trim($_GET['q'] ?? ''),
    'hersteller_id' => (int)($_GET['hersteller_id'] ?? 0) ?: null,
    'artikeltyp_id' => (int)($_GET['artikeltyp_id'] ?? 0) ?: null,
    'nurMitBestand' => isset($_GET['nurMitBestand']),
    'mitInaktiven'  => isset($_GET['inaktive']),
];
$artikel = $controller->index($filter);


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


    <a href="neu.php">+ Neuer Artikel</a>
    <form method="GET" action="liste.php">
        <input type="text" name="q"
            value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
            placeholder="Artikel suchen...">
        <select name="hersteller_id">
            <option value="">– Hersteller –</option>
            <?php foreach ($alleHersteller as $h): ?>
                <option value="<?= $h['id'] ?>" <?= ($_GET['hersteller_id'] ?? '') == $h['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($h['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="artikeltyp_id">
            <option value="">– Artikel-Typ –</option>
            <?php foreach ($alleArtikeltypen as $t): ?>
                <option value="<?= $t['id'] ?>" <?= ($_GET['artikeltyp_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label><input type="checkbox" name="nurMitBestand" <?= isset($_GET['nurMitBestand']) ? 'checked' : '' ?>> Nur mit Bestand</label>
        <label><input type="checkbox" name="inaktive" <?= isset($_GET['inaktive']) ? 'checked' : '' ?>> Auch inaktive</label>
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
                    <a href="kopieren.php?id=<?= $a['id'] ?>">copy</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>

</html>