<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';

$fehler = $_SESSION['fehler'] ?? [];
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$service = new LagerService();
$zeilen  = $service->getNachzutragendeChargen();

$pageTitle        = 'Chargen-Nachtrag';
$activeModule     = 'lager';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lager/uebersicht.php" class="btn btn-secondary btn-sm">← Lagerübersicht</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px">
        <ul style="margin:0;padding-left:18px;color:var(--color-danger)">
            <?php foreach ($fehler as $f): ?>
                <li><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($erfolg): ?>
    <div class="card" style="border-left:4px solid var(--color-success);margin-bottom:12px">
        <p style="margin:0;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></p>
    </div>
<?php endif; ?>

<div class="card">
    <?php if (empty($zeilen)): ?>
        <p style="color:var(--color-text-muted);padding:8px 0">Keine offenen Chargen — alles erfasst! ✅</p>
    <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr>
                    <th>ARTIKEL</th>
                    <th>NAME / VARIANTE</th>
                    <th>LAGER</th>
                    <th style="text-align:right">BESTAND</th>
                    <th>MENGE NEUE CHARGE</th>
                    <th>CHARGE EINTRAGEN</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($zeilen as $zeile): ?>
                <tr>
                    <form action="nachtrag_speichern.php" method="POST">
                        <input type="hidden" name="lagerbestand_id" value="<?= $zeile['id'] ?>">
                        <td><?= htmlspecialchars($zeile['vater_nr']) ?></td>
                        <td><?= htmlspecialchars($zeile['artikel_name']) ?></td>
                        <td><?= htmlspecialchars($zeile['lager_name']) ?></td>
                        <td style="text-align:right"><?= $zeile['bestand'] ?></td>
                        <td>
                            <input type="number" name="menge" class="erp-input" style="width:100px"
                                   min="0.001" step="0.001" max="<?= $zeile['bestand'] ?>" placeholder="Menge">
                        </td>
                        <td style="display:flex;gap:8px;align-items:center">
                            <input type="text" name="charge" class="erp-input" placeholder="z.B. LOT-2024-007">
                            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
