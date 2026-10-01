<?php
/**
 * Ware vom Partner übernehmen bzw. an ihn zurückgeben: Mengen je Artikel eintragen
 * (Scan der EAN springt zur Zeile), "Buchen" erzeugt den Beleg und öffnet das PDF.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerService.php';
require_once __DIR__ . '/../../src/modules/partner/PartnerLagerService.php';

$id      = (int)($_GET['id'] ?? 0);
$belegTyp     = ($_GET['typ'] ?? '') === 'rueckgabe' ? 'rueckgabe' : 'uebernahme';
$partner = (new PartnerService())->getById($id);
$svc     = new PartnerLagerService();
if (!$partner || !$svc->getLager($id)) {
    header('Location: ' . BASE_PATH . '/partner/detail.php?id=' . $id);
    exit;
}
$artikel = array_values(array_filter($svc->getArtikel($id), fn($a) =>
    $belegTyp === 'uebernahme' ? (int)$a['aktiv'] === 1 : (float)$a['bestand'] > 0
));

$belegTitel            = $belegTyp === 'uebernahme' ? '📥 Ware übernehmen' : '📤 Rückgabe an Partner';
$pageTitle        = $belegTitel . ' — ' . $partner['name'];
$activeModule     = 'partner';
$actionBarContent = '<a href="' . BASE_PATH . '/partner/detail.php?id=' . $id . '" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($partner['name']) . '</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="margin-bottom:12px">
    <div style="font-size:17px;font-weight:700;color:var(--color-nav)"><?= $belegTitel ?> — <?= htmlspecialchars($partner['name']) ?></div>
    <div style="font-size:12px;color:var(--color-text-muted);margin-top:4px">
        <?= $belegTyp === 'uebernahme'
            ? 'Mengen eintragen, die der Partner bringt. Neue Artikel vorher im Reiter „Artikel“ anlegen.'
            : 'Mengen eintragen, die der Partner wieder mitnimmt (höchstens der aktuelle Bestand).' ?>
        Beim Buchen entsteht ein <?= $belegTyp === 'uebernahme' ? 'Übernahmeschein' : 'Rückgabeschein' ?> zum Unterschreiben.
    </div>
    <input type="text" id="scan" class="erp-input" style="margin-top:10px;width:320px" placeholder="EAN scannen → springt zur Zeile, +1" autocomplete="off" autofocus>
</div>

<div class="card">
    <table class="erp-table" id="beleg-tabelle">
        <thead><tr><th style="width:90px">FACH</th><th style="width:170px">NR.</th><th>ARTIKEL</th><th style="width:130px">CHARGE</th>
            <th style="width:90px;text-align:right">BESTAND</th><th style="width:110px">MENGE</th></tr></thead>
        <tbody>
        <?php if (!$artikel): ?>
            <tr><td colspan="6" style="text-align:center;color:var(--color-text-muted);padding:24px">
                <?= $belegTyp === 'uebernahme' ? 'Noch keine aktiven Artikel — zuerst im Reiter „Artikel“ anlegen.' : 'Kein Bestand im Partner-Lager.' ?></td></tr>
        <?php endif; ?>
        <?php foreach ($artikel as $a): ?>
            <tr data-artikel="<?= (int)$a['id'] ?>" data-ean="<?= htmlspecialchars($a['ean'] ?? '') ?>" data-nr="<?= htmlspecialchars($a['artikelnummer']) ?>" data-bestand="<?= (float)$a['bestand'] ?>">
                <td style="font-family:monospace"><?= htmlspecialchars($a['fach'] ?? '–') ?></td>
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($a['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($a['name']) ?></td>
                <td><input type="text" class="erp-input charge" style="width:110px" placeholder="optional"></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$a['bestand'], 3, ',', ''), '0'), ',') ?></td>
                <td><input type="number" class="erp-input menge" style="width:90px" min="0" step="1"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:10px;align-items:center">
        <input type="text" id="notiz" class="erp-input" style="flex:1" placeholder="Notiz (optional, steht auf dem Beleg)">
        <button class="btn btn-primary" onclick="belegBuchen()">Buchen &amp; Beleg erstellen</button>
    </div>
    <div id="fehler" style="color:var(--color-danger);font-size:13px;margin-top:8px"></div>
</div>

<script>
document.getElementById('scan').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    var code = this.value.trim(); this.value = '';
    if (!code) return;
    var zeile = Array.from(document.querySelectorAll('#beleg-tabelle tbody tr[data-artikel]'))
        .find(function (tr) { return tr.dataset.ean === code || tr.dataset.nr.toUpperCase() === code.toUpperCase(); });
    if (!zeile) { document.getElementById('fehler').textContent = 'Kein Artikel dieses Partners mit „' + code + '“.'; return; }
    document.getElementById('fehler').textContent = '';
    var m = zeile.querySelector('.menge'); m.value = (parseInt(m.value, 10) || 0) + 1;
    zeile.style.background = '#e6f7ee'; zeile.scrollIntoView({ block: 'center' });
});
function belegBuchen() {
    var pos = Array.from(document.querySelectorAll('#beleg-tabelle tbody tr[data-artikel]')).map(function (tr) {
        return { artikel_id: parseInt(tr.dataset.artikel, 10), menge: tr.querySelector('.menge').value, charge: tr.querySelector('.charge').value };
    }).filter(function (p) { return parseFloat(p.menge) > 0; });
    if (!pos.length) { document.getElementById('fehler').textContent = 'Bitte mindestens eine Menge eintragen.'; return; }
    fetch(window.BASE_PATH + '/partner/beleg_buchen.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ partner_id: <?= $id ?>, typ: <?= json_encode($belegTyp) ?>, positionen: pos, notiz: document.getElementById('notiz').value }) })
        .then(r => r.json()).then(function (d) {
            if (!d.erfolg) { document.getElementById('fehler').textContent = (d.fehler || ['Fehler']).join(' '); return; }
            window.open(window.BASE_PATH + '/partner/beleg_pdf.php?id=' + d.id, '_blank');
            location.href = window.BASE_PATH + '/partner/detail.php?id=<?= $id ?>&tab=belege';
        });
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
