<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/achsen/AchsenService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';

$artikelId = (int)($_GET['artikel_id'] ?? 0);
if ($artikelId <= 0) {
    header('Location: liste.php');
    exit;
}

$varService  = new VariantenService();
$achsService = new AchsenService();

$alleAchsen      = $achsService->findAll();          // alle globalen Achsen
$zugewieseneIds  = array_column(
    $varService->findAchsenByArtikelId($artikelId),
    'achse_id'
);                                                    // welche sind schon zugewiesen?
$vorhandeneWerte = $varService->findWerteByArtikelId($artikelId);  // vorhandene Werte

// Werte nach achse_id gruppieren
$werteProAchse = [];
foreach ($vorhandeneWerte as $w) {
    $werteProAchse[$w['achse_id']][] = $w;
}

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>Achsen zuweisen – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Achsen zuweisen</h1>

    <p style="font-size:0.9em">
        Fehlende Achse?
        <a href="/mealana/achsen/neu.php" target="_blank">Neue Achse anlegen ↗</a>
    </p>


    <form action="achsen_speichern.php" method="POST">
        <input type="hidden" name="artikel_id" value="<?= $artikelId ?>">

        <?php foreach ($alleAchsen as $achse): ?>
            <div>
                <label>
                    <input type="checkbox" name="achsen[]"
                        value="<?= $achse['id'] ?>"
                        <?= in_array($achse['id'], $zugewieseneIds) ? 'checked' : '' ?>>
                    <?= htmlspecialchars($achse['name']) ?>
                </label>

                <!-- Werte für diese Achse -->
                <?php $existing = $werteProAchse[$achse['id']] ?? []; ?>

                <input type="text" name="werte[<?= $achse['id'] ?>][0][wert]"
                    value="<?= htmlspecialchars($existing[0]['wert'] ?? '') ?>"
                    placeholder="Wert 1">

                <input type="text" name="werte[<?= $achse['id'] ?>][1][wert]"
                    value="<?= htmlspecialchars($existing[1]['wert'] ?? '') ?>"
                    placeholder="Wert 2">

                <input type="text" name="werte[<?= $achse['id'] ?>][2][wert]"
                    value="<?= htmlspecialchars($existing[2]['wert'] ?? '') ?>"
                    placeholder="Wert 3">

            </div>
        <?php endforeach; ?>
        <button type="submit">Variante speichern</button>
        <a href="bearbeiten.php?id=<?= $artikelId ?>">Abbrechen</a>
    </form>
</body>

</html>