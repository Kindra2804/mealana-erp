<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinRepository.php';

$repo     = new GutscheinRepository();
$vorlagen = $repo->findAlleVorlagen(true);

$fehler   = $_SESSION['fehler'] ?? null;
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$pageTitle    = 'Neuer Gutschein';
$activeModule = 'gutscheine';

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:640px">
    <form method="post" action="speichern.php">
        <div style="margin-bottom:14px">
            <label style="display:block;font-weight:600;margin-bottom:4px">Betrag (€) *</label>
            <input type="number" name="betrag" class="erp-input" step="0.01" min="0.01" required
                   value="<?= htmlspecialchars($formdata['betrag'] ?? '') ?>" style="width:160px">
        </div>

        <?php if (!empty($vorlagen)): ?>
        <div style="margin-bottom:14px">
            <label style="display:block;font-weight:600;margin-bottom:4px">Design</label>
            <select name="vorlage_id" class="erp-select">
                <option value="">Standard (kein Hintergrundbild)</option>
                <?php foreach ($vorlagen as $v): ?>
                    <option value="<?= $v['id'] ?>" <?= ($formdata['vorlage_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div style="margin-bottom:14px">
            <label style="display:block;font-weight:600;margin-bottom:4px">Versandart</label>
            <select name="versandart" class="erp-select" id="versandart-select" onchange="empfaengerFelderToggle()">
                <option value="selbst_ausdrucken" <?= ($formdata['versandart'] ?? '') === 'selbst_ausdrucken' ? 'selected' : '' ?>>Selbst ausdrucken/übergeben (kein Mailversand)</option>
                <option value="versenden" <?= ($formdata['versandart'] ?? '') === 'versenden' ? 'selected' : '' ?>>Per E-Mail versenden</option>
            </select>
        </div>

        <div id="empfaenger-felder" style="display:none">
            <div style="margin-bottom:14px">
                <label style="display:block;font-weight:600;margin-bottom:4px">Name des Empfängers</label>
                <input type="text" name="empfaenger_name" class="erp-input" value="<?= htmlspecialchars($formdata['empfaenger_name'] ?? '') ?>">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-weight:600;margin-bottom:4px">E-Mail des Empfängers</label>
                <input type="email" name="empfaenger_email" class="erp-input" value="<?= htmlspecialchars($formdata['empfaenger_email'] ?? '') ?>">
            </div>
            <div style="margin-bottom:14px">
                <label style="display:block;font-weight:600;margin-bottom:4px">Zustellung am (leer = sofort)</label>
                <input type="date" name="zustellung_am" class="erp-input" value="<?= htmlspecialchars($formdata['zustellung_am'] ?? '') ?>">
            </div>
        </div>

        <div style="margin-bottom:14px">
            <label style="display:block;font-weight:600;margin-bottom:4px">Grußtext</label>
            <textarea name="grusstext" class="erp-input" rows="3"><?= htmlspecialchars($formdata['grusstext'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn btn-primary">Gutschein erstellen</button>
        <a href="liste.php" class="btn btn-secondary">Abbrechen</a>
    </form>
</div>

<script>
function empfaengerFelderToggle() {
    var sel = document.getElementById('versandart-select');
    document.getElementById('empfaenger-felder').style.display = sel.value === 'versenden' ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', empfaengerFelderToggle);
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
