<?php
/**
 * Konfigurator-Optionen (Achsen + Werte + Bedingungen) von einem Layout auf andere übertragen.
 * Logik: src/modules/konfigurator/KonfigOptionenService.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/konfigurator/KonfigOptionenService.php';

$service  = new KonfigOptionenService();
$quelleId = (int) ($_GET['quelle'] ?? $_POST['quelle'] ?? 0);
$ergebnis = null;
$fehler   = [];
$gewaehlt = array_map('intval', $_POST['ziel'] ?? []);
$ausgefuehrt = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ausgefuehrt = ($_POST['aktion'] ?? '') === 'ausfuehren';
        $ergebnis = $service->uebertragen($quelleId, $gewaehlt, $ausgefuehrt);
    } catch (Throwable $e) {
        $fehler[] = $e->getMessage();
        $ausgefuehrt = false;
    }
}

$layouts = $service->layouts();
$quelle  = null;
foreach ($layouts as $l) if ((int) $l['id'] === $quelleId) $quelle = $l;
if (!$quelle) { http_response_code(404); exit('Vorlage ist kein konfigurierbarer Artikel.'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $gewaehlt = array_map(fn($l) => (int) $l['id'], array_filter($layouts, fn($l) => (int) $l['id'] !== $quelleId));
$nameById = array_column($layouts, null, 'id');

$pageTitle    = 'Optionen übertragen';
$activeModule = 'artikel';
$basePath     = BASE_PATH;
$actionBarContent = '<a href="' . $basePath . '/artikel/detail.php?id=' . $quelleId . '" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($quelle['artikelnummer']) . '</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if ($fehler): ?>
    <div class="card" style="border-left:4px solid var(--color-danger);margin-bottom:12px"><ul style="margin:0;padding-left:18px;color:var(--color-danger)"><?php foreach ($fehler as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($ergebnis !== null): ?>
    <div class="card" style="border-left:4px solid <?= $ausgefuehrt ? 'var(--color-success)' : 'var(--color-primary)' ?>;margin-bottom:12px">
        <h3 style="margin:0 0 8px"><?= $ausgefuehrt ? '✓ Übertragen' : 'Probelauf — noch nichts geändert' ?></h3>
        <table class="erp-table" style="font-size:13px">
            <thead><tr><th>Layout</th><th style="text-align:right">Werte neu</th><th style="text-align:right">geändert</th><th style="text-align:right">entfernt</th><th style="text-align:right">Achsen neu / entfernt</th><th>Bleiben (in Aufträgen verwendet)</th></tr></thead>
            <tbody>
            <?php foreach ($ergebnis as $zid => $z): ?>
                <tr><td><?= htmlspecialchars($nameById[$zid]['artikelnummer'] . ' ' . $nameById[$zid]['name']) ?></td>
                    <td style="text-align:right"><?= $z['werte_neu'] ?></td><td style="text-align:right"><?= $z['werte_geaendert'] ?></td>
                    <td style="text-align:right"><?= $z['werte_weg'] ?></td><td style="text-align:right"><?= $z['achsen_neu'] ?> / <?= $z['achsen_weg'] ?></td>
                    <td style="font-size:12px;color:#c2410c"><?= htmlspecialchars(implode(', ', $z['behalten'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($ausgefuehrt): ?><p style="margin:8px 0 0;font-size:13px">Die Layouts werden beim nächsten Shop-Sync übertragen.</p><?php endif; ?>
    </div>
<?php endif; ?>

<form method="POST" class="card">
    <input type="hidden" name="quelle" value="<?= $quelleId ?>">
    <h3 style="margin:0 0 6px">Vorlage: <?= htmlspecialchars($quelle['artikelnummer'] . ' ' . $quelle['name']) ?></h3>
    <p style="margin:0 0 12px;font-size:13px;color:var(--color-text-muted)">
        <?= (int) $quelle['achsen'] ?> Achsen, <?= (int) $quelle['werte'] ?> Werte. Die gewählten Layouts bekommen genau diese Achsen, Werte, Reihenfolge, Aufpreise und Bedingungen.
        Gleichnamige Werte werden aktualisiert, fehlende angelegt, überzählige entfernt (außer sie stehen schon in Aufträgen). Der Grundpreis je Layout bleibt.</p>
    <div style="margin-bottom:8px;font-size:13px"><a href="#" onclick="document.querySelectorAll('.ou-ziel').forEach(c=>c.checked=true);return false">alle</a> · <a href="#" onclick="document.querySelectorAll('.ou-ziel').forEach(c=>c.checked=false);return false">keine</a></div>
    <div style="columns:2;column-gap:24px;font-size:13px;margin-bottom:12px">
        <?php foreach ($layouts as $l): if ((int) $l['id'] === $quelleId) continue; ?>
            <label style="display:block;break-inside:avoid;padding:2px 0;<?= $l['aktiv'] ? '' : 'opacity:.6' ?>">
                <input type="checkbox" class="ou-ziel" name="ziel[]" value="<?= (int) $l['id'] ?>" <?= in_array((int) $l['id'], $gewaehlt, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($l['artikelnummer'] . ' ' . $l['name']) ?> <span style="color:var(--color-text-muted)">(<?= (int) $l['werte'] ?> Werte)</span>
            </label>
        <?php endforeach; ?>
    </div>
    <button name="aktion" value="probelauf" class="btn btn-secondary btn-sm">Probelauf</button>
    <?php if ($ergebnis !== null && !$ausgefuehrt): ?>
        <button name="aktion" value="ausfuehren" class="btn btn-primary btn-sm" onclick="return confirm('Optionen jetzt auf die gewählten Layouts übertragen?')">Jetzt übertragen</button>
    <?php endif; ?>
</form>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
