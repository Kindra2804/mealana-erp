<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';
require_once __DIR__ . '/../../src/modules/partner/MietfachService.php';

$id      = (int)($_GET['id'] ?? 0);
$partner = (new PartnerService())->getById($id);
if (!$partner) {
    header('Location: ' . BASE_PATH . '/partner/liste.php');
    exit;
}
$tab = $_GET['tab'] ?? 'bestand';

$lagerSvc = new PartnerLagerService();
$lager    = $lagerSvc->getLager($id);
$plaetze  = $lager ? $lagerSvc->getPlaetze($id) : [];
$artikel  = $lager ? $lagerSvc->getArtikel($id) : [];
$faecher  = (new MietfachService())->getFaecherByPartner($id);
$steuerklassen = Database::getInstance()->query("SELECT id, name, satz FROM steuerklassen ORDER BY satz DESC")->fetchAll(PDO::FETCH_ASSOC);

// Verkauft im laufenden Monat (Kassenbons, ohne stornierte)
$verkauftMonat = [];
if ($artikel) {
    $ids  = array_column($artikel, 'id');
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = Database::getInstance()->prepare("
        SELECT p.artikel_id, SUM(p.menge) AS menge
        FROM kassen_bon_positionen p
        JOIN kassen_bons b ON b.id = p.bon_id
        WHERE p.artikel_id IN ($ph) AND b.storniert = 0 AND b.typ = 'verkauf'
          AND b.erstellt_am >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        GROUP BY p.artikel_id
    ");
    $stmt->execute($ids);
    $verkauftMonat = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

$belege     = $lager && $tab === 'belege' ? $lagerSvc->getBelege($id) : [];
$bewegungen = $lager && $tab === 'bewegungen' ? $lagerSvc->getBewegungen($id) : [];

$typLabel = ['mietfach' => 'Mietfach', 'kommission' => 'Kommission', 'spende' => 'Spende', 'beides' => 'Mietfach + Kommission'];
$praefix  = PartnerLagerService::nummernPraefix($id);

$pageTitle        = $partner['name'];
$activeModule     = 'partner';
$actionBarContent = '<a href="' . BASE_PATH . '/partner/liste.php" class="btn btn-secondary btn-sm">← Partner</a>';
require_once __DIR__ . '/../includes/shell_top.php';

function partnerTab(string $key, string $label, string $aktiv, int $id): string
{
    return '<a class="tab' . ($key === $aktiv ? ' active' : '') . '" href="?id=' . $id . '&tab=' . $key . '">' . $label . '</a>';
}
?>
<div class="card" style="margin-bottom:12px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <div style="font-size:18px;font-weight:700;color:var(--color-nav)"><?= htmlspecialchars($partner['name']) ?></div>
    <span class="chip"><?= htmlspecialchars($typLabel[$partner['typ']] ?? $partner['typ']) ?></span>
    <?php if ($lager): ?>
        <span style="font-size:12px;color:var(--color-text-muted)">Lager: <strong><?= htmlspecialchars($lager['name']) ?></strong> · Artikelnummern <code><?= $praefix ?>…</code></span>
    <?php endif; ?>
</div>

<?php if (!$lager): ?>
<div class="card" style="padding:24px;text-align:center">
    <p style="font-size:14px;margin-top:0">Dieser Partner hat noch kein eigenes Lager. Ohne Lager kann seine Ware nicht eingebucht, verkauft oder zurückgegeben werden.</p>
    <button class="btn btn-primary" onclick="partnerLagerAnlegen(<?= $id ?>)">Partner-Lager anlegen</button>
    <p style="font-size:12px;color:var(--color-text-muted)">Die aktuell gemieteten Fächer (<?= count($faecher) ?>) werden automatisch zu Lagerplätzen darin.</p>
</div>
<?php else: ?>

<div class="tab-bar" style="margin-bottom:12px">
    <?= partnerTab('bestand', 'Bestand', $tab, $id) ?>
    <?= partnerTab('artikel', 'Artikel (' . count($artikel) . ')', $tab, $id) ?>
    <?= partnerTab('bewegungen', 'Bewegungen', $tab, $id) ?>
    <?= partnerTab('belege', 'Belege & Verkaufsliste', $tab, $id) ?>
    <?= partnerTab('faecher', 'Mietfächer (' . count($plaetze) . ')', $tab, $id) ?>
</div>

<?php if ($tab === 'bestand'): ?>
<div class="card">
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-bottom:10px">
        <a class="btn btn-primary btn-sm" href="<?= BASE_PATH ?>/partner/beleg_neu.php?id=<?= $id ?>&typ=uebernahme">📥 Ware übernehmen</a>
        <a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/partner/beleg_neu.php?id=<?= $id ?>&typ=rueckgabe">📤 Rückgabe an Partner</a>
    </div>
    <table class="erp-table">
        <thead><tr><th style="width:110px">FACH</th><th>ARTIKEL</th><th style="width:170px">NR.</th>
            <th style="width:90px;text-align:right">BESTAND</th><th style="width:130px;text-align:right">VERKAUFT (MONAT)</th></tr></thead>
        <tbody>
        <?php $mitBestand = array_filter($artikel, fn($a) => (float)$a['bestand'] != 0); ?>
        <?php if (!$mitBestand): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Ware im Partner-Lager.</td></tr>
        <?php endif; ?>
        <?php foreach ($mitBestand as $a): ?>
            <tr>
                <td style="font-family:monospace;font-weight:600"><?= htmlspecialchars($a['fach'] ?? '–') ?></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($a['artikelnummer']) ?></td>
                <td style="text-align:right;font-weight:600"><?= rtrim(rtrim(number_format((float)$a['bestand'], 3, ',', ''), '0'), ',') ?></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)($verkauftMonat[$a['id']] ?? 0), 3, ',', ''), '0'), ',') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'artikel'): ?>
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div style="font-size:12px;color:var(--color-text-muted)">Partnerware steht nicht in der normalen Artikelliste und nie im Onlineshop.</div>
        <button class="btn btn-primary btn-sm" onclick="paModal(null)">+ Neuer Artikel</button>
    </div>
    <table class="erp-table">
        <thead><tr><th style="width:170px">NR.</th><th>BEZEICHNUNG</th><th style="width:130px">EAN</th><th style="width:90px">FACH</th>
            <th style="width:90px;text-align:right">PREIS</th><th style="width:60px">MWST</th><th style="width:80px;text-align:right">BESTAND</th><th style="width:50px"></th></tr></thead>
        <tbody>
        <?php if (!$artikel): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Artikel — „+ Neuer Artikel“.</td></tr>
        <?php endif; ?>
        <?php foreach ($artikel as $a): ?>
            <tr <?= $a['aktiv'] ? '' : 'style="opacity:.5"' ?>>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($a['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($a['ean'] ?? '') ?></td>
                <td style="font-family:monospace"><?= htmlspecialchars($a['fach'] ?? '–') ?></td>
                <td style="text-align:right">€ <?= number_format((float)$a['brutto_vk'], 2, ',', '.') ?></td>
                <td><?= rtrim(rtrim(number_format((float)$a['steuersatz'], 1, ',', ''), '0'), ',') ?> %</td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$a['bestand'], 3, ',', ''), '0'), ',') ?></td>
                <td><button class="btn btn-secondary btn-sm" onclick='paModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'>✎</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Partner-Artikel -->
<div id="pa-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:20px;width:460px;box-shadow:0 8px 32px rgba(0,0,0,.2)">
        <div id="pa-titel" style="font-weight:700;font-size:15px;margin-bottom:12px;color:var(--color-nav)">Neuer Artikel</div>
        <input type="hidden" id="pa-id">
        <label class="erp-label">Artikelnummer *</label>
        <div style="display:flex;align-items:center;gap:4px;margin-bottom:10px">
            <code style="font-size:14px;padding:6px 8px;background:#f1f5f9;border-radius:4px"><?= $praefix ?></code>
            <input type="text" id="pa-suffix" class="erp-input" style="flex:1" maxlength="40" placeholder="z.B. SEIFE-LAV">
        </div>
        <label class="erp-label">Bezeichnung *</label>
        <input type="text" id="pa-name" class="erp-input" style="width:100%;box-sizing:border-box;margin-bottom:10px" maxlength="200">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <div><label class="erp-label">Verkaufspreis brutto *</label><input type="text" id="pa-preis" class="erp-input" style="width:100%;box-sizing:border-box" placeholder="0,00"></div>
            <div><label class="erp-label">MwSt</label>
                <select id="pa-steuer" class="erp-select" style="width:100%">
                    <?php foreach ($steuerklassen as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= rtrim(rtrim(number_format((float)$s['satz'], 1, ',', ''), '0'), ',') ?> %)</option><?php endforeach; ?>
                </select></div>
            <div><label class="erp-label">EAN (optional)</label><input type="text" id="pa-ean" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="20"></div>
            <div><label class="erp-label">Fach</label>
                <select id="pa-fach" class="erp-select" style="width:100%">
                    <option value="">— kein Fach —</option>
                    <?php foreach ($plaetze as $lp): ?><option value="<?= $lp['id'] ?>"><?= htmlspecialchars($lp['bezeichnung']) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <label style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:13px"><input type="checkbox" id="pa-aktiv" checked> Aktiv (an der Kasse verkaufbar)</label>
        <div id="pa-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">
            <button class="btn btn-secondary btn-sm" onclick="document.getElementById('pa-modal').style.display='none'">Abbrechen</button>
            <button class="btn btn-primary btn-sm" onclick="paSpeichern(<?= $id ?>)">Speichern</button>
        </div>
    </div>
</div>

<?php elseif ($tab === 'bewegungen'): ?>
<div class="card">
    <table class="erp-table">
        <thead><tr><th style="width:130px">DATUM</th><th style="width:90px">ART</th><th style="width:160px">NR.</th><th>ARTIKEL</th>
            <th style="width:70px;text-align:right">MENGE</th><th style="width:70px;text-align:right">DANACH</th><th>BELEG / BON</th><th style="width:110px">VON</th></tr></thead>
        <tbody>
        <?php if (!$bewegungen): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Bewegungen.</td></tr>
        <?php endif; ?>
        <?php $artLabel = ['eingang' => 'Eingang', 'ausgang' => 'Ausgang', 'korrektur' => 'Korrektur', 'inventur' => 'Inventur', 'schwund' => 'Schwund']; ?>
        <?php foreach ($bewegungen as $b): $minus = in_array($b['bewegungstyp'], ['ausgang', 'schwund'], true); ?>
            <tr>
                <td style="font-size:12px"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                <td><span class="chip"><?= $artLabel[$b['bewegungstyp']] ?? htmlspecialchars($b['bewegungstyp']) ?></span></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($b['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($b['name']) ?><?= $b['charge'] ? ' <span style="font-size:11px;color:var(--color-text-muted)">· ' . htmlspecialchars($b['charge']) . '</span>' : '' ?></td>
                <td style="text-align:right;font-weight:600;color:<?= $minus ? 'var(--color-danger)' : 'var(--color-success)' ?>"><?= ($minus ? '−' : '+') . rtrim(rtrim(number_format((float)$b['menge'], 3, ',', ''), '0'), ',') ?></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$b['bestand_nachher'], 3, ',', ''), '0'), ',') ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($b['referenz'] ?? '') ?></td>
                <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($b['benutzer_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'belege'): ?>
<div class="card" style="margin-bottom:12px">
    <strong style="font-size:13px">Verkaufsliste für den Partner</strong>
    <form method="get" action="<?= BASE_PATH ?>/partner/verkaufsliste_pdf.php" target="_blank" style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap">
        <input type="hidden" name="id" value="<?= $id ?>">
        von <input type="date" name="von" class="erp-input" value="<?= date('Y-m-01') ?>">
        bis <input type="date" name="bis" class="erp-input" value="<?= date('Y-m-d') ?>">
        <button class="btn btn-primary btn-sm">📄 PDF erstellen</button>
        <span style="font-size:12px;color:var(--color-text-muted)">was wann um wie viel an der Kasse verkauft wurde</span>
    </form>
</div>
<div class="card">
    <table class="erp-table">
        <thead><tr><th style="width:150px">NUMMER</th><th style="width:140px">ART</th><th style="width:130px">DATUM</th>
            <th style="width:90px;text-align:right">POSITIONEN</th><th style="width:80px;text-align:right">STÜCK</th><th>NOTIZ</th><th style="width:110px">VON</th></tr></thead>
        <tbody>
        <?php if (!$belege): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--color-text-muted);padding:24px">Noch keine Übernahme- oder Rückgabescheine.</td></tr>
        <?php endif; ?>
        <?php foreach ($belege as $b): ?>
            <tr>
                <td><a href="<?= BASE_PATH ?>/partner/beleg_pdf.php?id=<?= (int)$b['id'] ?>" target="_blank" style="font-family:monospace;font-weight:600"><?= htmlspecialchars($b['nummer']) ?></a></td>
                <td><?= $b['typ'] === 'uebernahme' ? '📥 Übernahmeschein' : '📤 Rückgabeschein' ?></td>
                <td style="font-size:12px"><?= date('d.m.Y H:i', strtotime($b['erstellt_am'])) ?></td>
                <td style="text-align:right"><?= (int)$b['anzahl'] ?></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$b['menge_gesamt'], 3, ',', ''), '0'), ',') ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($b['notiz'] ?? '') ?></td>
                <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($b['benutzer_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($tab === 'faecher'): ?>
<div class="card">
    <table class="erp-table">
        <thead><tr><th style="width:160px">FACH (LAGERPLATZ)</th><th>ORT</th><th style="width:120px;text-align:right">ARTIKEL</th><th style="width:60px"></th></tr></thead>
        <tbody>
        <?php if (!$plaetze): ?>
            <tr><td colspan="4" style="text-align:center;color:var(--color-text-muted);padding:24px">Keine gemieteten Fächer. Mietverträge: <a href="<?= BASE_PATH ?>/partner/mietfaecher.php">Mietfächer</a></td></tr>
        <?php endif; ?>
        <?php foreach ($plaetze as $lp): $n = count(array_filter($artikel, fn($a) => (int)$a['stammplatz_id'] === (int)$lp['id'])); ?>
            <tr>
                <td style="font-family:monospace;font-weight:600"><?= htmlspecialchars($lp['bezeichnung']) ?></td>
                <td style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($lp['ort_beschreibung'] ?? '') ?></td>
                <td style="text-align:right"><?= $n ?></td>
                <td><a class="btn btn-secondary btn-sm" href="<?= BASE_PATH ?>/lager/lagerplaetze_etiketten.php?ids=<?= $lp['id'] ?>" target="_blank" title="Etikett mit QR-Code">🏷</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:12px;color:var(--color-text-muted);margin-bottom:0">Ein neu gemietetes Fach wird beim Start des Mietvertrags automatisch hier eingetragen.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
function partnerLagerAnlegen(id) {
    fetch(window.BASE_PATH + '/partner/lager_anlegen.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ partner_id: id }) })
        .then(r => r.json()).then(function (d) {
            if (!d.erfolg) { alert((d.fehler || ['Fehler']).join(' ')); return; }
            if (d.hinweise && d.hinweise.length) alert(d.hinweise.join('\n'));
            location.reload();
        });
}
function paModal(a) {
    var praefix = <?= json_encode($praefix) ?>;
    document.getElementById('pa-titel').textContent = a ? 'Artikel bearbeiten' : 'Neuer Artikel';
    document.getElementById('pa-id').value     = a ? a.id : '';
    document.getElementById('pa-suffix').value = a ? a.artikelnummer.substring(praefix.length) : '';
    document.getElementById('pa-name').value   = a ? a.name : '';
    document.getElementById('pa-preis').value  = a && a.brutto_vk ? parseFloat(a.brutto_vk).toFixed(2).replace('.', ',') : '';
    document.getElementById('pa-steuer').value = a ? a.steuerklasse_id : '1';
    document.getElementById('pa-ean').value    = a ? (a.ean || '') : '';
    document.getElementById('pa-fach').value   = a && a.stammplatz_id ? a.stammplatz_id : '';
    document.getElementById('pa-aktiv').checked = a ? a.aktiv == 1 : true;
    document.getElementById('pa-fehler').textContent = '';
    document.getElementById('pa-modal').style.display = 'flex';
    document.getElementById(a ? 'pa-name' : 'pa-suffix').focus();
}
function paSpeichern(partnerId) {
    var daten = {
        partner_id: partnerId, id: document.getElementById('pa-id').value || null,
        nummer_suffix: document.getElementById('pa-suffix').value, name: document.getElementById('pa-name').value,
        brutto_vk: document.getElementById('pa-preis').value, steuerklasse_id: document.getElementById('pa-steuer').value,
        ean: document.getElementById('pa-ean').value, stammplatz_id: document.getElementById('pa-fach').value || null,
        aktiv: document.getElementById('pa-aktiv').checked ? 1 : 0,
    };
    fetch(window.BASE_PATH + '/partner/artikel_speichern.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) })
        .then(r => r.json()).then(function (d) {
            if (!d.erfolg) { document.getElementById('pa-fehler').textContent = (d.fehler || ['Fehler']).join(' '); return; }
            location.reload();
        });
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
