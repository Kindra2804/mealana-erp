<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/modules/varianten/VariantenService.php';

$artikel_id = (int) ($_GET['artikel_id'] ?? 0);
if ($artikel_id <= 0) {
    header('Location: liste.php');
    exit;
}

$artikelService = new ArtikelService();
$variantenService = new VariantenService();

$vater = $artikelService->findById($artikel_id);
$achsen = $variantenService->findAchsenByArtikelId($artikel_id);
$werte = $variantenService->findWerteByArtikelId($artikel_id);

function kartesischesProdukt(array $arrays): array
{
    $result = [[]];  // startet mit einer leeren Kombination

    foreach ($arrays as $group) {
        $newResult = [];
        foreach ($result as $existing) {
            foreach ($group as $item) {
                $newResult[] = array_merge($existing, [$item]);
            }
        }
        $result = $newResult;
    }

    return $result;
}

// Werte nach achse_id gruppieren
$werteProAchse = [];
foreach ($werte as $w) {
    $werteProAchse[$w['achse_id']][] = $w;
}

// Kartesisches Produkt — nur wenn mind. eine Achse mit Werten da ist
$gruppen = array_values($werteProAchse);  // numerisch indiziert
$alleKombis = !empty($gruppen) ? kartesischesProdukt($gruppen) : [];

// var_dump($alleKombis);

$existing = $variantenService->findExistingKombinationen($artikel_id);

// Bestehende Wert-ID-Sets als Lookup bauen
$existingKeys = [];
foreach ($existing as $e) {
    $existingKeys[$e['wert_ids']] = $e;  // key: "1,3" → artikel-daten
}

// Kombis aufteilen
$neueKombis     = [];
$vorhandeneKombis = [];

foreach ($alleKombis as $kombi) {
    // Wert-IDs aus dieser Kombi extrahieren, sortieren, als String
    $ids = array_map(fn($w) => $w['id'], $kombi);
    sort($ids);
    $key = implode(',', $ids);

    if (isset($existingKeys[$key])) {
        $vorhandeneKombis[] = ['kombi' => $kombi, 'artikel' => $existingKeys[$key]];
    } else {
        $neueKombis[] = ['kombi' => $kombi, 'key' => $key];
    }
}

// var_dump($vorhandeneKombis);

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>VarKombi-Generator – MeaLana ERP</title>
    <link rel="stylesheet" href="/mealana/css/app.css">
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>Neue VarKombis für: <?= htmlspecialchars($vater['name']) ?></h1>

    <?php if (empty($neueKombis)): ?>
        <p>Alle möglichen Kombinationen sind bereits angelegt.</p>
    <?php else: ?>
        <form action="varkombi_erstellen.php" method="POST">
            <input type="hidden" name="artikel_id" value="<?= $artikel_id ?>">

            <label>
                <input type="checkbox" name="hat_eigenen_lagerstand" value="1" checked>
                Eigener Lagerstand pro Kind-Artikel
            </label>

            <table>
                <thead>
                    <tr>
                        <th></th>
                        <th>Kombination</th>
                        <th>Artikelnummer</th>
                        <th>Name</th>
                        <th>EAN</th>
                        <th>Aufpreis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($neueKombis as $n => $k): ?>
                        <?php
                        // Vorschläge berechnen bevor wir in den HTML-Teil gehen
                        $wertNamen  = array_column($k['kombi'], 'wert');
                        $vorschlagNr   = $vater['artikelnummer'] . '-' . implode('-', $wertNamen);
                        $vorschlagName = $vater['name'] . ' ' . implode(' ', $wertNamen);
                        $aufpreis      = array_sum(array_column($k['kombi'], 'aufpreis'));
                        ?>
                        <tr>
                            <td>
                                <input type="hidden" name="kombis[<?= $n ?>][key]" value="<?= htmlspecialchars($k['key']) ?>">
                                <input type="checkbox" name="kombis[<?= $n ?>][selected]" value="1" checked>
                            </td>
                            <td><?php foreach ($k['kombi'] as $w): ?><?= htmlspecialchars($w['wert']) ?> <?php endforeach; ?></td>
                            <td><input type="text" name="kombis[<?= $n ?>][artikelnummer]" value="<?= htmlspecialchars($vorschlagNr) ?>"></td>
                            <td><input type="text" name="kombis[<?= $n ?>][name]" value="<?= htmlspecialchars($vorschlagName) ?>"></td>
                            <td><input type="text" name="kombis[<?= $n ?>][ean]" value=""></td>
                            <td><input type="number" name="kombis[<?= $n ?>][aufpreis]" value="<?= number_format($aufpreis, 2, '.', '') ?>" step="0.01"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit">Markierte Varianten erstellen</button>
        </form>
    <?php endif; ?>


    <h2>Bereits bestehende VarKombis:</h2>
    <table>
        <?php foreach ($vorhandeneKombis as $v): ?>
            <tr>
                <td>
                    <a href="bearbeiten.php?id=<?= $v['artikel']['id'] ?>">
                        <?= htmlspecialchars($v['artikel']['artikelnummer']) ?>
                    </a>
                </td>
                <?php foreach ($v['kombi'] as $w): ?>
                    <td><?= htmlspecialchars($w['wert']) ?></td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>

</body>

</html>