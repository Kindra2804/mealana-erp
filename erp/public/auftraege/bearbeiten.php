<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';

$service = new AuftragService();


$db = Database::getInstance();
$versandklassen = $db->query("SELECT id, name, preis_brutto FROM versandklassen ORDER BY sortierung")->fetchAll();
$preisanzeige   = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'preisanzeige_auftrag'")->fetchColumn() ?: 'brutto';
$epLabel     = match($preisanzeige) { 'netto' => 'Einzelpreis (Netto)', 'beides' => 'Einzelpreis (Brutto / Netto)', default => 'Einzelpreis (Brutto)' };
$gesamtLabel = match($preisanzeige) { 'netto' => 'Gesamt Netto', default => 'Gesamt Brutto' };

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /mealana/auftraege/liste.php');
    exit;
}

$auftrag = $service->getById($id);

if (in_array($auftrag['lieferstatus'], ['versendet', 'abgeschlossen', 'storniert'])) {
    $_SESSION['fehler'] = ['Dieser Auftrag kann nicht mehr bearbeitet werden.'];
    header('Location: /mealana/auftraege/detail.php?id=' . $id);
    exit;
}

$positionen        = $service->getPositionen($id);
$lieferAdresse     = !empty($auftrag['lieferadresse_snapshot'])    ? json_decode($auftrag['lieferadresse_snapshot'],    true) : [];
$rechnungsAdresse  = !empty($auftrag['rechnungsadresse_snapshot']) ? json_decode($auftrag['rechnungsadresse_snapshot'], true) : [];


$pageTitle        = 'Auftrag bearbeiten';
$activeModule     = 'verkauf';
$actionBarContent = <<<HTML
<button form="auftrag-form" type="submit" class="btn btn-primary btn-sm">Auftrag updaten</button>
<a href="/mealana/auftraege/liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px">
        <?php foreach ($fehler as $f): ?>
            <p style="color:var(--color-danger);margin:4px 0"><?= htmlspecialchars($f) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="auftrag-form" method="post" action="/mealana/auftraege/aktualisieren.php">
    <input type="hidden" name="id" value="<?= $auftrag['id'] ?>">

    <!-- Kopfdaten -->
    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Auftragsdaten</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:16px;padding:16px">

            <div class="form-group">
                <label class="form-label">Kunde</label>
                <input type="hidden" name="kunden_id" id="kunden-id" value="<?= htmlspecialchars($formdata['kunden_id'] ?? $auftrag['kunden_id']) ?>">
                <p style="padding:6px 0;font-weight:500"><?= htmlspecialchars($auftrag['kunden_name']) ?></p>
            </div>

            <div class="form-group">
                <label class="form-label">Zahlungsart *</label>
                <select name="zahlungsart" class="erp-select" required>
                    <option value="vorkasse" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'vorkasse'  ? 'selected' : '' ?>>Vorkasse</option>
                    <option value="paypal" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'paypal'            ? 'selected' : '' ?>>PayPal</option>
                    <option value="rechnung" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'rechnung'          ? 'selected' : '' ?>>Rechnung</option>
                    <option value="bar" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'bar'               ? 'selected' : '' ?>>Bar</option>
                    <option value="gutschein" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'gutschein'         ? 'selected' : '' ?>>Gutschein</option>
                    <option value="gemischt" <?= ($formdata['zahlungsart'] ?? $auftrag['zahlungsart']) === 'gemischt'          ? 'selected' : '' ?>>Gemischt</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Lieferart</label>
                <select id="lieferart" name="lieferart" class="erp-select" required>
                    <option value="versand" <?= ($formdata['lieferart'] ?? $auftrag['lieferart']) === 'versand'  ? 'selected' : '' ?>>Versand</option>
                    <option value="abholung" <?= ($formdata['lieferart'] ?? $auftrag['lieferart']) === 'abholung'            ? 'selected' : '' ?>>Abholung</option>
                </select>
            </div>

            <div class="form-group" id="gruppe-versandart">
                <label class="form-label">Versandart / Kosten</label>
                <select name="versandklasse_id" id="versandklasse" class="erp-select">
                    <option value="">— keine —</option>
                    <?php foreach ($versandklassen as $vk): ?>
                        <option value="<?= $vk['id'] ?>"
                            data-preis="<?= $vk['preis_brutto'] ?>"
                            <?= ($formdata['versandklasse_id'] ?? $auftrag['versandklasse_id']) == $vk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($vk['name']) ?> (<?= number_format($vk['preis_brutto'], 2, ',', '.') ?> €)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="gruppe-versandkosten">
                <label class="form-label">Versandkosten (€)</label>
                <input type="number" id="versandkosten-wert" name="versandkosten" class="erp-input" step="0.01" min="0"
                    value="<?= htmlspecialchars($formdata['versandkosten'] ?? $auftrag['versandkosten']) ?>">
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Interne Notiz</label>
                <textarea name="notiz_intern" class="erp-input" rows="2"><?= htmlspecialchars($formdata['notiz_intern'] ?? $auftrag['notiz_intern'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Versandnotiz (erscheint am Packerl)</label>
                <textarea name="notiz_versand" class="erp-input" rows="2"><?= htmlspecialchars($formdata['notiz_versand'] ?? $auftrag['notiz_versand'] ?? '') ?></textarea>
            </div>

        </div>
    </div>

    <!-- Positionen -->
    <div class="card" style="margin-bottom:12px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <span>Positionen</span>
            <button type="button" class="btn btn-secondary btn-sm" onclick="positionHinzufuegen()">+ Position</button>
        </div>
        <div id="positionen-container" style="padding:12px">
            <table class="erp-table" id="positionen-tabelle">
                <thead>
                    <tr>
                        <th style="width:40%">Artikel</th>
                        <th style="width:10%">Menge</th>
                        <th style="width:15%"><?= $epLabel ?></th>
                        <th style="width:10%">MwSt. %</th>
                        <th style="width:10%">Rabatt %</th>
                        <th style="width:12%"><?= $gesamtLabel ?></th>
                        <th style="width:3%"></th>
                    </tr>
                </thead>
                <tbody id="positionen-body">
                    <!-- Zeilen werden per JS hinzugefügt -->
                </tbody>
            </table>
            <p id="keine-positionen" style="color:var(--color-text-muted);padding:8px 0;margin:0">Noch keine Positionen — bitte oben hinzufügen.</p>
        </div>
        <div style="padding:12px 16px;border-top:1px solid var(--color-border);text-align:right">
            <span style="color:var(--color-text-muted);margin-right:16px">Netto: <strong id="summe-netto">0,00 €</strong></span>
            <span style="color:var(--color-text-muted);margin-right:16px">MwSt.: <strong id="summe-steuer">0,00 €</strong></span>
            <span style="font-size:15px">Brutto: <strong id="summe-brutto">0,00 €</strong></span>
        </div>
    </div>

    <!-- Adressen -->
    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Adressen</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;padding:16px">
            <div>
                <?php if (!empty($rechnungsAdresse)): ?>
                    <div style="font-weight:600;margin-bottom:10px;color:var(--color-text-muted);font-size:12px;text-transform:uppercase">Rechnungsadresse <span style="font-weight:400">(eingefroren)</span></div>
                    <p style="font-size:13px;line-height:1.6;color:var(--color-text)">
                        <?php if (!empty($rechnungsAdresse['firma'])) echo htmlspecialchars($rechnungsAdresse['firma']) . '<br>'; ?>
                        <?= htmlspecialchars(trim(($rechnungsAdresse['vorname'] ?? '') . ' ' . ($rechnungsAdresse['nachname'] ?? ''))) ?><br>
                        <?= htmlspecialchars(($rechnungsAdresse['strasse'] ?? '') . ' ' . ($rechnungsAdresse['hausnummer'] ?? '')) ?><br>
                        <?= htmlspecialchars(($rechnungsAdresse['plz'] ?? '') . ' ' . ($rechnungsAdresse['ort'] ?? '')) ?><br>
                        <?= htmlspecialchars($rechnungsAdresse['land'] ?? '') ?>
                        <?php if (!empty($rechnungsAdresse['zusatz'])): ?><br><em><?= htmlspecialchars($rechnungsAdresse['zusatz']) ?></em><?php endif; ?>
                    </p>
                <?php else: ?>
                    <div style="font-weight:600;margin-bottom:10px;color:var(--color-text-muted);font-size:12px;text-transform:uppercase">Rechnungsadresse <span style="font-weight:400;color:var(--color-warning)">(noch nicht gesetzt)</span></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                        <?php $rfa = $formdata['rechnungsadresse'] ?? []; $r = fn($f) => htmlspecialchars($rfa[$f] ?? ''); ?>
                        <input type="text" name="rechnungsadresse[vorname]"    id="rechnungsadresse_vorname"    class="erp-input" placeholder="Vorname"       value="<?= $r('vorname') ?>">
                        <input type="text" name="rechnungsadresse[nachname]"   id="rechnungsadresse_nachname"   class="erp-input" placeholder="Nachname"      value="<?= $r('nachname') ?>">
                        <input type="text" name="rechnungsadresse[firma]"      id="rechnungsadresse_firma"      class="erp-input" placeholder="Firma (opt.)"  style="grid-column:1/-1" value="<?= $r('firma') ?>">
                        <input type="text" name="rechnungsadresse[strasse]"    id="rechnungsadresse_strasse"    class="erp-input" placeholder="Straße"        value="<?= $r('strasse') ?>">
                        <input type="text" name="rechnungsadresse[hausnummer]" id="rechnungsadresse_hausnummer" class="erp-input" placeholder="Nr."           value="<?= $r('hausnummer') ?>">
                        <input type="text" name="rechnungsadresse[plz]"        id="rechnungsadresse_plz"        class="erp-input" placeholder="PLZ"           value="<?= $r('plz') ?>" style="width:80px">
                        <input type="text" name="rechnungsadresse[ort]"        id="rechnungsadresse_ort"        class="erp-input" placeholder="Ort"           value="<?= $r('ort') ?>">
                        <input type="text" name="rechnungsadresse[land]"       id="rechnungsadresse_land"       class="erp-input" placeholder="Land"          value="<?= $r('land') ?: 'AT' ?>" style="width:60px">
                        <input type="text" name="rechnungsadresse[zusatz]"     id="rechnungsadresse_zusatz"     class="erp-input" placeholder="Zusatz (opt.)" style="grid-column:1/-1" value="<?= $r('zusatz') ?>">
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-weight:600;margin-bottom:10px;color:var(--color-text-muted);font-size:12px;text-transform:uppercase">Lieferadresse <span style="font-weight:400">(änderbar)</span></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <?php
                    $lfa = $formdata['lieferadresse'] ?? $lieferAdresse;
                    $v = fn($f) => htmlspecialchars($lfa[$f] ?? '');
                    ?>
                    <input type="text" name="lieferadresse[vorname]"    id="lieferadresse_vorname"    class="erp-input" placeholder="Vorname"       value="<?= $v('vorname') ?>">
                    <input type="text" name="lieferadresse[nachname]"   id="lieferadresse_nachname"   class="erp-input" placeholder="Nachname"      value="<?= $v('nachname') ?>">
                    <input type="text" name="lieferadresse[firma]"      id="lieferadresse_firma"      class="erp-input" placeholder="Firma (opt.)"  style="grid-column:1/-1" value="<?= $v('firma') ?>">
                    <input type="text" name="lieferadresse[strasse]"    id="lieferadresse_strasse"    class="erp-input" placeholder="Straße"        value="<?= $v('strasse') ?>">
                    <input type="text" name="lieferadresse[hausnummer]" id="lieferadresse_hausnummer" class="erp-input" placeholder="Nr."           value="<?= $v('hausnummer') ?>">
                    <input type="text" name="lieferadresse[plz]"        id="lieferadresse_plz"        class="erp-input" placeholder="PLZ"           value="<?= $v('plz') ?>" style="width:80px">
                    <input type="text" name="lieferadresse[ort]"        id="lieferadresse_ort"        class="erp-input" placeholder="Ort"           value="<?= $v('ort') ?>">
                    <input type="text" name="lieferadresse[land]"       id="lieferadresse_land"       class="erp-input" placeholder="Land"          value="<?= $v('land') ?: 'AT' ?>" style="width:60px">
                    <input type="text" name="lieferadresse[zusatz]"     id="lieferadresse_zusatz"     class="erp-input" placeholder="Zusatz (opt.)" style="grid-column:1/-1" value="<?= $v('zusatz') ?>">
                </div>
            </div>
        </div>
    </div>

</form>

<script>
    window.POSITIONEN      = <?= json_encode($positionen) ?>;
    window.ARTIKEL_AJAX_URL = '/mealana/auftraege/artikel_ajax.php';
    window.KUNDEN_AJAX_URL  = '/mealana/auftraege/kunden_ajax.php';
    window.PREISANZEIGE     = '<?= $preisanzeige ?>';
</script>
<script src="/mealana/js/auftraege_neu.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>