<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$id = (int) ($_GET['id'] ?? 0);
$service = new KundenService();
$kunde = $service->getById($id);

if (!$kunde) {
    echo 'Kunde nicht gefunden.';
    exit;
}

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

// Formdata aus DB vorbelegen wenn kein Repopulation-Fall
if (empty($formdata)) {
    $formdata = $kunde;
}

$db = Database::getInstance();
$kundengruppen       = $db->query("SELECT id, name, ist_standard FROM kundengruppen ORDER BY name")->fetchAll();
$zahlungsbedingungen = $db->query("SELECT id, name FROM zahlungsbedingungen WHERE aktiv = 1 ORDER BY name")->fetchAll();

function old(string $field, array $formdata, string $default = ''): string
{
    return htmlspecialchars((string)($formdata[$field] ?? $default));
}
function selected(string $field, string $value, array $formdata, string $default = ''): string
{
    $current = (string)($formdata[$field] ?? $default);
    return $current === $value ? 'selected' : '';
}

$anzeigename = $kunde['ist_firma'] && $kunde['firmenname']
    ? $kunde['firmenname']
    : trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''));

$pageTitle        = 'Bearbeiten: ' . $anzeigename;
$activeModule     = 'kunden';
$actionBarContent = <<<HTML
<button form="kunden-edit-form" type="submit" class="btn btn-primary btn-sm">💾 Speichern</button>
<a href="detail.php?id={$id}" class="btn btn-secondary btn-sm">Abbrechen</a>
<div class="actionbar-sep"></div>
<div class="actionbar-right">
    <a href="status_setzen.php?id={$id}&status=gesperrt"
       onclick="return confirm('Kunde wirklich sperren?')"
       class="btn btn-secondary btn-sm" style="color:var(--color-danger)">Sperren</a>
</div>
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

<form id="kunden-edit-form" method="POST" action="aktualisieren.php">
<input type="hidden" name="id" value="<?= $id ?>">

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
                    value="<?= old('firmenname', $formdata) ?>">
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
                    value="<?= old('uid_nummer', $formdata) ?>">
            </div>
            <div id="feld-kreditlimit" style="<?= empty($formdata['ist_firma']) ? 'display:none;' : '' ?>">
                <label class="erp-label">Kreditlimit (€)</label>
                <input type="number" name="kreditlimit" class="erp-input" style="width:100%"
                    value="<?= old('kreditlimit', $formdata) ?>" min="0" step="0.01">
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
                        <option value="<?= $kg['id'] ?>" <?= selected('kundengruppe_id', (string)$kg['id'], $formdata) ?>>
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
                    <option value="vorkasse"    <?= selected('standardzahlungsart', 'vorkasse',    $formdata) ?>>Vorkasse</option>
                    <option value="rechnung"    <?= selected('standardzahlungsart', 'rechnung',    $formdata) ?>>Rechnung</option>
                    <option value="kreditkarte" <?= selected('standardzahlungsart', 'kreditkarte', $formdata) ?>>Kreditkarte</option>
                    <option value="paypal"      <?= selected('standardzahlungsart', 'paypal',      $formdata) ?>>PayPal</option>
                    <option value="bar"         <?= selected('standardzahlungsart', 'bar',         $formdata) ?>>Bar</option>
                </select>
            </div>

            <div>
                <label class="erp-label">Kundenherkunft</label>
                <select name="kundenherkunft" class="erp-select" style="width:100%">
                    <option value="erp"        <?= selected('kundenherkunft', 'erp',        $formdata) ?>>ERP (manuell)</option>
                    <option value="shop"       <?= selected('kundenherkunft', 'shop',       $formdata) ?>>Shop</option>
                    <option value="messe"      <?= selected('kundenherkunft', 'messe',      $formdata) ?>>Messe</option>
                    <option value="empfehlung" <?= selected('kundenherkunft', 'empfehlung', $formdata) ?>>Empfehlung</option>
                    <option value="walkin"     <?= selected('kundenherkunft', 'walkin',     $formdata) ?>>Walk-in</option>
                    <option value="kasse"      <?= selected('kundenherkunft', 'kasse',      $formdata) ?>>Kasse</option>
                </select>
            </div>

            <div>
                <label class="erp-label">Sprache</label>
                <select name="sprache" class="erp-select" style="width:100%">
                    <option value="de" <?= selected('sprache', 'de', $formdata, 'de') ?>>Deutsch</option>
                    <option value="en" <?= selected('sprache', 'en', $formdata, 'de') ?>>Englisch</option>
                </select>
            </div>

            <div>
                <label class="erp-label">Status</label>
                <select name="status" class="erp-select" style="width:100%">
                    <option value="aktiv"     <?= selected('status', 'aktiv',     $formdata, 'aktiv') ?>>Aktiv</option>
                    <option value="gesperrt"  <?= selected('status', 'gesperrt',  $formdata) ?>>Gesperrt</option>
                    <option value="geloescht" <?= selected('status', 'geloescht', $formdata) ?>>Gelöscht</option>
                </select>
            </div>

        </div>

        <div style="margin-top:var(--space-md)">
            <label class="erp-label">Interne Notiz</label>
            <textarea name="notiz" class="erp-input" style="width:100%;height:80px;resize:vertical"><?= old('notiz', $formdata) ?></textarea>
        </div>
    </div>

</form>

<script src="<?= BASE_PATH ?>/js/kunden.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
