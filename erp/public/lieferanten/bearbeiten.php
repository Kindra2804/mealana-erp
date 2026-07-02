<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: liste.php');
    exit;
}

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new LieferantenService();
$laender = $service->laender();

if (empty($formdata)) {
    $lieferant = $service->findById($id);
    if ($lieferant === false) {
        header('Location: liste.php');
        exit;
    }
    $formdata = $lieferant;
}

$pageTitle        = 'Lieferant bearbeiten';
$activeModule     = 'lieferanten';
$actionBarContent = <<<HTML
    <a href="/mealana/lieferanten/detail.php?id={$id}" class="btn btn-secondary btn-sm">← Zurück</a>
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

<form action="aktualisieren.php" method="POST">
<input type="hidden" name="id" value="<?= $id ?>">

<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 14px">Stammdaten</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 16px">

        <div style="grid-column:1/-1">
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Name *</label>
            <input type="text" name="name" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['name'] ?? '') ?>" required>
        </div>

        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Firma</label>
            <input type="text" name="firma" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['firma'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Firmenzusatz</label>
            <input type="text" name="firmenzusatz" class="erp-input" style="width:100%" placeholder="z.B. Niederlassung, c/o"
                   value="<?= htmlspecialchars($formdata['firmenzusatz'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Kundennummer (bei Lieferant)</label>
            <input type="text" name="kundennummer" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['kundennummer'] ?? '') ?>">
        </div>

        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Land</label>
            <select name="land" class="erp-select" style="width:100%">
                <?php foreach ($laender as $l): ?>
                    <option value="<?= $l['iso_code'] ?>"
                        <?= ($formdata['land'] ?? 'AT') === $l['iso_code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($l['name_de']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">UStID</label>
            <input type="text" name="ustid" class="erp-input" style="width:100%" placeholder="z.B. ATU12345678"
                   value="<?= htmlspecialchars($formdata['ustid'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Steuerregel</label>
            <select name="steuerregel" class="erp-select" style="width:100%">
                <?php $sr = $formdata['steuerregel'] ?? 'inland'; ?>
                <option value="inland"            <?= $sr === 'inland'            ? 'selected' : '' ?>>Inland</option>
                <option value="eu_igl"            <?= $sr === 'eu_igl'            ? 'selected' : '' ?>>EU – Innergem. Erwerb (USt-frei)</option>
                <option value="drittland_einfuhr" <?= $sr === 'drittland_einfuhr' ? 'selected' : '' ?>>Drittland – Einfuhr</option>
                <option value="reverse_charge"    <?= $sr === 'reverse_charge'    ? 'selected' : '' ?>>Reverse-Charge (Dienstleistung)</option>
            </select>
        </div>

        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Währung</label>
            <select name="waehrung" class="erp-select" style="width:100%">
                <?php foreach (['EUR','USD','GBP','CHF','CZK','HUF'] as $w): ?>
                    <option value="<?= $w ?>" <?= ($formdata['waehrung'] ?? 'EUR') === $w ? 'selected' : '' ?>><?= $w ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Straße</label>
            <input type="text" name="strasse" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['strasse'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">PLZ</label>
            <input type="text" name="plz" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['plz'] ?? '') ?>">
        </div>

        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Ort</label>
            <input type="text" name="ort" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['ort'] ?? '') ?>">
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
                <option value="1" <?= (string)($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Aktiv</option>
                <option value="0" <?= (string)($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Inaktiv</option>
            </select>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 14px">Konditionen</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 16px">

        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Zahlungsziel (Tage)</label>
            <input type="number" name="zahlungsziel_tage" class="erp-input" style="width:100%" min="0" placeholder="z.B. 30"
                   value="<?= htmlspecialchars($formdata['zahlungsziel_tage'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Skonto (%)</label>
            <input type="number" name="skonto_prozent" class="erp-input" style="width:100%" min="0" max="100" step="0.01" placeholder="z.B. 2.00"
                   value="<?= htmlspecialchars($formdata['skonto_prozent'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Skonto bis (Tage)</label>
            <input type="number" name="skonto_tage" class="erp-input" style="width:100%" min="0" placeholder="z.B. 14"
                   value="<?= htmlspecialchars($formdata['skonto_tage'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Mindestbestellwert (€)</label>
            <input type="number" name="mindestbestellwert" class="erp-input" style="width:100%" min="0" step="0.01"
                   value="<?= htmlspecialchars($formdata['mindestbestellwert'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Standard-Lieferkosten (€)</label>
            <input type="number" name="standard_lieferkosten" class="erp-input" style="width:100%" min="0" step="0.01" placeholder="Vorbelegung für Bestellung"
                   value="<?= htmlspecialchars($formdata['standard_lieferkosten'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Standard-Lieferzeit (Tage)</label>
            <input type="number" name="lieferzeit_tage" class="erp-input" style="width:100%" min="0"
                   value="<?= htmlspecialchars($formdata['lieferzeit_tage'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Lieferbedingung</label>
            <select name="lieferbedingung" class="erp-select" style="width:100%">
                <option value="">– keine –</option>
                <option value="frei_haus" <?= ($formdata['lieferbedingung'] ?? '') === 'frei_haus' ? 'selected' : '' ?>>Frei Haus</option>
                <option value="ab_werk"   <?= ($formdata['lieferbedingung'] ?? '') === 'ab_werk'   ? 'selected' : '' ?>>Ab Werk</option>
                <option value="ab_lager"  <?= ($formdata['lieferbedingung'] ?? '') === 'ab_lager'  ? 'selected' : '' ?>>Ab Lager</option>
                <option value="sonstige"  <?= ($formdata['lieferbedingung'] ?? '') === 'sonstige'  ? 'selected' : '' ?>>Sonstige</option>
            </select>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 14px">Bankverbindung</h3>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px 16px">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">IBAN</label>
            <input type="text" name="iban" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['iban'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">BIC</label>
            <input type="text" name="bic" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['bic'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Bank</label>
            <input type="text" name="bank_name" class="erp-input" style="width:100%"
                   value="<?= htmlspecialchars($formdata['bank_name'] ?? '') ?>">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Kontoinhaber</label>
            <input type="text" name="kontoinhaber" class="erp-input" style="width:100%" placeholder="nur falls abweichend von Firma"
                   value="<?= htmlspecialchars($formdata['kontoinhaber'] ?? '') ?>">
        </div>
    </div>
</div>

<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 14px">Interne Notizen</h3>
    <textarea name="interne_notizen" rows="4" class="erp-input" style="width:100%;resize:vertical"
              placeholder="Freitext – interne Hinweise, Besonderheiten, ..."><?= htmlspecialchars($formdata['interne_notizen'] ?? '') ?></textarea>
</div>

<div style="margin-bottom:24px">
    <button type="submit" class="btn btn-primary">Änderungen speichern</button>
</div>
</form>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
