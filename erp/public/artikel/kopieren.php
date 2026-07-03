<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new ArtikelService();

function checkedDefault(string $field, array $formdata): string
{
    if (empty($formdata)) return 'checked';  // Erstaufruf: immer angehakt
    return isset($formdata[$field]) ? 'checked' : '';  // Nach Fehler: aus POST
}

$artikel = $service->getDetailArtikel($id);

if ($artikel === false) {
    echo 'Artikel nicht gefunden!';
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Artikel kopieren – MeaLana ERP</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>

    <h1>Artikel: <?= htmlspecialchars($artikel['name']) ?> (Art.Nr.: <?= htmlspecialchars($artikel['artikelnummer']) ?>) kopieren</h1>

    <?php if ($erfolg): ?>
        <div class="erfolg-box"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <strong>Bitte korrigiere folgende Fehler:</strong>
            <ul>
                <?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="kopieren_speichern.php" method="POST">
        <input type="hidden" name="quell_id" value="<?= $id ?>">

        <div class="gruppe">
            <h2>Kopiereinstellungen</h2>

            <label>neuer Artikelname <span class="pflicht">*</span></label>
            <input type="text" name="name"
                value="<?= htmlspecialchars($artikel['name']) ?>-KOPIE" required>

            <label>neue Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer"
                value="<?= htmlspecialchars($artikel['artikelnummer']) ?>-KOPIE" required>

            <label>Preise/Sonderpreise</label>
            <input type="checkbox" name="preise" value="1" <?= checkedDefault('preise', $formdata) ?>>

            <label>Kategorien</label>
            <input type="checkbox" name="kategorien" value="1" <?= checkedDefault('kategorien', $formdata) ?>>

            <label>Merkmale</label>
            <input type="checkbox" name="merkmale" value="1" <?= checkedDefault('merkmale', $formdata) ?>>

            <label>Lieferanten</label>
            <input type="checkbox" name="lieferanten" value="1" <?= checkedDefault('lieferanten', $formdata) ?>>

            <label>Überverkauf</label>
            <input type="checkbox" name="ueberverkauf" value="1" <?= checkedDefault('ueberverkauf', $formdata) ?>>

            <button type="submit">Artikel kopieren</button>
        </div>

    </form>
</body>

</html>