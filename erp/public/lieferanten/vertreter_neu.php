<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$erfolg   = $_SESSION['erfolg']   ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

// ID aus URL holen
$vertreter_id = (int) ($_GET['vertreter_id'] ?? 0);
if ($vertreter_id <= 0) {
    header('Location: liste.php');
    exit;
}

$service = new LieferantenService();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ergebnis = $service->saveVertreter($_POST);
    if ($ergebnis['erfolg']) {
        $_SESSION['erfolg'] = 'Vertreter erfolgreich angelegt!';
        header('Location: detail.php?id=' . $vertreter_id);
        exit;
    } else {
        $fehler   = $ergebnis['fehler'];
        $formdata = $_POST;
    }
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Vertreter – MeaLana ERP</title>
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
    <?php if ($erfolg): ?>
        <div class="erfolg-box">
            <?= htmlspecialchars($erfolg) ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="vertreter_neu.php">
        <input type="hidden" name="vertreter_id" value="<?= $vertreter_id ?>">
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
            <textarea name="notizen" rows="4">
                <?= htmlspecialchars($formdata['notizen'] ?? '') ?></textarea>
            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Ja</option>
                <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Nein</option>
            </select>
        </div>

        <button type="submit">Vertreter anlegen</button>