<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$service = new BestellungService();
$id      = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: liste.php'); exit; }

$bestellung = $service->getById($id);
if (!$bestellung || in_array($bestellung['status'], ['erledigt', 'storniert'])) {
    header('Location: detail.php?id=' . $id);
    exit;
}

$fehler      = $_SESSION['fehler']   ?? [];
$formdata    = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$lieferanten = $service->getAlleLieferanten();
$positionen  = $service->getPositionen($id);
$data        = empty($formdata) ? $bestellung : $formdata;
$nr          = BestellungService::bestellnummer($id, $bestellung['bestelldatum']);

function fval(string $field, array $data): string {
    return htmlspecialchars((string)($data[$field] ?? ''));
}
function fsel(string $field, string $value, array $data): string {
    return ($data[$field] ?? '') == $value ? 'selected' : '';
}

$pageTitle        = 'Bestellung bearbeiten — ' . $nr;
$activeModule     = 'einkauf';
$basePath = BASE_PATH;
$actionBarContent = <<<HTML
<button form="bestellung-edit-form" type="submit" class="btn btn-primary btn-sm">Speichern</button>
<a href="{$basePath}/bestellungen/detail.php?id={$id}" class="btn btn-secondary btn-sm">Abbrechen</a>
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

<form id="bestellung-edit-form" method="post" action="<?= BASE_PATH ?>/bestellungen/aktualisieren.php">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- Kopfdaten -->
    <div class="card" style="margin-bottom:12px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferant *</label>
                <select name="lieferant_id" class="erp-select" style="width:100%" required>
                    <option value="">– wählen –</option>
                    <?php foreach ($lieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= fsel('lieferant_id', $l['id'], $data) ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Bestelldatum *</label>
                <input type="date" name="bestelldatum" class="erp-input" style="width:100%" required value="<?= fval('bestelldatum', $data) ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Zahlungsart</label>
                <select name="zahlungsart" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach (['vorkasse' => 'Vorkasse', 'rechnung' => 'Rechnung', 'lastschrift' => 'Lastschrift'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= fsel('zahlungsart', $val, $data) ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Erwartet am</label>
                <input type="date" name="erwartet_am" class="erp-input" style="width:100%" value="<?= fval('erwartet_am', $data) ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferzeit (Freitext)</label>
                <input type="text" name="lieferzeit_text" class="erp-input" style="width:100%" value="<?= fval('lieferzeit_text', $data) ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">AB-Nummer (Lieferant)</label>
                <input type="text" name="ab_nummer" class="erp-input" style="width:100%" value="<?= fval('ab_nummer', $data) ?>">
            </div>
        </div>
        <div style="margin-top:10px">
            <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Notiz</label>
            <textarea name="notiz" class="erp-input" style="width:100%;height:50px;resize:vertical"><?= fval('notiz', $data) ?></textarea>
        </div>
    </div>

    <!-- Bestehende Positionen -->
    <div class="card" style="margin-bottom:12px">
        <strong style="font-size:13px;display:block;margin-bottom:10px">Bestehende Positionen</strong>
        <?php if (empty($positionen)): ?>
            <div style="color:var(--color-text-muted);font-size:13px">Keine Positionen vorhanden.</div>
        <?php else: ?>
        <table class="erp-table">
            <thead>
                <tr><th>Artikel</th><th>Menge</th><th>Eingeg.</th><th>EK-Preis</th></tr>
            </thead>
            <tbody>
            <?php foreach ($positionen as $p):
                $offen = (float)$p['menge_bestellt'] - (float)$p['menge_eingegangen'];
            ?>
                <tr <?= $p['gestrichen'] ? 'style="opacity:.4;text-decoration:line-through"' : '' ?>>
                    <td>
                        <?= htmlspecialchars($p['artikel_name']) ?>
                        <?= $p['variante_name'] ? '<span style="font-size:11px;color:var(--color-text-muted)"> — ' . htmlspecialchars($p['variante_name']) . '</span>' : '' ?>
                    </td>
                    <td><?= (int)$p['menge_bestellt'] ?></td>
                    <td><?= (int)$p['menge_eingegangen'] ?></td>
                    <td><?= $p['ek_preis'] ? number_format((float)$p['ek_preis'], 4, ',', '.') . ' €' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Neue Positionen hinzufügen -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <strong style="font-size:13px">Neue Positionen hinzufügen</strong>
            <button type="button" class="btn btn-secondary btn-sm" onclick="positionHinzufuegen()">+ Position</button>
        </div>
        <div id="positionen-container"></div>
    </div>

</form>

<script src="<?= BASE_PATH ?>/js/bestellungen_bearbeiten.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
