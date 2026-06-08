<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$artikel_id = (int) ($_GET['artikel_id'] ?? 0);
if ($artikel_id <= 0) {
    header('Location: liste.php');
    exit;
}

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Artikel – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <form action="variante_speichern.php" method="POST">
        <input type="hidden" name="artikel_id" value="<?= $artikel_id ?>">
        <label>Artikelnummer *</label>
        <input name="artikelnummer" value="<?= htmlspecialchars($formdata['artikelnummer'] ?? '') ?>" required>
        <label>GTIN *</label>
        <input name="gtin" value="<?= htmlspecialchars($formdata['gtin'] ?? '') ?>">
        <label>Name *</label>
        <input name="farbe_name" value="<?= htmlspecialchars($formdata['farbe_name'] ?? '') ?>">
        <label>Farbe *</label>
        <input type="color" name="farbe_hex" value="<?= htmlspecialchars($formdata['farbe_hex'] ?? '') ?>">
        <label>Bild URL *</label>
        <input name="bild_url" value="<?= htmlspecialchars($formdata['bild_url'] ?? '') ?>">
        <label>Brutto VK *</label>
        <input type="number" step="0.01" name="brutto_vk" value="<?= htmlspecialchars($formdata['brutto_vk'] ?? '') ?>">
        <select name="aktiv">
            <option value="1">Ja</option>
            <option value="0">Nein</option>
        </select>
        <button type="submit">Variante speichern</button>
    </form>
</body>

</html>