<?php
require_once __DIR__ . '/../includes/auth_check.php';

// Session Daten holen
$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neuer Lieferant – MeaLana ERP</title>
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
    <h1>Neuer Lieferant</h1>
    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul>
                <?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <form method="POST" action="speichern.php">
        <div class="gruppe">
            <label for="name">Name <span class="pflicht">*</span></label>
            <input type="text" id="name" name="name"
                value="<?= htmlspecialchars($formdata['name'] ?? '') ?>"
                required>
            <label for="land">Land</label>
            <input type="text" id="land" name="land"
                value="<?= htmlspecialchars($formdata['land'] ?? '') ?>">
            <label for="website">Website</label>
            <input type="text" id="website" name="website"
                value="<?= htmlspecialchars($formdata['website'] ?? '') ?>">
            <label for="email">E-Mail</label>
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

        <button type="submit">Lieferant anlegen</button>