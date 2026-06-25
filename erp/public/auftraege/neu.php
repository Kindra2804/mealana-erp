<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';
$db = Database::getInstance();
$versandklassen = $db->query("SELECT id, name, preis_brutto FROM versandklassen ORDER BY sortierung")->fetchAll();
$preisanzeige   = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'preisanzeige_auftrag'")->fetchColumn() ?: 'brutto';
$epLabel     = match($preisanzeige) { 'netto' => 'Einzelpreis (Netto)', 'beides' => 'Einzelpreis (Brutto / Netto)', default => 'Einzelpreis (Brutto)' };
$gesamtLabel = match($preisanzeige) { 'netto' => 'Gesamt Netto', default => 'Gesamt Brutto' };

$fehler   = $_SESSION['fehler']   ?? [];
$formdata = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$pageTitle        = 'Neuer Auftrag';
$activeModule     = 'verkauf';
$actionBarContent = <<<HTML
<button form="auftrag-form" type="submit" class="btn btn-primary btn-sm">Auftrag anlegen</button>
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

<form id="auftrag-form" method="post" action="/mealana/auftraege/speichern.php">

    <!-- Kopfdaten -->
    <div class="card" style="margin-bottom:12px">
        <div class="card-header">Auftragsdaten</div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:16px;padding:16px">

            <div class="form-group">
                <label class="form-label">Kunde</label>
                <input type="hidden" name="kunden_id" id="kunden-id" value="<?= htmlspecialchars($formdata['kunden_id'] ?? '') ?>">
                <input type="text" class="erp-input" id="kunden-suche" placeholder="Name oder E-Mail suchen…"
                    value="<?= htmlspecialchars($formdata['kunden_name'] ?? '') ?>" autocomplete="off">
                <div id="kunden-dropdown" style="position:absolute;z-index:100;background:#fff;border:1px solid var(--color-border);border-radius:4px;width:320px;display:none"></div>
                <small style="color:var(--color-text-muted)">Leer lassen = Laufkunde</small>
            </div>

            <div class="form-group">
                <label class="form-label">Zahlungsart *</label>
                <select name="zahlungsart" class="erp-select" required>
                    <option value="vorkasse" <?= ($formdata['zahlungsart'] ?? 'vorkasse') === 'vorkasse'  ? 'selected' : '' ?>>Vorkasse</option>
                    <option value="paypal" <?= ($formdata['zahlungsart'] ?? '') === 'paypal'            ? 'selected' : '' ?>>PayPal</option>
                    <option value="rechnung" <?= ($formdata['zahlungsart'] ?? '') === 'rechnung'          ? 'selected' : '' ?>>Rechnung</option>
                    <option value="bar" <?= ($formdata['zahlungsart'] ?? '') === 'bar'               ? 'selected' : '' ?>>Bar</option>
                    <option value="gutschein" <?= ($formdata['zahlungsart'] ?? '') === 'gutschein'         ? 'selected' : '' ?>>Gutschein</option>
                    <option value="gemischt" <?= ($formdata['zahlungsart'] ?? '') === 'gemischt'          ? 'selected' : '' ?>>Gemischt</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Lieferart</label>
                <select id="lieferart" name="lieferart" class="erp-select" required>
                    <option value="versand" <?= ($formdata['lieferart'] ?? 'versand') === 'versand'  ? 'selected' : '' ?>>Versand</option>
                    <option value="abholung" <?= ($formdata['lieferart'] ?? '') === 'abholung'            ? 'selected' : '' ?>>Abholung</option>
                </select>
            </div>

            <div class="form-group" id="gruppe-versandart">
                <label class="form-label">Versandart / Kosten</label>
                <select name="versandklasse_id" id="versandklasse" class="erp-select">
                    <option value="">— keine —</option>
                    <?php foreach ($versandklassen as $vk): ?>
                        <option value="<?= $vk['id'] ?>"
                            data-preis="<?= $vk['preis_brutto'] ?>"
                            <?= ($formdata['versandklasse_id'] ?? '') == $vk['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($vk['name']) ?> (<?= number_format($vk['preis_brutto'], 2, ',', '.') ?> €)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="gruppe-versandkosten">
                <label class="form-label">Versandkosten (€)</label>
                <input type="number" id="versandkosten-wert" name="versandkosten" class="erp-input" step="0.01" min="0"
                    value="<?= htmlspecialchars($formdata['versandkosten'] ?? '0.00') ?>">
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Interne Notiz</label>
                <textarea name="notiz_intern" class="erp-input" rows="2"><?= htmlspecialchars($formdata['notiz_intern'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Versandnotiz (erscheint am Packerl)</label>
                <textarea name="notiz_versand" class="erp-input" rows="2"><?= htmlspecialchars($formdata['notiz_versand'] ?? '') ?></textarea>
            </div>

            <div class="form-group" style="grid-column:1/-1">
                <label class="form-label">Kontakt bei Abholung
                    <span style="font-weight:normal; color:#888; font-size:0.88em;">(z.B. "WhatsApp wenn da" oder Rufnummer — erscheint auf Abholzettel + Lieferschein)</span>
                </label>
                <input type="text" name="kontakt_notiz" class="erp-input"
                       placeholder="z.B. WhatsApp +43 664 …"
                       value="<?= htmlspecialchars($formdata['kontakt_notiz'] ?? '') ?>">
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
                <div style="font-weight:600;margin-bottom:10px;color:var(--color-text-muted);font-size:12px;text-transform:uppercase">Rechnungsadresse</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <input type="text" name="rechnungsadresse[vorname]"    id="rechnungsadresse_vorname"    class="erp-input" placeholder="Vorname"    value="<?= htmlspecialchars($formdata['rechnungsadresse']['vorname']    ?? '') ?>">
                    <input type="text" name="rechnungsadresse[nachname]"   id="rechnungsadresse_nachname"   class="erp-input" placeholder="Nachname"   value="<?= htmlspecialchars($formdata['rechnungsadresse']['nachname']   ?? '') ?>">
                    <input type="text" name="rechnungsadresse[firma]"      id="rechnungsadresse_firma"      class="erp-input" placeholder="Firma (opt.)" style="grid-column:1/-1" value="<?= htmlspecialchars($formdata['rechnungsadresse']['firma']      ?? '') ?>">
                    <input type="text" name="rechnungsadresse[strasse]"    id="rechnungsadresse_strasse"    class="erp-input" placeholder="Straße"     value="<?= htmlspecialchars($formdata['rechnungsadresse']['strasse']    ?? '') ?>">
                    <input type="text" name="rechnungsadresse[hausnummer]" id="rechnungsadresse_hausnummer" class="erp-input" placeholder="Nr."        value="<?= htmlspecialchars($formdata['rechnungsadresse']['hausnummer'] ?? '') ?>">
                    <input type="text" name="rechnungsadresse[plz]"        id="rechnungsadresse_plz"        class="erp-input" placeholder="PLZ"        value="<?= htmlspecialchars($formdata['rechnungsadresse']['plz']        ?? '') ?>" style="width:80px">
                    <input type="text" name="rechnungsadresse[ort]"        id="rechnungsadresse_ort"        class="erp-input" placeholder="Ort"        value="<?= htmlspecialchars($formdata['rechnungsadresse']['ort']        ?? '') ?>">
                    <input type="text" name="rechnungsadresse[land]"       id="rechnungsadresse_land"       class="erp-input" placeholder="Land"       value="<?= htmlspecialchars($formdata['rechnungsadresse']['land']       ?? 'AT') ?>" style="width:60px">
                    <input type="text" name="rechnungsadresse[zusatz]"     id="rechnungsadresse_zusatz"     class="erp-input" placeholder="Zusatz (opt.)" style="grid-column:1/-1" value="<?= htmlspecialchars($formdata['rechnungsadresse']['zusatz']     ?? '') ?>">
                </div>
            </div>
            <div>
                <div style="font-weight:600;margin-bottom:10px;color:var(--color-text-muted);font-size:12px;text-transform:uppercase">Lieferadresse <span style="font-weight:400">(leer = wie Rechnungsadresse)</span></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    <input type="text" name="lieferadresse[vorname]"    id="lieferadresse_vorname"    class="erp-input" placeholder="Vorname"    value="<?= htmlspecialchars($formdata['lieferadresse']['vorname']    ?? '') ?>">
                    <input type="text" name="lieferadresse[nachname]"   id="lieferadresse_nachname"   class="erp-input" placeholder="Nachname"   value="<?= htmlspecialchars($formdata['lieferadresse']['nachname']   ?? '') ?>">
                    <input type="text" name="lieferadresse[firma]"      id="lieferadresse_firma"      class="erp-input" placeholder="Firma (opt.)" style="grid-column:1/-1" value="<?= htmlspecialchars($formdata['lieferadresse']['firma']      ?? '') ?>">
                    <input type="text" name="lieferadresse[strasse]"    id="lieferadresse_strasse"    class="erp-input" placeholder="Straße"     value="<?= htmlspecialchars($formdata['lieferadresse']['strasse']    ?? '') ?>">
                    <input type="text" name="lieferadresse[hausnummer]" id="lieferadresse_hausnummer" class="erp-input" placeholder="Nr."        value="<?= htmlspecialchars($formdata['lieferadresse']['hausnummer'] ?? '') ?>">
                    <input type="text" name="lieferadresse[plz]"        id="lieferadresse_plz"        class="erp-input" placeholder="PLZ"        value="<?= htmlspecialchars($formdata['lieferadresse']['plz']        ?? '') ?>" style="width:80px">
                    <input type="text" name="lieferadresse[ort]"        id="lieferadresse_ort"        class="erp-input" placeholder="Ort"        value="<?= htmlspecialchars($formdata['lieferadresse']['ort']        ?? '') ?>">
                    <input type="text" name="lieferadresse[land]"       id="lieferadresse_land"       class="erp-input" placeholder="Land"       value="<?= htmlspecialchars($formdata['lieferadresse']['land']       ?? '') ?>" style="width:60px">
                    <input type="text" name="lieferadresse[zusatz]"     id="lieferadresse_zusatz"     class="erp-input" placeholder="Zusatz (opt.)" style="grid-column:1/-1" value="<?= htmlspecialchars($formdata['lieferadresse']['zusatz']     ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

</form>

<script>
    window.ARTIKEL_AJAX_URL = '/mealana/auftraege/artikel_ajax.php';
    window.KUNDEN_AJAX_URL  = '/mealana/auftraege/kunden_ajax.php';
    window.PREISANZEIGE     = '<?= $preisanzeige ?>';
</script>
<script src="/mealana/js/auftraege_neu.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>