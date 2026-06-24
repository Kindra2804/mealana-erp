<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service     = new BestellungService();
$lieferanten = $service->getAlleLieferanten();
$fehler      = $_SESSION['fehler']   ?? [];
$formdata    = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$pageTitle        = 'Neue Bestellung';
$activeModule     = 'einkauf';
$actionBarContent = <<<HTML
<button form="bestellung-form" type="submit" class="btn btn-primary btn-sm">Speichern</button>
<a href="/mealana/bestellungen/liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
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

<form id="bestellung-form" method="post" action="/mealana/bestellungen/speichern.php">

    <div class="card" style="margin-bottom:12px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferant *</label>
                <select name="lieferant_id" id="lieferant_id" class="erp-select" style="width:100%" required onchange="ladeReserviert(this.value)">
                    <option value="">– Lieferant wählen –</option>
                    <?php foreach ($lieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($formdata['lieferant_id'] ?? '') == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Bestelldatum *</label>
                <input type="date" name="bestelldatum" class="erp-input" style="width:100%" required value="<?= htmlspecialchars($formdata['bestelldatum'] ?? date('Y-m-d')) ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Zahlungsart</label>
                <select name="zahlungsart" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach (['vorkasse' => 'Vorkasse', 'rechnung' => 'Rechnung', 'lastschrift' => 'Lastschrift'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($formdata['zahlungsart'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Erwartet am</label>
                <input type="date" name="erwartet_am" class="erp-input" style="width:100%" value="<?= htmlspecialchars($formdata['erwartet_am'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferzeit (Freitext)</label>
                <input type="text" name="lieferzeit_text" class="erp-input" style="width:100%" placeholder="z.B. ab KW38" value="<?= htmlspecialchars($formdata['lieferzeit_text'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">AB-Nummer (Lieferant)</label>
                <input type="text" name="ab_nummer" class="erp-input" style="width:100%" value="<?= htmlspecialchars($formdata['ab_nummer'] ?? '') ?>">
            </div>
        </div>
        <div style="margin-top:10px">
            <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Notiz</label>
            <textarea name="notiz" class="erp-input" style="width:100%;height:50px;resize:vertical"><?= htmlspecialchars($formdata['notiz'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Reserviert Infobox -->
    <div id="reserviert-box" style="display:none;margin-bottom:12px"></div>

    <!-- Positionen -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <strong style="font-size:13px">Positionen</strong>
            <button type="button" class="btn btn-secondary btn-sm" onclick="positionHinzufuegen()">+ Position</button>
        </div>

        <div id="positionen-container">
            <!-- wird per JS befüllt -->
        </div>

        <div style="margin-top:12px;text-align:right;font-size:13px;color:var(--color-text-muted)">
            Gesamt EK (netto): <strong id="gesamt-ek" style="color:var(--color-nav)">0,00 €</strong>
        </div>
    </div>

</form>

<script>
    window.BESTELLUNGEN_SAVED_POS = <?= json_encode($formdata['positionen'] ?? []) ?>;
</script>
<script src="/mealana/js/bestellungen_neu.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>