<?php
/**
 * Händler: Ware liefern (Artikel suchen/scannen aus dem eigenen Lager), zurücknehmen oder
 * Schwund melden (aus dem Bestand beim Händler). Erzeugt den jeweiligen Beleg (HL/HR/HS).
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

$kundeId   = (int)($_GET['kunde_id'] ?? 0);
$buchArt   = in_array($_GET['typ'] ?? '', ['lieferung', 'ruecknahme', 'schwund'], true) ? $_GET['typ'] : 'lieferung';
$svc       = new HaendlerService();
$kunde     = (new KundenService())->getById($kundeId);
if (!$kunde || !$svc->getLager($kundeId)) { header('Location: ' . BASE_PATH . '/haendler/detail.php?kunde_id=' . $kundeId); exit; }
$haendlerName = $svc->kundenName($kunde);
$eigeneLager  = Database::getInstance()->query("SELECT id, name FROM lager WHERE lager_beziehung = 'eigen' AND aktiv = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$haendlerBestand = $buchArt === 'lieferung' ? [] : $svc->getBestand($kundeId);

$ueberschrift = ['lieferung' => '🚚 Ware liefern', 'ruecknahme' => '↩ Rücknahme vom Händler', 'schwund' => '⚠ Schwund beim Händler'][$buchArt];
$pageTitle        = $ueberschrift . ' — ' . $haendlerName;
$activeModule     = 'verkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/haendler/detail.php?kunde_id=' . $kundeId . '" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($haendlerName) . '</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="margin-bottom:12px;display:flex;flex-direction:column;gap:8px">
    <div style="font-size:17px;font-weight:700;color:var(--color-nav)"><?= $ueberschrift ?> — <?= htmlspecialchars($haendlerName) ?></div>
    <div style="font-size:12px;color:var(--color-text-muted)">
        <?php if ($buchArt === 'lieferung'): ?>
            Artikel scannen oder suchen. Die Ware wird ins Händler-Außenlager umgebucht und bleibt euer Bestand. Der Händlerpreis wird mit der Lieferung festgehalten und steht auf dem Lieferschein, zusammen mit dem empfohlenen Endkunden-VK.
        <?php elseif ($buchArt === 'ruecknahme'): ?>
            Mengen eintragen, die der Händler zurückgibt. Sie werden zurück ins eigene Lager gebucht (Rücknahmeschein).
        <?php else: ?>
            Ware, die beim Händler verloren gegangen oder beschädigt ist. Wird ausgebucht und <strong>nicht</strong> verrechnet. Grund ist Pflicht.
        <?php endif; ?>
    </div>
    <?php if ($buchArt !== 'schwund'): ?>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <label style="font-size:13px"><?= $buchArt === 'lieferung' ? 'Aus Lager' : 'Zurück in Lager' ?>:</label>
        <select id="eigenes-lager" class="erp-select">
            <?php foreach ($eigeneLager as $l): ?><option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php if ($buchArt === 'lieferung'): ?>
    <div style="position:relative;max-width:520px">
        <input type="text" id="suche" class="erp-input" style="width:100%" placeholder="EAN scannen oder Artikelnummer / Name suchen" autocomplete="off" autofocus>
        <div id="treffer" style="display:none;position:absolute;left:0;right:0;top:100%;z-index:50;background:var(--color-bg, #fff);border:1px solid var(--color-border);border-radius:6px;max-height:300px;overflow-y:auto;box-shadow:0 4px 16px rgba(0,0,0,.12)"></div>
    </div>
    <?php endif; ?>
</div>

<div class="card">
    <table class="erp-table" id="zeilen">
        <thead><tr><th style="width:150px">NR.</th><th>ARTIKEL</th><th style="width:130px">CHARGE</th>
            <th style="width:100px;text-align:right"><?= $buchArt === 'lieferung' ? 'IM LAGER' : 'BEIM HÄNDLER' ?></th>
            <?php if ($buchArt === 'lieferung'): ?><th style="width:120px;text-align:right">HÄNDLER NETTO</th><th style="width:100px;text-align:right">VK BRUTTO</th><?php endif; ?>
            <th style="width:100px">MENGE</th><?php if ($buchArt === 'lieferung'): ?><th style="width:40px"></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($haendlerBestand as $b): ?>
            <tr data-artikel="<?= (int)$b['artikel_id'] ?>" data-ean="<?= htmlspecialchars($b['ean'] ?? '') ?>" data-nr="<?= htmlspecialchars($b['artikelnummer']) ?>">
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($b['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td><input type="text" class="erp-input charge" style="width:110px" placeholder="optional"></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$b['menge'], 3, ',', ''), '0'), ',') ?></td>
                <td><input type="number" class="erp-input menge" style="width:90px" min="0" step="1" max="<?= (float)$b['menge'] ?>"></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($buchArt !== 'lieferung' && !$haendlerBestand): ?>
            <tr><td colspan="5" style="text-align:center;color:var(--color-text-muted);padding:24px">Beim Händler liegt nichts.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" id="notiz" class="erp-input" style="flex:1;min-width:200px" placeholder="<?= $buchArt === 'schwund' ? 'Grund (Pflicht), z.B. beim Händler beschädigt' : 'Notiz (optional, steht auf dem Beleg)' ?>">
        <div id="summe" style="font-size:13px;color:var(--color-text-muted)"></div>
        <button class="btn btn-primary" onclick="buchen()"><?= ['lieferung' => 'Liefern & Lieferschein erstellen', 'ruecknahme' => 'Rücknahme buchen', 'schwund' => 'Schwund ausbuchen'][$buchArt] ?></button>
    </div>
    <div id="fehler" style="color:var(--color-danger);font-size:13px;margin-top:8px"></div>
</div>

<script>
var KUNDE = <?= $kundeId ?>, ART = <?= json_encode($buchArt) ?>;
var eur = v => v == null ? '–' : '€ ' + Number(v).toFixed(2).replace('.', ',');

function zeilen() { return Array.from(document.querySelectorAll('#zeilen tbody tr[data-artikel]')); }

function summeZeigen() {
    if (ART !== 'lieferung') return;
    var n = 0, s = 0;
    zeilen().forEach(function (tr) { var m = parseFloat(tr.querySelector('.menge').value) || 0; n += m; s += m * (parseFloat(tr.dataset.preis) || 0); });
    document.getElementById('summe').textContent = n ? n + ' Stk · ' + eur(s) + ' netto' : '';
}
document.addEventListener('input', function (e) { if (e.target.classList.contains('menge')) summeZeigen(); });

function zeileHinzu(a) {
    var vorhanden = zeilen().find(tr => tr.dataset.artikel == a.id);
    if (vorhanden) { var m = vorhanden.querySelector('.menge'); m.value = (parseInt(m.value, 10) || 0) + 1; summeZeigen(); return; }
    if (a.preis_netto === null) { document.getElementById('fehler').textContent = a.artikelnummer + ': kein Händlerpreis — Standard-Rabatt festlegen oder Händlerpreis am Artikel pflegen.'; return; }
    var tr = document.createElement('tr');
    tr.dataset.artikel = a.id; tr.dataset.ean = a.ean || ''; tr.dataset.nr = a.artikelnummer; tr.dataset.preis = a.preis_netto;
    tr.innerHTML = '<td style="font-family:monospace;font-size:12px"></td><td></td>'
        + '<td><input type="text" class="erp-input charge" style="width:110px" placeholder="optional"></td>'
        + '<td style="text-align:right"></td><td style="text-align:right"></td><td style="text-align:right"></td>'
        + '<td><input type="number" class="erp-input menge" style="width:90px" min="0" step="1" value="1"></td>'
        + '<td><button class="btn btn-secondary btn-sm" title="Zeile entfernen">✕</button></td>';
    tr.cells[0].textContent = a.artikelnummer; tr.cells[1].textContent = a.name;
    tr.cells[3].textContent = String(parseFloat(a.bestand)).replace('.', ',');
    tr.cells[4].textContent = eur(a.preis_netto) + (a.preis_quelle === 'rabatt' ? ' *' : '');
    tr.cells[5].textContent = eur(a.vk_brutto);
    tr.querySelector('button').onclick = function () { tr.remove(); summeZeigen(); };
    document.querySelector('#zeilen tbody').appendChild(tr);
    summeZeigen();
}

<?php if ($buchArt === 'lieferung'): ?>
(function () {
    var feld = document.getElementById('suche'), box = document.getElementById('treffer'), timer;
    function suchen(q, sofortUebernehmen) {
        fetch(window.BASE_PATH + '/haendler/artikel_ajax.php?q=' + encodeURIComponent(q) + '&lager_id=' + document.getElementById('eigenes-lager').value)
            .then(r => r.json()).then(function (liste) {
                if (sofortUebernehmen && liste.length && (liste[0].ean === q || liste[0].artikelnummer === q)) { zeileHinzu(liste[0]); feld.value = ''; box.style.display = 'none'; return; }
                box.innerHTML = '';
                liste.forEach(function (a) {
                    var d = document.createElement('div');
                    d.style.cssText = 'padding:7px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-border)';
                    d.textContent = a.artikelnummer + ' · ' + a.name + ' · Lager ' + String(parseFloat(a.bestand)).replace('.', ',') + ' · ' + eur(a.preis_netto) + ' netto';
                    d.onclick = function () { zeileHinzu(a); feld.value = ''; box.style.display = 'none'; feld.focus(); };
                    box.appendChild(d);
                });
                box.style.display = liste.length ? 'block' : 'none';
            });
    }
    feld.addEventListener('input', function () { clearTimeout(timer); var q = feld.value.trim(); if (q.length < 2) { box.style.display = 'none'; return; } timer = setTimeout(() => suchen(q, false), 250); });
    feld.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var q = feld.value.trim(); if (q) suchen(q, true); } });
})();
<?php endif; ?>

function buchen() {
    var pos = zeilen().map(tr => ({ artikel_id: parseInt(tr.dataset.artikel, 10), menge: tr.querySelector('.menge').value, charge: tr.querySelector('.charge').value }))
        .filter(p => parseFloat(p.menge) > 0);
    if (!pos.length) { document.getElementById('fehler').textContent = 'Bitte mindestens eine Menge eintragen.'; return; }
    var lagerSel = document.getElementById('eigenes-lager');
    fetch(window.BASE_PATH + '/haendler/aktion.php', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ aktion: 'buchen', typ: ART, kunde_id: KUNDE, positionen: pos, notiz: document.getElementById('notiz').value, lager_id: lagerSel ? lagerSel.value : 1 }) })
        .then(r => r.json()).then(function (d) {
            if (!d.erfolg) { document.getElementById('fehler').textContent = (d.fehler || ['Fehler']).join(' '); return; }
            if (ART !== 'schwund') window.open(window.BASE_PATH + '/haendler/beleg_pdf.php?id=' + d.id, '_blank');
            location.href = window.BASE_PATH + '/haendler/detail.php?kunde_id=' + KUNDE + '&tab=belege';
        });
}
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
