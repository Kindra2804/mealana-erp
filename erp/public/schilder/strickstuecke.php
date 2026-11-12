<?php
/**
 * Anhänger ohne Artikel (Babsis Strickstücke): eingeben, wiederverwenden, drucken (Vorlage "anhaenger").
 * AnhaengerFreitextService, Tabelle anhaenger_freitext (Migration 235).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/schilder/AnhaengerFreitextService.php';
require_once __DIR__ . '/../../src/modules/schilder/PreisschildService.php';
require_once __DIR__ . '/../../src/modules/artikel/ArtikelService.php';
require_once __DIR__ . '/../../src/core/Logger.php';

$svc = new AnhaengerFreitextService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $aktion = $_POST['aktion'] ?? '';
        if ($aktion === 'speichern') {
            $id = $svc->speichern($_POST);
            Logger::log('schilder.anhaenger_freitext', 'anhaenger_freitext', $id, ['titel' => $_POST['titel'] ?? '']);
            $_SESSION['erfolg'] = 'Anhänger „' . trim($_POST['titel']) . '“ gespeichert.';
        } elseif ($aktion === 'ausblenden' || $aktion === 'einblenden') {
            $svc->setzeAktiv((int) $_POST['id'], $aktion === 'einblenden');
        }
    } catch (Throwable $e) {
        $_SESSION['fehler'] = $e->getMessage();
    }
    header('Location: strickstuecke.php' . (!empty($_GET['alle']) ? '?alle=1' : ''));
    exit;
}

$alle   = !empty($_GET['alle']);
$liste  = $svc->liste($alle);
$pflege = $svc->pflegeAuswahl();
$erfolg = $_SESSION['erfolg'] ?? null; $fehler = $_SESSION['fehler'] ?? null;
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$pageTitle    = 'Anhänger ohne Artikel';
$activeModule = 'artikel';
$kategorienBaum = (new ArtikelService())->getKategorienBaum();
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div style="padding:var(--space-md);max-width:1100px">
    <?php if ($erfolg): ?><div class="success-banner" style="margin-bottom:var(--space-sm)">✓ <?= htmlspecialchars($erfolg) ?></div><?php endif; ?>
    <?php if ($fehler): ?><div class="error-banner" style="margin-bottom:var(--space-sm)">✗ <?= htmlspecialchars($fehler) ?></div><?php endif; ?>

    <div style="display:flex;gap:6px;margin-bottom:var(--space-sm)">
        <a href="auswahl.php?vorlage=anhaenger" class="btn btn-secondary btn-sm">← Preisschilder</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="ffNeu()">+ Neuer Anhänger</button>
        <a href="?<?= $alle ? '' : 'alle=1' ?>" class="btn btn-secondary btn-sm" style="margin-left:auto"><?= $alle ? 'Ausgeblendete verbergen' : 'auch ausgeblendete zeigen' ?></a>
    </div>

    <!-- Eingabe -->
    <form method="post" class="card" id="ff-form" style="margin-bottom:var(--space-sm);display:none">
        <input type="hidden" name="aktion" value="speichern">
        <input type="hidden" name="id" id="ff-id">
        <div style="font-weight:700;color:var(--color-nav);margin-bottom:8px" id="ff-titel-kopf">Neuer Anhänger</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:13px">
            <label>Kopfzeile (klein, kursiv)<br><input name="kopfzeile" id="ff-kopfzeile" class="erp-input" style="width:100%" value="handgestrickt von Babsi"></label>
            <label>Name *<br><input name="titel" id="ff-titelfeld" class="erp-input" style="width:100%" required placeholder="z.B. Tuch Herbstwald"></label>
            <label>Material<br><input name="material" id="ff-material" class="erp-input" style="width:100%" placeholder="z.B. 100% Merino, handgefärbt"></label>
            <label>Größe<br><input name="groesse" id="ff-groesse" class="erp-input" style="width:100%" placeholder="z.B. 180 × 60 cm"></label>
            <label>Zusatz<br><input name="zusatz" id="ff-zusatz" class="erp-input" style="width:100%" placeholder="z.B. Muster: Lace"></label>
            <div style="display:flex;gap:16px">
                <label>Preis €<br><input name="preis" id="ff-preis" class="erp-input" style="width:110px" placeholder="89,00"></label>
                <label>Badge<br><input name="badge" id="ff-badge" class="erp-input" style="width:120px" value="UNIKAT" maxlength="20"></label>
            </div>
        </div>
        <div style="font-size:13px;margin-top:10px">Pflege</div>
        <div style="display:flex;flex-wrap:wrap;gap:4px 14px;margin-top:4px">
            <?php foreach ($pflege as $sym => $text): ?>
                <label style="display:flex;gap:5px;align-items:center;font-size:12px">
                    <input type="checkbox" name="pflege[]" value="<?= htmlspecialchars($sym) ?>" class="ff-pflege">
                    <img src="<?= PflegeSymbole::dataUri($sym) ?>" style="width:22px;height:19px"> <?= htmlspecialchars($text) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('ff-form').style.display='none'">Abbrechen</button>
            <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        </div>
    </form>

    <!-- Liste + Druck -->
    <form method="post" action="drucken.php" target="_blank" class="card" id="ff-druck">
        <input type="hidden" name="vorlage" value="anhaenger">
        <div style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;font-size:13px;margin-bottom:10px">
            <strong style="font-size:14px">🔖 Anhänger ohne Artikel (<?= count($liste) ?>)</strong>
            <label>Start bei Feld<br><select name="start" class="erp-select" style="width:auto"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?></select></label>
            <label>Hausfarbe<br><select name="farbe" class="erp-select" style="width:auto">
                <?php foreach (PreisschildService::HAUSFARBEN as $key => [$name, $hex]): ?><option value="<?= $key ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?>
            </select></label>
            <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="schnitt" value="1" checked> Schnittlinien</label>
            <button type="submit" class="btn btn-primary btn-sm" style="margin-left:auto">🖨 Angehakte drucken</button>
        </div>
        <?php if (!$liste): ?>
            <div style="font-size:13px;color:var(--color-text-muted)">Noch keine Anhänger angelegt. „+ Neuer Anhänger“ oben.</div>
        <?php else: ?>
        <table class="erp-table">
            <thead><tr><th style="width:28px"></th><th>Anzahl</th><th>Name</th><th>Material / Größe</th><th>Pflege</th><th style="text-align:right">Preis</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $f): ?>
                <tr style="<?= $f['aktiv'] ? '' : 'opacity:.5' ?>">
                    <td><input type="checkbox" name="frei_ids[]" value="<?= (int)$f['id'] ?>"></td>
                    <td><input type="number" name="frei_anzahl[<?= (int)$f['id'] ?>]" value="1" min="1" max="12" class="erp-input" style="width:56px"></td>
                    <td><?php if ($f['kopfzeile']): ?><span style="font-size:11px;font-style:italic;color:var(--color-text-muted)"><?= htmlspecialchars($f['kopfzeile']) ?></span><br><?php endif; ?><strong><?= htmlspecialchars($f['titel']) ?></strong><?= $f['badge'] ? ' <span style="font-size:10px;border:1px solid #888;border-radius:6px;padding:0 4px">' . htmlspecialchars($f['badge']) . '</span>' : '' ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars(implode(' · ', array_filter([$f['material'], $f['groesse'], $f['zusatz']]))) ?></td>
                    <td><?php foreach ($f['pflege'] as $p): ?><img src="<?= PflegeSymbole::dataUri($p) ?>" style="width:20px;height:17px" title="<?= htmlspecialchars($pflege[$p] ?? $p) ?>"><?php endforeach; ?></td>
                    <td style="text-align:right;white-space:nowrap"><?= $f['preis'] !== null ? number_format((float)$f['preis'], 2, ',', '.') . ' €' : '–' ?></td>
                    <td style="white-space:nowrap;text-align:right">
                        <button type="button" class="btn btn-secondary btn-xs" onclick='ffBearbeiten(<?= htmlspecialchars(json_encode($f), ENT_QUOTES) ?>)'>✏️</button>
                        <button type="button" class="btn btn-secondary btn-xs" onclick="ffAktiv(<?= (int)$f['id'] ?>, <?= $f['aktiv'] ? "'ausblenden'" : "'einblenden'" ?>)"><?= $f['aktiv'] ? 'ausblenden' : 'einblenden' ?></button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </form>
    <form method="post" id="ff-aktiv"><input type="hidden" name="aktion" id="ff-aktiv-aktion"><input type="hidden" name="id" id="ff-aktiv-id"></form>
</div>

<script>
function ffSetzen(f) {
    document.getElementById('ff-id').value = f.id || '';
    document.getElementById('ff-kopfzeile').value = f.kopfzeile ?? 'handgestrickt von Babsi';
    document.getElementById('ff-titelfeld').value = f.titel || '';
    document.getElementById('ff-material').value = f.material || '';
    document.getElementById('ff-groesse').value = f.groesse || '';
    document.getElementById('ff-zusatz').value = f.zusatz || '';
    document.getElementById('ff-preis').value = f.preis !== null && f.preis !== undefined ? String(f.preis).replace('.', ',') : '';
    document.getElementById('ff-badge').value = f.badge ?? 'UNIKAT';
    document.querySelectorAll('.ff-pflege').forEach(function (c) { c.checked = (f.pflege || []).includes(c.value); });
    document.getElementById('ff-titel-kopf').textContent = f.id ? 'Anhänger bearbeiten' : 'Neuer Anhänger';
    document.getElementById('ff-form').style.display = 'block';
    document.getElementById('ff-titelfeld').focus();
}
function ffNeu() { ffSetzen({}); }
function ffBearbeiten(f) { ffSetzen(f); }
function ffAktiv(id, aktion) {
    document.getElementById('ff-aktiv-id').value = id;
    document.getElementById('ff-aktiv-aktion').value = aktion;
    document.getElementById('ff-aktiv').submit();
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
