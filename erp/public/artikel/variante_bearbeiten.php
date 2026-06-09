<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new ArtikelService();

if (empty($formdata)) {
    $kind = $service->findById($id);
    if (!$kind) {
        header('Location: liste.php');
        exit;
    }
    // GTIN aus artikel_codes laden
    $codes = $service->getCodesByArtikelId($id);
    $kind['gtin'] = $codes[0]['code'] ?? '';
    $formdata = $kind;
}

function old(string $field, array $formdata, string $default = ''): string {
    return htmlspecialchars((string) ($formdata[$field] ?? $default));
}
function selected(string $field, string $value, array $formdata): string {
    return ((string) ($formdata[$field] ?? '')) === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Variante bearbeiten – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Variante bearbeiten</h1>

    <?php if ($erfolg): ?>
        <div class="erfolg-box"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul><?php foreach ($fehler as $f): ?>
                <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form action="variante_aktualisieren.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="vaterartikel_id" value="<?= (int) ($formdata['vaterartikel_id'] ?? 0) ?>">

        <div class="gruppe">
            <h2>Stammdaten</h2>

            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer" value="<?= old('artikelnummer', $formdata) ?>" required>

            <label>Name / Farbname <span class="pflicht">*</span></label>
            <input type="text" name="farbe_name" value="<?= old('farbe_name', $formdata) ?>">

            <label>Farbe</label>
            <input type="color" name="farbe_hex" value="<?= old('farbe_hex', $formdata, '#cccccc') ?>">

            <label>GTIN / EAN</label>
            <input type="text" name="gtin" value="<?= old('gtin', $formdata) ?>">

            <label>Brutto-VK</label>
            <input type="number" step="0.01" name="brutto_vk" value="<?= old('brutto_vk', $formdata) ?>">

            <label>Auslaufartikel</label>
            <input type="checkbox" name="ist_auslaufartikel" value="1"
                <?= ($formdata['ist_auslaufartikel'] ?? 0) == 1 ? 'checked' : '' ?>>

            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1" <?= selected('aktiv', '1', $formdata) ?>>Ja</option>
                <option value="0" <?= selected('aktiv', '0', $formdata) ?>>Nein</option>
            </select>
        </div>

        <button type="submit">Variante speichern</button>
        <a href="detail.php?id=<?= (int) ($formdata['vaterartikel_id'] ?? 0) ?>">Abbrechen</a>
    </form>
</body>
</html>
