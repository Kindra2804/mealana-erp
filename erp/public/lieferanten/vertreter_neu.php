<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

// ID aus URL holen
$lieferant_id = (int) ($_GET['lieferant_id'] ?? 0);
if ($lieferant_id <= 0) {
    header('Location: liste.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Vertreter – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Neuer Vertreter</h1>
    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul>
                <?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="vertreter_speichern.php">
        <input type="hidden" name="lieferant_id" value="<?= $lieferant_id ?>">
        <div class="gruppe">
            <label for="vorname">Vorname</label>
            <input type="text" id="vorname" name="vorname"
                value="<?= htmlspecialchars($formdata['vorname'] ?? '') ?>">
            <label for="nachname">Nachname <span class="pflicht">*</span></label>
            <input type="text" id="nachname" name="nachname"
                value="<?= htmlspecialchars($formdata['nachname'] ?? '') ?>"
                required>
            <label for="telefon">Telefon</label>
            <input type="text" id="telefon" name="telefon"
                value="<?= htmlspecialchars($formdata['telefon'] ?? '') ?>">
            <label for="email">E-Mail</label>
            <input type="text" id="email" name="email"
                value="<?= htmlspecialchars($formdata['email'] ?? '') ?>">
            <label for="mobil">Mobil</label>
            <input type="tel" id="mobil" name="mobil"
                value="<?= htmlspecialchars($formdata['mobil'] ?? '') ?>">
            <label for="notizen">Notizen</label>
            <textarea name="notizen" rows="4"><?= htmlspecialchars($formdata['notizen'] ?? '') ?></textarea>
            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Ja</option>
                <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Nein</option>
            </select>
        </div>

        <button type="submit">Vertreter anlegen</button>