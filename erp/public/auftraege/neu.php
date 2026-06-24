<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
require_once __DIR__ . '/../../src/modules/kunden/KundenService.php';
$db = Database::getInstance();
$versandklassen = $db->query("SELECT id, name, preis_brutto FROM versandklassen ORDER BY sortierung")->fetchAll();

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
                        <th style="width:15%">Einzelpreis (Netto)</th>
                        <th style="width:10%">MwSt. %</th>
                        <th style="width:10%">Rabatt %</th>
                        <th style="width:12%">Gesamt Netto</th>
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

</form>

<script>
    window.ARTIKEL_AJAX_URL = '/mealana/auftraege/artikel_ajax.php';
    window.KUNDEN_AJAX_URL = '/mealana/auftraege/kunden_ajax.php';
</script>
<script src="/mealana/js/auftraege_neu.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>