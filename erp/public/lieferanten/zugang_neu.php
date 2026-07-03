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

$pageTitle        = 'Neuer Zugang';
$activeModule     = 'lieferanten';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lieferanten/detail.php?id={$lieferant_id}&tab=zugaenge" class="btn btn-secondary btn-sm">← Zurück zu Zugängen</a>
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

<div class="card" style="max-width:640px">
    <form method="POST" action="zugang_speichern.php">
        <input type="hidden" name="lieferant_id" value="<?= $lieferant_id ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px 16px">
            <div style="grid-column:1/-1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Bezeichnung *</label>
                <input type="text" name="bezeichnung" class="erp-input" style="width:100%" required
                       placeholder="z.B. Bestellportal, Händlerlogin, FTP"
                       value="<?= htmlspecialchars($formdata['bezeichnung'] ?? '') ?>">
            </div>
            <div style="grid-column:1/-1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">URL</label>
                <input type="text" name="url" class="erp-input" style="width:100%"
                       placeholder="https://..."
                       value="<?= htmlspecialchars($formdata['url'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Benutzername</label>
                <input type="text" name="benutzername" class="erp-input" style="width:100%"
                       autocomplete="off"
                       value="<?= htmlspecialchars($formdata['benutzername'] ?? '') ?>">
            </div>
            <div>
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Passwort</label>
                <div style="display:flex;gap:6px">
                    <input type="password" name="passwort" id="pw_field" class="erp-input" style="flex:1"
                           autocomplete="new-password">
                    <button type="button" onclick="togglePwInput()" style="background:none;border:1px solid var(--color-border);border-radius:4px;padding:0 10px;cursor:pointer;font-size:12px;color:var(--color-text-muted)">Zeigen</button>
                </div>
            </div>
            <div style="grid-column:1/-1">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Notizen</label>
                <textarea name="notizen" rows="3" class="erp-input" style="width:100%;resize:vertical"><?= htmlspecialchars($formdata['notizen'] ?? '') ?></textarea>
            </div>
        </div>

        <div style="margin-top:20px">
            <button type="submit" class="btn btn-primary">Zugang anlegen</button>
        </div>
    </form>
</div>

<script>
function togglePwInput() {
    const f = document.getElementById('pw_field');
    f.type = f.type === 'password' ? 'text' : 'password';
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
