<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';

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

$service = new AchsenService();

// Achse aus DB laden – aber Session hat Vorrang bei Fehler!
if (empty($formdata)) {
    $achse = $service->findById($id);

    if ($achse === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $achse;
}


// Hilfsfunktion: war dieser Wert im letzten Submit?
function old(string $field, array $formdata, string $default = ''): string
{
    return htmlspecialchars($formdata[$field] ?? $default);
}

// Hilfsfunktion: war diese Option selected?
function selected(string $field, string $value, array $formdata): string
{
    return ($formdata[$field] ?? '') === $value ? 'selected' : '';
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Achse bearbeiten – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Achse bearbeiten</h1>

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

    <form action="aktualisieren.php" method="POST">

        <input type="hidden" name="id" value="<?= $id ?>">

        <div>
            <h2>Einstellungen</h2>

            <label>Name<span class="pflicht">*</span></label>
            <input type="text" name="name"
                value="<?= old('name', $formdata) ?>" required>

            <label>Code<span class="pflicht">*</span></label>
            <input type="text" name="code"
                value="<?= old('code', $formdata) ?>"
                placeholder="kleinbuchstaben, kein Leerzeichen, z.B. farbe"
                required>

            <label>Darstellungsform</label>
            <select name="darstellungsform">
                <option value="swatches" <?= selected('darstellungsform', 'swatches', $formdata) ?>>swatches</option>
                <option value="dropdown" <?= selected('darstellungsform', 'dropdown', $formdata) ?>>dropdown</option>
                <option value="radiobutton" <?= selected('darstellungsform', 'radiobutton', $formdata) ?>>radiobutton</option>
                <option value="freitext" <?= selected('darstellungsform', 'freitext', $formdata) ?>>freitext</option>
                <option value="pflichtfreitext" <?= selected('darstellungsform', 'pflichtfreitext', $formdata) ?>>pflicht-freitext</option>
            </select>

            <label>Reihenfolge</label>
            <input type="number" min="0" step="1" name="sort_order"
                value="<?= old('sort_order', $formdata, '0') ?>">

        </div>

        <button type="submit">Achse speichern</button>

    </form>

</body>

</html>