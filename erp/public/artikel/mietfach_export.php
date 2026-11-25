<?php
/**
 * Massenaktion "Für Mietfach exportieren" (Artikelliste → Aktion, öffnet im neuen Tab).
 * Aufruf:  POST ids[]               gewählte Artikel (Väter → ihre aktiven Kinder)
 *          POST ids[] + laden=1     Datei (.tar) herunterladen
 * Logik: src/modules/artikel/MietfachExport.php · Gegenstück: partner/mietfach_import.php
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/artikel/MietfachExport.php';

$roh = $_POST['ids'] ?? ($_GET['ids'] ?? []);
if (is_string($roh)) $roh = explode(',', $roh);
$ids = array_values(array_unique(array_filter(array_map('intval', (array)$roh))));
$svc = new MietfachExport();

if (!empty($_POST['laden']) && $ids) {
    try {
        $p = $svc->paket($ids);
        header('Content-Type: application/x-tar');
        header('Content-Disposition: attachment; filename="' . $p['dateiname'] . '"');
        header('Content-Length: ' . filesize($p['datei']));
        readfile($p['datei']);
        @unlink($p['datei']);
        exit;
    } catch (Throwable $e) {
        $fehler = $e->getMessage();
    }
}

$erg   = $svc->sammle($ids);
$firma = Lizenz::firmendaten();
$pageTitle = 'Für Mietfach exportieren';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">

<h2 style="margin:0 0 4px">Für Mietfach exportieren</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    Für Betriebe, bei denen <strong><?= htmlspecialchars($firma['firmenname'] ?: 'deine Firma') ?></strong> ein Mietfach hat und die ebenfalls NAHTLOS verwenden.
    Die Datei dort unter <em>Partner → Partner öffnen → Artikel → „Aus NAHTLOS-Datei übernehmen“</em> einlesen –
    Name, EAN, Verkaufspreis, Steuersatz, Kurztext und ein Bild je Artikel kommen mit, <strong>Bestand und Einkaufspreise nicht</strong>.
    Varianten werden zu einzelnen Artikeln.
</div>

<?php if (!empty($fehler)): ?>
    <div class="card" style="padding:var(--space-md);border-left:4px solid #dc2626;margin-bottom:12px"><?= htmlspecialchars($fehler) ?></div>
<?php endif; ?>

<?php if (!$ids): ?>
    <div class="card" style="padding:var(--space-md)">Keine Artikel gewählt. Bitte in der Artikelliste Artikel anhaken und die Aktion „📦 Für Mietfach exportieren“ wählen.</div>
<?php else: ?>

    <?php foreach ($erg['hinweise'] as $h): ?>
        <div style="display:flex;gap:8px;align-items:center;font-size:13px;margin-bottom:6px">
            <span class="mf-ausruf">!</span><span><?= htmlspecialchars($h) ?></span>
        </div>
    <?php endforeach; ?>

    <?php if ($erg['positionen']): ?>
    <form method="post" style="margin:12px 0">
        <?php foreach ($ids as $id): ?><input type="hidden" name="ids[]" value="<?= $id ?>"><?php endforeach; ?>
        <input type="hidden" name="laden" value="1">
        <button class="btn btn-primary">⬇ Datei herunterladen (<?= count($erg['positionen']) ?> Artikel)</button>
    </form>

    <table class="erp-table">
        <thead><tr><th style="width:60px">BILD</th><th style="width:150px">NR.</th><th>BEZEICHNUNG</th><th style="width:130px">EAN</th>
            <th style="width:90px;text-align:right">PREIS</th><th style="width:60px">MWST</th></tr></thead>
        <tbody>
        <?php foreach ($erg['positionen'] as $p): ?>
            <tr>
                <td><?= $p['bild_datei'] ? '🖼' : '<span style="color:var(--color-text-muted)">–</span>' ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['nummer']) ?></td>
                <td><?= htmlspecialchars($p['name']) ?>
                    <?php if ($p['vater_nummer']): ?><div style="font-size:11px;color:var(--color-text-muted)">Variante von <?= htmlspecialchars($p['vater_name']) ?></div><?php endif; ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($p['ean'] ?? '') ?: '<span class="mf-ausruf" title="ohne EAN">!</span>' ?></td>
                <td style="text-align:right"><?= $p['brutto_vk'] ? '€ ' . number_format($p['brutto_vk'], 2, ',', '.') : '<span class="mf-ausruf" title="ohne Preis">!</span>' ?></td>
                <td><?= rtrim(rtrim(number_format($p['steuersatz'], 1, ',', ''), '0'), ',') ?> %</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
<?php endif; ?>

</div>
<style>
    .mf-ausruf { display:inline-block;width:16px;height:16px;line-height:16px;border-radius:50%;background:#2563eb;color:#fff;font-weight:700;font-size:11px;text-align:center }
</style>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
