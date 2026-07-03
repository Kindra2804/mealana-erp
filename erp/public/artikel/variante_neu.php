<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$vaterartikel_id = (int) ($_GET['artikel_id'] ?? 0);
if ($vaterartikel_id <= 0) {
    header('Location: liste.php');
    exit;
}

$service = new ArtikelService();
$vater   = $service->findById($vaterartikel_id);
if (!$vater) {
    header('Location: liste.php');
    exit;
}

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

function old(string $field, array $formdata, string $default = ''): string {
    return htmlspecialchars((string) ($formdata[$field] ?? $default));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Neue Variante – MeaLana ERP</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/app.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Neue Variante für: <?= htmlspecialchars($vater['name']) ?></h1>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul><?php foreach ($fehler as $f): ?>
                <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form action="variante_speichern.php" method="POST">
        <input type="hidden" name="vaterartikel_id" value="<?= $vaterartikel_id ?>">

        <div class="gruppe">
            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer" value="<?= old('artikelnummer', $formdata) ?>" required>

            <label>GTIN / EAN</label>
            <input type="text" name="gtin" value="<?= old('gtin', $formdata) ?>">

            <label>Brutto-VK</label>
            <input type="number" step="0.01" name="brutto_vk" value="<?= old('brutto_vk', $formdata) ?>">

            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1">Ja</option>
                <option value="0">Nein</option>
            </select>
        </div>

        <button type="submit">Variante speichern</button>
        <a href="detail.php?id=<?= $vaterartikel_id ?>">Abbrechen</a>
    </form>
</body>
</html>
