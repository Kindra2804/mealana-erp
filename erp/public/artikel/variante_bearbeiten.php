<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelController.php';
require_once __DIR__ . '/../../src/core/Database.php';

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


// Variante aus DB laden – aber Session hat Vorrang bei Fehler!
if (empty($formdata)) {
    $controller = new ArtikelController();
    $variante = $controller->findVarianteFuerBearbeitung($id);
    if ($variante === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $variante;  // ← variantedaten als Formularwerte!

}

// Hilfsfunktion: war dieser Wert im letzten Submit?
function old(string $field, array $formdata, string $default = ''): string
{
    $value = $formdata[$field] ?? $default;
    return htmlspecialchars((string)($value ?? $default));
}

// Hilfsfunktion: war diese Option selected?
function selected(string $field, string $value, array $formdata): string
{
    return ((string)($formdata[$field] ?? '')) === $value ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Neue Variante – MeaLana ERP</title>
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
    <h1>Variante bearbeiten</h1>

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

    <form action="variante_aktualisieren.php" method="POST">

        <input type="hidden" name="artikel_id" value="<?= $formdata['artikel_id'] ?>">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="gruppe">
            <h2>Stammdaten</h2>

            <label>Artikelnummer <span class="pflicht">*</span></label>
            <input type="text" name="artikelnummer"
                value="<?= old('artikelnummer', $formdata) ?>" required>

            <label>GTIN <span class="pflicht">*</span></label>
            <input type="text" name="gtin"
                value="<?= old('gtin', $formdata) ?>">

            <label>Name <span class="pflicht">*</span></label>
            <input type="text" name="farbe_name"
                value="<?= old('farbe_name', $formdata) ?>">

            <label>Farbauswahl <span class="pflicht">*</span></label>
            <input type="color" name="farbe_hex"
                value="<?= old('farbe_hex', $formdata) ?>">

            <label>Bild <span class="pflicht">*</span></label>
            <input type="text" name="bild_url"
                value="<?= old('bild_url', $formdata) ?>">

            <label>Brutto-VK <span class="pflicht">*</span></label>
            <input type="number" step="0.01" name="brutto_vk"
                value="<?= old('brutto_vk', $formdata) ?>">

            <label>Aktiv</label>
            <select name="aktiv">
                <option value="1" <?= selected('aktiv', '1', $formdata) ?>>Ja</option>
                <option value="0" <?= selected('aktiv', '0', $formdata) ?>>Nein</option>
            </select>
        </div>

        <button type="submit">Variante updaten</button>

    </form>

</body>

</html>