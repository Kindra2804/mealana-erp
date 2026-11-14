<?php
/**
 * Massenaktion "Preise ändern" (Artikelliste → Aktion "Preise ändern", öffnet im neuen Tab).
 * Aufruf:  POST ids[]  gewählte Artikel (Väter nehmen ihre Kinder mit)
 * AJAX:    POST JSON {aktion: 'vorschau', ids, ziel, basis, art, wert, rundung}
 *          POST JSON {aktion: 'speichern', ziel, positionen: [{id, neu}], einstellung: {...}}
 * Logik: src/modules/preise/PreisMassenService.php, Oberfläche: js/preise_massen.js
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/preise/PreisMassenService.php';

$svc = new PreisMassenService();

// ── AJAX ─────────────────────────────────────────────────────────────
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    try {
        $in = json_decode(file_get_contents('php://input'), true) ?? [];
        if (($in['aktion'] ?? '') === 'vorschau') {
            $erg = $svc->vorschau((array) ($in['ids'] ?? []), (string) ($in['ziel'] ?? ''), (string) ($in['basis'] ?? ''),
                                  (string) ($in['art'] ?? ''), (float) ($in['wert'] ?? 0), (string) ($in['rundung'] ?? 'keine'));
        } elseif (($in['aktion'] ?? '') === 'speichern') {
            $erg = $svc->uebernehmen((string) ($in['ziel'] ?? ''), (array) ($in['positionen'] ?? []), (array) ($in['einstellung'] ?? []));
        } else {
            throw new RuntimeException('Unbekannte Aktion');
        }
        echo json_encode(['erfolg' => true] + $erg);
    } catch (Throwable $e) {
        echo json_encode(['erfolg' => false, 'fehler' => $e->getMessage()]);
    }
    exit;
}

// ── Seite ────────────────────────────────────────────────────────────
$roh = $_POST['ids'] ?? ($_GET['ids'] ?? []);
if (is_string($roh)) $roh = explode(',', $roh);
$ids = array_values(array_unique(array_filter(array_map('intval', (array) $roh))));

$arten     = $svc->preisArten();
$protokoll = $svc->protokoll();
$pageTitle = 'Preise ändern';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">

<h2 style="margin:0 0 4px">Preise ändern</h2>
<div style="font-size:13px;color:var(--color-text-muted);margin-bottom:var(--space-md)">
    <?= count($ids) ?> Artikel aus der Artikelliste gewählt · gewählte Vater-Artikel nehmen ihre Kinder mit ·
    zuerst kommt eine Vorschau, gespeichert wird erst mit „Übernehmen“.
</div>

<?php if (!$ids): ?>
    <div class="card" style="padding:var(--space-md)">Keine Artikel gewählt. Bitte in der Artikelliste Artikel anhaken und die Aktion „Preise ändern“ wählen.</div>
<?php else: ?>

<div class="card" style="padding:var(--space-md);display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end">
    <label style="font-size:12px">Welcher Preis wird geändert?
        <select id="pm-ziel" class="erp-select" style="width:100%">
            <?php foreach ($arten as $k => $name): ?><option value="<?= $k ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:12px">Gerechnet von (Basis)
        <select id="pm-basis" class="erp-select" style="width:100%">
            <?php foreach ($arten as $k => $name): ?><option value="<?= $k ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
        </select>
    </label>
    <label style="font-size:12px">Änderung
        <select id="pm-art" class="erp-select" style="width:100%">
            <option value="prozent">+ / − Prozent</option>
            <option value="euro">+ / − Euro</option>
            <option value="fest">fester neuer Preis</option>
        </select>
    </label>
    <label style="font-size:12px"><span id="pm-wert-label">Wert in %</span> <span style="color:var(--color-text-muted)">(minus = billiger)</span>
        <input type="text" id="pm-wert" class="erp-input" style="width:100%" inputmode="decimal" placeholder="z.B. 5 oder -30">
    </label>
    <label style="font-size:12px">Rundung
        <select id="pm-rundung" class="erp-select" style="width:100%">
            <?php foreach (PreisMassenService::RUNDUNGEN as $k => $name): ?><option value="<?= $k ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
        </select>
    </label>
    <div><button type="button" class="btn btn-primary" id="pm-vorschau" style="width:100%">Vorschau</button></div>
</div>
<div id="pm-fehler" style="color:var(--color-danger);font-size:13px;min-height:18px;margin:6px 0"></div>

<div id="pm-ergebnis" style="display:none">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
        <div id="pm-zusammenfassung" style="font-size:13px;flex:1"></div>
        <label style="font-size:13px" id="pm-abw-alle-wrap"><input type="checkbox" id="pm-abw-alle"> auch alle mit <span class="pm-ausruf">!</span> ändern</label>
        <button type="button" class="btn btn-primary" id="pm-speichern">Übernehmen</button>
    </div>
    <table class="erp-table" style="width:100%">
        <thead><tr>
            <th style="width:28px"><input type="checkbox" id="pm-alle" checked title="alle an/aus"></th>
            <th>Artikel</th><th style="text-align:right">Basis</th><th style="text-align:right">alt</th>
            <th style="text-align:right;width:120px">neu</th><th style="text-align:right">Änderung</th><th>Hinweis</th>
        </tr></thead>
        <tbody id="pm-zeilen"></tbody>
    </table>
</div>

<?php endif; ?>

<?php if ($protokoll): ?>
<h3 style="margin:var(--space-lg) 0 6px;font-size:14px">Letzte Preisänderungen</h3>
<table class="erp-table" style="width:100%;font-size:12px">
    <thead><tr><th>Wann</th><th>Wer</th><th>Preis</th><th>Basis</th><th>Änderung</th><th>Rundung</th><th style="text-align:right">Artikel</th></tr></thead>
    <tbody>
    <?php foreach ($protokoll as $p):
        $wert = $p['wert'] ?? '';
        $aend = match ($p['art'] ?? '') { 'prozent' => ($wert > 0 ? '+' : '') . $wert . ' %', 'euro' => ($wert > 0 ? '+' : '') . $wert . ' €', 'fest' => '= ' . $wert . ' €', default => '' }; ?>
        <tr>
            <td><?= date('d.m.Y H:i', strtotime($p['wann'])) ?></td>
            <td><?= htmlspecialchars((string) $p['wer']) ?></td>
            <td><?= htmlspecialchars((string) ($p['ziel'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string) ($p['basis'] ?? '')) ?></td>
            <td><?= htmlspecialchars($aend) ?></td>
            <td><?= htmlspecialchars(PreisMassenService::RUNDUNGEN[$p['rundung'] ?? ''] ?? '') ?></td>
            <td style="text-align:right"><?= (int) ($p['anzahl'] ?? 0) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div style="font-size:11px;color:var(--color-text-muted);margin-top:4px">Jede einzelne Änderung (alt → neu je Artikel) steht im Aktivitäten-Protokoll unter „preis.massen“.</div>
<?php endif; ?>

</div>
<style>
    .pm-ausruf { display:inline-block;width:16px;height:16px;line-height:16px;border-radius:50%;background:#2563eb;color:#fff;font-weight:700;font-size:11px;text-align:center }
    .pm-kind td:nth-child(2) { padding-left:28px }
    .pm-neu { width:100px;text-align:right }
    .pm-neu.pm-hand { background:#fff7d6 }
</style>
<script>window.PM_IDS = <?= json_encode($ids) ?>;</script>
<script src="<?= BASE_PATH ?>/js/preise_massen.js"></script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
