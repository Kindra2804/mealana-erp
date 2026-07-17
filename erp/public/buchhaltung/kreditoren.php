<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenRepository.php';

$repo   = new LieferantenRepository();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$lieferanten = $repo->findAll();

$pageTitle    = 'Kreditoren';
$activeModule = 'buchhaltung';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>
<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Kreditorenkonten — jedem Lieferanten manuell ein Konto zuweisen</div>
    <div style="font-size:12px;color:var(--color-text-muted);padding:0 16px 10px">
        Kein automatisch berechnetes Konto (anders als bei Kunden/Debitoren) — Kreditorennummern kommen meist schon vom Steuerberater aus der bisherigen Buchhaltung.
    </div>
    <form method="post" action="<?= BASE_PATH ?>/buchhaltung/kreditoren_speichern.php">
        <table class="erp-table">
            <thead>
                <tr>
                    <th>Lieferant</th>
                    <th>Firma</th>
                    <th>Land</th>
                    <th style="width:160px">Kreditorennummer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lieferanten as $l): ?>
                <tr>
                    <td><a href="<?= BASE_PATH ?>/lieferanten/detail.php?id=<?= $l['id'] ?>" style="color:var(--color-nav)"><?= htmlspecialchars($l['name']) ?></a></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($l['firma'] ?? '') ?></td>
                    <td style="color:var(--color-text-muted)"><?= htmlspecialchars($l['land_name'] ?? $l['land'] ?? '') ?></td>
                    <td>
                        <input type="text" name="kreditorennummer[<?= $l['id'] ?>]" class="erp-input" style="width:100%"
                               value="<?= htmlspecialchars($l['kreditorennummer'] ?? '') ?>" maxlength="10" placeholder="z.B. 300001">
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lieferanten)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:20px">Keine aktiven Lieferanten vorhanden</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div style="padding:12px 16px">
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
