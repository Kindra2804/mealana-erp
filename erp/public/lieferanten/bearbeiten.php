<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

// ID aus URL holen
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

// Service und Daten für Lieferanten laden
$service = new LieferantenService();

// Artikel aus DB laden – aber Session hat Vorrang bei Fehler!
if (empty($formdata)) {
    $lieferant = $service->findById($id);
    if ($lieferant === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $lieferant;  // ← Lieferantendaten als Formularwerte!
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Lieferant bearbeiten – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <div class="container">
        <h1>Lieferant bearbeiten</h1>

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

        <form action="aktualisieren.php" method="POST">

            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="gruppe">
                <label for="name">Name <span class="pflicht">*</span></label>
                <input type="text" id="name" name="name"
                    value="<?= htmlspecialchars($formdata['name'] ?? '') ?>">
                <label for="land">Land</label>
                <input type="text" id="land" name="land"
                    value="<?= htmlspecialchars($formdata['land'] ?? '') ?>">
                <label for="website">Website</label>
                <input type="text" id="website" name="website"
                    value="<?= htmlspecialchars($formdata['website'] ?? '') ?>">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                    value="<?= htmlspecialchars($formdata['email'] ?? '') ?>">
                <label for="telefon">Telefon</label>
                <input type="text" id="telefon" name="telefon"
                    value="<?= htmlspecialchars($formdata['telefon'] ?? '') ?>">
                <label>Aktiv</label>
                <select name="aktiv">
                    <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
            <button type="submit">Lieferant updaten</button>

        </form>
    </div>

</body>

</html>