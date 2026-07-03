<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/lieferanten/LieferantenService.php';

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['erfolg'], $_SESSION['formdata']);

$service = new LieferantenService();
$laender = $service->laender();

$pageTitle        = 'Neuer Lieferant';
$activeModule     = 'lieferanten';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
    <a href="{$basePath}/lieferanten/liste.php" class="btn btn-secondary btn-sm">← Zurück</a>
HTML;

/**
 * Rendert eine Vertreter-Zeile fürs Anlageformular.
 * $index ist entweder eine echte Array-Position (bei vorbefüllten Zeilen
 * nach einem Validierungsfehler) oder der Platzhalter '__INDEX__', der
 * client-seitig beim Hinzufügen neuer Zeilen per JS ersetzt wird.
 */
function vertreterFeldZeile(int|string $index, array $row = []): string
{
    $anrede   = $row['anrede'] ?? '';
    $vorname  = htmlspecialchars($row['vorname'] ?? '');
    $nachname = htmlspecialchars($row['nachname'] ?? '');
    $telefon  = htmlspecialchars($row['telefon'] ?? '');
    $mobil    = htmlspecialchars($row['mobil'] ?? '');
    $email    = htmlspecialchars($row['email'] ?? '');
    ob_start();
    ?>
    <div class="vertreter-row" style="display:grid;grid-template-columns:110px 1fr 1fr 1fr 1fr 1fr 32px;gap:8px;margin-bottom:8px;align-items:end">
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">Anrede</label>
            <select name="vertreter[<?= $index ?>][anrede]" class="erp-select" style="width:100%">
                <option value="">–</option>
                <option value="herr" <?= $anrede === 'herr' ? 'selected' : '' ?>>Herr</option>
                <option value="frau" <?= $anrede === 'frau' ? 'selected' : '' ?>>Frau</option>
                <option value="divers" <?= $anrede === 'divers' ? 'selected' : '' ?>>Divers</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">Vorname</label>
            <input type="text" name="vertreter[<?= $index ?>][vorname]" class="erp-input" style="width:100%" value="<?= $vorname ?>">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">Nachname</label>
            <input type="text" name="vertreter[<?= $index ?>][nachname]" class="erp-input" style="width:100%" value="<?= $nachname ?>">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">Telefon</label>
            <input type="text" name="vertreter[<?= $index ?>][telefon]" class="erp-input" style="width:100%" value="<?= $telefon ?>">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">Mobil</label>
            <input type="text" name="vertreter[<?= $index ?>][mobil]" class="erp-input" style="width:100%" value="<?= $mobil ?>">
        </div>
        <div>
            <label style="display:block;font-size:11px;font-weight:600;margin-bottom:4px">E-Mail</label>
            <input type="email" name="vertreter[<?= $index ?>][email]" class="erp-input" style="width:100%" value="<?= $email ?>">
        </div>
        <button type="button" class="btn btn-secondary btn-sm" style="padding:6px" onclick="this.closest('.vertreter-row').remove()" title="Zeile entfernen">✕</button>
    </div>
    <?php
    return ob_get_clean();
}

$vertreterRows = $formdata['vertreter'] ?? [[]];

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

<form method="POST" action="speichern.php">
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
                <option value="1" <?= ($formdata['aktiv'] ?? '1') === '1' ? 'selected' : '' ?>>Aktiv</option>
                <option value="0" <?= ($formdata['aktiv'] ?? '1') === '0' ? 'selected' : '' ?>>Inaktiv</option>
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
    <h3 style="margin:0 0 14px">Vertreter</h3>
    <div id="vertreter-rows">
        <?php foreach ($vertreterRows as $i => $row): ?>
            <?= vertreterFeldZeile($i, $row) ?>
        <?php endforeach; ?>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addVertreterRow()">+ Vertreter</button>
</div>

<div class="card" style="margin-bottom:12px">
    <h3 style="margin:0 0 14px">Interne Notizen</h3>
    <textarea name="interne_notizen" rows="4" class="erp-input" style="width:100%;resize:vertical"
              placeholder="Freitext – interne Hinweise, Besonderheiten, ..."><?= htmlspecialchars($formdata['interne_notizen'] ?? '') ?></textarea>
</div>

<div style="margin-bottom:24px">
    <button type="submit" class="btn btn-primary">Lieferant anlegen</button>
</div>
</form>

<script>
window.LIEFERANT_VERTRETER_INDEX    = <?= count($vertreterRows) ?>;
window.LIEFERANT_VERTRETER_TEMPLATE = <?= json_encode(vertreterFeldZeile('__INDEX__')) ?>;
</script>
<script src="<?= BASE_PATH ?>/js/lieferanten_neu.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
