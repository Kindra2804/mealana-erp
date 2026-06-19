<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$db = Database::getInstance();
$kundengruppen       = $db->query("SELECT id, name, ist_standard FROM kundengruppen ORDER BY name")->fetchAll();
$zahlungsbedingungen = $db->query("SELECT id, name FROM zahlungsbedingungen WHERE aktiv = 1 ORDER BY name")->fetchAll();

$stdKg = array_filter($kundengruppen, fn($kg) => $kg['ist_standard']);
$standardKgId = $stdKg ? (string)array_values($stdKg)[0]['id'] : '';

function old(string $field, array $formdata, string $default = ''): string
{
    return htmlspecialchars((string)($formdata[$field] ?? $default));
}
function selected(string $field, string $value, array $formdata, string $default = ''): string
{
    $current = (string)($formdata[$field] ?? $default);
    return $current === $value ? 'selected' : '';
}

$pageTitle        = 'Neuer Kunde';
$activeModule     = 'kunden';
$actionBarContent = <<<HTML
<button form="kunden-neu-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
<a href="liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
HTML;

require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
<div style="background:#fff5f5;border-left:3px solid var(--color-danger);padding:var(--space-sm) var(--space-md);border-radius:4px;margin-bottom:var(--space-md)">
    <strong>Bitte korrigiere folgende Fehler:</strong>
    <ul style="margin:var(--space-xs) 0 0 var(--space-md)">
        <?php foreach ($fehler as $f): ?>
            <li><?= htmlspecialchars($f) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form id="kunden-neu-form" method="POST" action="speichern.php">

    <!-- ── Stammdaten ───────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-md)">
        <div style="font-weight:600;font-size:13px;color:var(--color-nav);margin-bottom:var(--space-md);padding-bottom:var(--space-xs);border-bottom:1px solid var(--color-border)">
            Stammdaten
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm) var(--space-md)">

            <div style="grid-column:1/-1;display:flex;align-items:center;gap:10px">
                <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;font-weight:500">
                    <input type="checkbox" id="ist_firma" name="ist_firma" value="1"
                        <?= !empty($formdata['ist_firma']) ? 'checked' : '' ?>
                        onchange="toggleFirma(this.checked)">
                    Firmenkunde (B2B)
                </label>
            </div>

            <div id="feld-firmenname" style="<?= empty($formdata['ist_firma']) ? 'display:none;' : '' ?>grid-column:1/-1">
                <label class="erp-label">Firmenname <span style="color:var(--color-danger)">*</span></label>
                <input type="text" name="firmenname" class="erp-input" style="width:100%"
                    value="<?= old('firmenname', $formdata) ?>" placeholder="z.B. Müller GmbH">
            </div>

            <div>
                <label class="erp-label">Vorname</label>
                <input type="text" name="vorname" class="erp-input" style="width:100%"
                    value="<?= old('vorname', $formdata) ?>">
            </div>
            <div>
                <label class="erp-label">Nachname <span id="nachname-stern" style="color:var(--color-danger);<?= !empty($formdata['ist_firma']) ? 'display:none' : '' ?>">*</span></label>
                <input type="text" name="nachname" class="erp-input" style="width:100%"
                    value="<?= old('nachname', $formdata) ?>">
            </div>

            <div>
                <label class="erp-label">E-Mail</label>
                <input type="email" name="email" class="erp-input" style="width:100%"
                    value="<?= old('email', $formdata) ?>">
            </div>
            <div>
                <label class="erp-label">Telefon</label>
                <input type="text" name="telefon" class="erp-input" style="width:100%"
                    value="<?= old('telefon', $formdata) ?>">
            </div>

            <div>
                <label class="erp-label">Mobil</label>
                <input type="text" name="mobil" class="erp-input" style="width:100%"
                    value="<?= old('mobil', $formdata) ?>">
            </div>
            <div id="feld-geburtsdatum" style="<?= !empty($formdata['ist_firma']) ? 'visibility:hidden;' : '' ?>">
                <label class="erp-label">Geburtsdatum</label>
                <input type="date" name="geburtsdatum" class="erp-input" style="width:100%"
                    value="<?= old('geburtsdatum', $formdata) ?>">
            </div>

            <div id="feld-uid" style="<?= empty($formdata['ist_firma']) ? 'display:none;' : '' ?>">
                <label class="erp-label">UID-Nummer (Steuer-ID)</label>
                <input type="text" name="uid_nummer" class="erp-input" style="width:100%"
                    value="<?= old('uid_nummer', $formdata) ?>" placeholder="ATU12345678">
            </div>
            <div id="feld-kreditlimit" style="<?= empty($formdata['ist_firma']) ? 'display:none;' : '' ?>">
                <label class="erp-label">Kreditlimit (€)</label>
                <input type="number" name="kreditlimit" class="erp-input" style="width:100%"
                    value="<?= old('kreditlimit', $formdata) ?>" min="0" step="0.01" placeholder="0.00">
            </div>

        </div>
    </div>

    <!-- ── Erste Adresse ────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-md)">
        <div style="font-weight:600;font-size:13px;color:var(--color-nav);margin-bottom:var(--space-md);padding-bottom:var(--space-xs);border-bottom:1px solid var(--color-border)">
            Hauptadresse <span style="font-size:11px;font-weight:400;color:var(--color-text-muted)">(optional – kann später ergänzt werden)</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 80px;gap:var(--space-sm) var(--space-md)">
            <div>
                <label class="erp-label">Straße</label>
                <input type="text" name="strasse" class="erp-input" style="width:100%"
                    value="<?= old('strasse', $formdata) ?>">
            </div>
            <div>
                <label class="erp-label">Nr.</label>
                <input type="text" name="hausnummer" class="erp-input" style="width:100%"
                    value="<?= old('hausnummer', $formdata) ?>">
            </div>
        </div>
        <div style="display:grid;grid-template-columns:100px 1fr 80px;gap:var(--space-sm) var(--space-md);margin-top:var(--space-sm)">
            <div>
                <label class="erp-label">PLZ</label>
                <input type="text" name="plz" class="erp-input" style="width:100%"
                    value="<?= old('plz', $formdata) ?>">
            </div>
            <div>
                <label class="erp-label">Ort</label>
                <input type="text" name="ort" class="erp-input" style="width:100%"
                    value="<?= old('ort', $formdata) ?>">
            </div>
            <div>
                <label class="erp-label">Land</label>
                <select name="land" class="erp-select" style="width:100%">
                    <option value="AT" <?= selected('land', 'AT', $formdata, 'AT') ?>>AT</option>
                    <option value="DE" <?= selected('land', 'DE', $formdata, 'AT') ?>>DE</option>
                    <option value="CH" <?= selected('land', 'CH', $formdata, 'AT') ?>>CH</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── Einstellungen ────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:var(--space-md)">
        <div style="font-weight:600;font-size:13px;color:var(--color-nav);margin-bottom:var(--space-md);padding-bottom:var(--space-xs);border-bottom:1px solid var(--color-border)">
            Einstellungen
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-sm) var(--space-md)">

            <div>
                <label class="erp-label">Kundengruppe</label>
                <select name="kundengruppe_id" class="erp-select" style="width:100%">
                    <option value="">– Keine –</option>
                    <?php foreach ($kundengruppen as $kg): ?>
                        <option value="<?= $kg['id'] ?>" <?= selected('kundengruppe_id', (string)$kg['id'], $formdata, $standardKgId) ?>>
                            <?= htmlspecialchars($kg['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="erp-label">Zahlungsbedingung</label>
                <select name="zahlungsbedingung_id" class="erp-select" style="width:100%">
                    <option value="">– Standard –</option>
                    <?php foreach ($zahlungsbedingungen as $zb): ?>
                        <option value="<?= $zb['id'] ?>" <?= selected('zahlungsbedingung_id', (string)$zb['id'], $formdata) ?>>
                            <?= htmlspecialchars($zb['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="erp-label">Standard-Zahlungsart</label>
                <select name="standardzahlungsart" class="erp-select" style="width:100%">
                    <option value="">– Keine –</option>
                    <option value="vorkasse"   <?= selected('standardzahlungsart', 'vorkasse',   $formdata) ?>>Vorkasse</option>
                    <option value="rechnung"   <?= selected('standardzahlungsart', 'rechnung',   $formdata) ?>>Rechnung</option>
                    <option value="kreditkarte"<?= selected('standardzahlungsart', 'kreditkarte',$formdata) ?>>Kreditkarte</option>
                    <option value="paypal"     <?= selected('standardzahlungsart', 'paypal',     $formdata) ?>>PayPal</option>
                    <option value="bar"        <?= selected('standardzahlungsart', 'bar',        $formdata) ?>>Bar</option>
                </select>
            </div>

            <div>
                <label class="erp-label">Kundenherkunft</label>
                <select name="kundenherkunft" class="erp-select" style="width:100%">
                    <option value="erp"       <?= selected('kundenherkunft', 'erp',       $formdata, 'erp') ?>>ERP (manuell)</option>
                    <option value="shop"      <?= selected('kundenherkunft', 'shop',      $formdata, 'erp') ?>>Shop</option>
                    <option value="messe"     <?= selected('kundenherkunft', 'messe',     $formdata, 'erp') ?>>Messe</option>
                    <option value="empfehlung"<?= selected('kundenherkunft', 'empfehlung',$formdata, 'erp') ?>>Empfehlung</option>
                    <option value="walkin"    <?= selected('kundenherkunft', 'walkin',    $formdata, 'erp') ?>>Walk-in</option>
                    <option value="kasse"     <?= selected('kundenherkunft', 'kasse',     $formdata, 'erp') ?>>Kasse</option>
                </select>
            </div>

            <div>
                <label class="erp-label">Sprache</label>
                <select name="sprache" class="erp-select" style="width:100%">
                    <option value="de" <?= selected('sprache', 'de', $formdata, 'de') ?>>Deutsch</option>
                    <option value="en" <?= selected('sprache', 'en', $formdata, 'de') ?>>Englisch</option>
                </select>
            </div>

        </div>

        <div style="margin-top:var(--space-md)">
            <label class="erp-label">Interne Notiz</label>
            <textarea name="notiz" class="erp-input" style="width:100%;height:70px;resize:vertical"
                placeholder="Interne Anmerkungen zum Kunden …"><?= old('notiz', $formdata) ?></textarea>
        </div>

        <div style="margin-top:var(--space-sm)">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                <input type="checkbox" name="newsletter" value="1"
                    <?= !empty($formdata['newsletter']) ? 'checked' : '' ?>>
                Newsletter-Einwilligung erteilt (DSGVO-Consent wird gespeichert)
            </label>
        </div>

    </div>

</form>

<script>
function toggleFirma(isFirma) {
    document.getElementById('feld-firmenname').style.display  = isFirma ? '' : 'none';
    document.getElementById('feld-uid').style.display         = isFirma ? '' : 'none';
    document.getElementById('feld-kreditlimit').style.display = isFirma ? '' : 'none';
    document.getElementById('feld-geburtsdatum').style.visibility = isFirma ? 'hidden' : '';
    document.getElementById('nachname-stern').style.display   = isFirma ? 'none' : '';
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
