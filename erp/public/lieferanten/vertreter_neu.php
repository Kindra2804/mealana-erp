<?php
require_once __DIR__ . '/../includes/auth_check.php';

$fehler       = $_SESSION['fehler']   ?? [];
$formdata     = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$lieferant_id = (int) ($_GET['lieferant_id'] ?? $formdata['lieferant_id'] ?? 0);
if ($lieferant_id <= 0) {
    header('Location: liste.php');
    exit;
}

$pageTitle        = 'Neuer Vertreter';
$activeModule     = 'lieferanten';
$actionBarContent = <<<HTML
    <a href="/mealana/lieferanten/detail.php?id={$lieferant_id}" class="btn btn-secondary btn-sm">← Zurück zum Lieferanten</a>
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
    <form method="POST" action="vertreter_speichern.php">
        <input type="hidden" name="lieferant_id" value="<?= $lieferant_id ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px">
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Anrede</label>
                <select name="anrede" class="erp-select" style="width:100%">
                    <?php $an = $formdata['anrede'] ?? ''; ?>
                    <option value="">–</option>
                    <option value="herr"   <?= $an === 'herr'   ? 'selected' : '' ?>>Herr</option>
                    <option value="frau"   <?= $an === 'frau'   ? 'selected' : '' ?>>Frau</option>
                    <option value="divers" <?= $an === 'divers' ? 'selected' : '' ?>>Divers</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Vorname</label>
                <input type="text" name="vorname" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['vorname'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Nachname *</label>
                <input type="text" name="nachname" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['nachname'] ?? '') ?>" required>
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
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Mobil</label>
                <input type="tel" name="mobil" class="erp-input" style="width:100%"
                       value="<?= htmlspecialchars($formdata['mobil'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Status</label>
                <select name="aktiv" class="erp-select" style="width:100%">
                    <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Aktiv</option>
                    <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Inaktiv</option>
                </select>
            </div>
            <div style="grid-column:1/-1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Notizen</label>
                <textarea name="notizen" rows="3" class="erp-input" style="width:100%;resize:vertical"><?= htmlspecialchars($formdata['notizen'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Vertreter anlegen</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
