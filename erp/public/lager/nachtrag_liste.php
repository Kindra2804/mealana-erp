<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$fehler  = $_SESSION['fehler']  ?? [];
$erfolg  = $_SESSION['erfolg']  ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$service = new LagerService();

$zeilen = $service->getNachzutragendeChargen();

// if (empty($zeilen)) {
//     $_SESSION['fehler'] = 'Keine offenen Chargen';
//     exit;
// }

?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>zu prüfende Chargen / Artikel – MeaLana ERP</title>
</head>

<body>
    <?php require_once __DIR__ . '/../includes/nav.php'; ?>
    <h1>offene Chargeneintragungen</h1>

    <?php if ($erfolg): ?>
        <div class="erfolg-box"><?= htmlspecialchars($erfolg) ?></div>
    <?php endif; ?>

    <?php if (!empty($fehler)): ?>
        <div class="fehler-box">
            <ul><?php foreach ($fehler as $f): ?>
                    <li><?= htmlspecialchars($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (empty($zeilen)): ?>
        <p>Keine offenen Chargen — alles erfasst!</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Artikel</th>
                <th>Name / Variante</th>
                <th>Farbe</th>
                <th>Lager</th>
                <th>Bestand</th>
                <th>Menge neue Charge</th>
                <th>Charge eintragen</th>
            </tr>
            <?php foreach ($zeilen as $zeile): ?>
                <tr>
                    <form action="nachtrag_speichern.php" method="POST">
                        <input type="hidden" name="lagerbestand_id" value="<?= $zeile['id'] ?>">
                        <td><?= htmlspecialchars($zeile['vater_nr']) ?></td>
                        <td><?= htmlspecialchars($zeile['artikel_name']) ?></td>
                        <td><?= htmlspecialchars($zeile['farbe_name'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($zeile['lager_name']) ?></td>
                        <td><?= $zeile['bestand'] ?></td>
                        <td>
                            <input type="number" name="menge" min="0.001" step="0.001"
                                max="<?= $zeile['bestand'] ?>" placeholder="Menge">
                        </td>
                        <td>
                            <input type="text" name="charge" placeholder="z.B. LOT-2024-007">
                            <button type="submit">Speichern</button>
                        </td>
                    </form>
                </tr>

            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>

</html>