<?php
require_once __DIR__ . '/../includes/auth_check.php';

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$pageTitle        = 'Neuer Lieferant';
$activeModule     = 'lieferanten';
$actionBarContent = <<<HTML
    <a href="/mealana/lieferanten/liste.php" class="btn btn-secondary btn-sm">← Zurück</a>
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

<div class="card" style="max-width:600px">
    <form method="POST" action="speichern.php">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px">
            <div style="grid-column:1/-1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Name *</label>
                <input type="text" name="name" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['name'] ?? '') ?>" required>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Land</label>
                <input type="text" name="land" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['land'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Website</label>
                <input type="text" name="website" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['website'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">E-Mail</label>
                <input type="email" name="email" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['email'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Telefon</label>
                <input type="text" name="telefon" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['telefon'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Status</label>
                <select name="aktiv" class="erp-select" style="width:100%">
                    <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Aktiv</option>
                    <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Inaktiv</option>
                </select>
            </div>
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Lieferant anlegen</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
