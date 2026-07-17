<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/modules/buchhaltung/BuchhaltungExportService.php';

$db     = Database::getInstance();
$fehler = $_SESSION['fehler'] ?? null;
$erfolg = $_SESSION['erfolg'] ?? null;
unset($_SESSION['fehler'], $_SESSION['erfolg']);

$einstellungen = $db->query("
    SELECT schluessel, wert FROM system_einstellungen
    WHERE schluessel IN ('datev_berater_nr', 'datev_mandant_nr', 'datev_wj_beginn', 'datev_sachkontenlaenge')
")->fetchAll(PDO::FETCH_KEY_PAIR);

$von = $_GET['von'] ?? date('Y-m-01');
$bis = $_GET['bis'] ?? date('Y-m-t');

// Vorschau: Buchungen + Hinweise für den gewählten Zeitraum, direkt auf der Seite anzeigen
$service   = new BuchhaltungExportService();
$vorschau  = $service->sammleZeitraum($von, $bis);

$pageTitle    = 'DATEV/CSV-Export';
$activeModule = 'buchhaltung';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
<div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px;padding:10px 16px;color:var(--color-danger)">
    <?= htmlspecialchars(is_array($fehler) ? implode(', ', $fehler) : $fehler) ?>
</div>
<?php endif; ?>
<?php if ($erfolg): ?>
<div class="card" style="border-left:3px solid var(--color-success);margin-bottom:12px;padding:10px 16px;color:var(--color-success)">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">DATEV-Einstellungen (einmalig von deinem Steuerberater erfragen)</div>
    <form method="post" action="<?= BASE_PATH ?>/buchhaltung/export_einstellungen_speichern.php" style="padding:0 16px 14px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin-bottom:10px">
            <div class="form-group">
                <label class="form-label">Berater-Nr.</label>
                <input type="text" name="datev_berater_nr" class="erp-input" style="width:100%" value="<?= htmlspecialchars($einstellungen['datev_berater_nr'] ?? '') ?>" placeholder="z.B. 1001">
            </div>
            <div class="form-group">
                <label class="form-label">Mandanten-Nr.</label>
                <input type="text" name="datev_mandant_nr" class="erp-input" style="width:100%" value="<?= htmlspecialchars($einstellungen['datev_mandant_nr'] ?? '') ?>" placeholder="z.B. MEA25">
            </div>
            <div class="form-group">
                <label class="form-label">Wirtschaftsjahr-Beginn</label>
                <input type="date" name="datev_wj_beginn" class="erp-input" style="width:100%" value="<?= htmlspecialchars($einstellungen['datev_wj_beginn'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Sachkontenlänge</label>
                <input type="number" name="datev_sachkontenlaenge" class="erp-input" style="width:100%" value="<?= htmlspecialchars($einstellungen['datev_sachkontenlaenge'] ?? '4') ?>" min="4" max="8">
            </div>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Einstellungen speichern</button>
        <?php if (empty($einstellungen['datev_berater_nr']) || empty($einstellungen['datev_mandant_nr'])): ?>
        <span style="font-size:12px;color:#c2410c;margin-left:8px">⚠ Ohne Berater-/Mandanten-Nr. ist der DATEV-Export nicht vollständig — CSV-Export funktioniert trotzdem.</span>
        <?php endif; ?>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-header">Zeitraum</div>
    <form method="get" style="padding:0 16px 14px;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div class="form-group">
            <label class="form-label">Von</label>
            <input type="date" name="von" id="f-von" class="erp-input" value="<?= htmlspecialchars($von) ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Bis</label>
            <input type="date" name="bis" id="f-bis" class="erp-input" value="<?= htmlspecialchars($bis) ?>">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Anzeigen</button>
        <div style="display:flex;gap:6px;margin-left:12px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('monat')">Dieser Monat</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('quartal')">Dieses Quartal</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="zeitraumSetzen('jahr')">Dieses Jahr</button>
        </div>
    </form>
    <div style="padding:0 16px 14px;display:flex;gap:8px">
        <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/buchhaltung/export_datei.php?format=csv&von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>">⬇ CSV herunterladen</a>
        <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/buchhaltung/export_datei.php?format=datev&von=<?= urlencode($von) ?>&bis=<?= urlencode($bis) ?>">⬇ DATEV herunterladen</a>
    </div>
</div>

<?php if (!empty($vorschau['hinweise'])): ?>
<div class="card" style="border-left:3px solid #f59e0b;margin-bottom:16px;padding:12px 16px">
    <div style="font-weight:700;margin-bottom:6px">⚠ <?= count($vorschau['hinweise']) ?> Position(en) brauchen manuelle Prüfung (nicht im Export enthalten):</div>
    <ul style="margin:0;padding-left:20px;font-size:12px;color:var(--color-text-muted)">
        <?php foreach ($vorschau['hinweise'] as $h): ?>
        <li><?= htmlspecialchars($h) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Vorschau — <?= count($vorschau['buchungen']) ?> Buchungszeilen</div>
    <table class="erp-table">
        <thead>
            <tr>
                <th>Datum</th><th>Beleg</th><th>Konto</th><th>Gegenkonto</th>
                <th style="text-align:right">Betrag</th><th style="text-align:center">S/H</th><th>Text</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (array_slice($vorschau['buchungen'], 0, 200) as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['datum']) ?></td>
                <td><?= htmlspecialchars($b['belegnr']) ?></td>
                <td><code style="font-size:12px"><?= htmlspecialchars($b['konto']) ?></code></td>
                <td><code style="font-size:12px"><?= htmlspecialchars($b['gegenkonto']) ?></code></td>
                <td style="text-align:right"><?= number_format($b['betrag'], 2, ',', '.') ?></td>
                <td style="text-align:center"><?= htmlspecialchars($b['soll_haben']) ?></td>
                <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($b['text']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($vorschau['buchungen'])): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:20px">Keine Buchungen in diesem Zeitraum</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php if (count($vorschau['buchungen']) > 200): ?>
    <div style="padding:10px 16px;font-size:12px;color:var(--color-text-muted)">... und <?= count($vorschau['buchungen']) - 200 ?> weitere (nur Vorschau, im Export enthalten)</div>
    <?php endif; ?>
</div>

<script src="<?= BASE_PATH ?>/js/buchhaltung_export.js"></script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
