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

// Service und Daten für Vertreter laden
$service = new LieferantenService();

// Artikel aus DB laden – aber Session hat Vorrang bei Fehler!
if (empty($formdata)) {
    $vertreter = $service->findVertreterById($id);
    if ($vertreter === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $vertreter;  // ← Vertreterdaten als Formularwerte!
    $lieferant_id = $vertreter['lieferant_id'];
}

?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Vertreter bearbeiten – MeaLana ERP</title>
    <style>
        body {
            font-family: sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .gruppe {
            margin-bottom: 20px;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
        }

        label {
            display: block;
            margin-bottom: 4px;
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 12px;
            box-sizing: border-box;
        }

        .pflicht {
            color: red;
        }

        .versteckt {
            display: none;
        }

        h2 {
            color: #333;
        }

        button {
            background: #4a7cb5;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }

        .fehler-box {
            background: #f8d7da;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .erfolg-box {
            background: #d4edda;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <div class="container">
        <h1>Vertreter bearbeiten</h1>

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

        <form action="vertreter_aktualisieren.php" method="POST">

            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="lieferant_id" value="<?= $formdata['lieferant_id'] ?? '' ?>">

            <div class="gruppe">
                <label for="vorname">Vorname</label>
                <input type="text" id="vorname" name="vorname"
                    value="<?= htmlspecialchars($formdata['vorname'] ?? '') ?>">
                <label for="nachname">Nachname <span class="pflicht">*</span></label>
                <input type="text" id="nachname" name="nachname"
                    value="<?= htmlspecialchars($formdata['nachname'] ?? '') ?>">
                <label for="telefon">Telefon</label>
                <input type="text" id="telefon" name="telefon"
                    value="<?= htmlspecialchars($formdata['telefon'] ?? '') ?>">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email"
                    value="<?= htmlspecialchars($formdata['email'] ?? '') ?>">
                <label for="mobil">mobil</label>
                <input type="tel" id="mobil" name="mobil"
                    value="<?= htmlspecialchars($formdata['mobil'] ?? '') ?>">
                <textarea name="notizen" rows="4">
                <?= htmlspecialchars($formdata['notizen'] ?? '') ?></textarea>
                <label>Aktiv</label>
                <select name="aktiv">
                    <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Ja</option>
                    <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Nein</option>
                </select>
            </div>
            <button type="submit">Vertreter updaten</button>

        </form>
    </div>

</body>

</html>