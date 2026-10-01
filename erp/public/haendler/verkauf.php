<?php
/**
 * Verkaufsmeldung eines Händlers abrechnen. Zwei Eingabewege:
 *  - "verkauft": Händler meldet die verkauften Stück
 *  - "noch da":  Händler meldet seinen Restbestand, verkauft = beim Händler − noch da − Schwund
 * Preise sind vorbelegt (Preis der ältesten offenen Lieferung) und vor der Rechnung änderbar.
 * Schwund (verloren/beschädigt) wird separat ausgebucht und nicht verrechnet.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/haendler/HaendlerService.php';

$kundeId = (int)($_GET['kunde_id'] ?? 0);
$svc     = new HaendlerService();
$kunde   = (new KundenService())->getById($kundeId);
if (!$kunde || !$svc->getLager($kundeId)) { header('Location: ' . BASE_PATH . '/haendler/detail.php?kunde_id=' . $kundeId); exit; }
$haendlerName = $svc->kundenName($kunde);
$bestand      = $svc->getBestand($kundeId);

$pageTitle        = 'Verkaufsmeldung — ' . $haendlerName;
$activeModule     = 'verkauf';
$actionBarContent = '<a href="' . BASE_PATH . '/haendler/detail.php?kunde_id=' . $kundeId . '" class="btn btn-secondary btn-sm">← ' . htmlspecialchars($haendlerName) . '</a>';
require_once __DIR__ . '/../includes/shell_top.php';
?>
<div class="card" style="margin-bottom:12px;display:flex;flex-direction:column;gap:8px">
    <div style="font-size:17px;font-weight:700;color:var(--color-nav)">🧾 Verkaufsmeldung — <?= htmlspecialchars($haendlerName) ?></div>
    <div style="font-size:12px;color:var(--color-text-muted)">Aus der Meldung entsteht ein Auftrag mit Rechnung (Netto-Preise + USt). Erst dann wird der Bestand beim Händler abgebucht. Preise lassen sich hier noch korrigieren, danach nur über eine Gutschrift.</div>
    <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;font-size:13px">
        <strong>Händler meldet:</strong>
        <label><input type="radio" name="modus" value="verkauft" checked onchange="modusWechsel()"> was verkauft wurde</label>
        <label><input type="radio" name="modus" value="rest" onchange="modusWechsel()"> was noch da ist (Restbestand)</label>
        <input type="text" id="scan" class="erp-input" style="width:260px" placeholder="EAN scannen → +1 in der Eingabespalte" autocomplete="off">
    </div>
</div>

<div class="card">
    <table class="erp-table" id="zeilen">
        <thead><tr><th style="width:140px">NR.</th><th>ARTIKEL</th><th style="width:85px;text-align:right">BEIM HÄNDLER</th>
            <th style="width:100px" id="kopf-eingabe">VERKAUFT</th><th style="width:90px">SCHWUND</th>
            <th style="width:85px;text-align:right">VERRECHNET</th><th style="width:110px">PREIS NETTO</th><th style="width:100px;text-align:right">SUMME NETTO</th></tr></thead>
        <tbody>
        <?php if (!$bestand): ?>
            <tr><td colspan="8" style="text-align:center;color:var(--color-text-muted);padding:24px">Beim Händler liegt nichts zum Abrechnen.</td></tr>
        <?php endif; ?>
        <?php foreach ($bestand as $b): ?>
            <tr data-artikel="<?= (int)$b['artikel_id'] ?>" data-ean="<?= htmlspecialchars($b['ean'] ?? '') ?>" data-nr="<?= htmlspecialchars($b['artikelnummer']) ?>"
                data-bestand="<?= (float)$b['menge'] ?>" data-preis="<?= number_format((float)$b['preis_fifo'], 2, '.', '') ?>" data-gemischt="<?= $b['preis_min'] != $b['preis_max'] ? 1 : 0 ?>">
                <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($b['artikelnummer']) ?></td>
                <td><?= htmlspecialchars($b['name']) ?></td>
                <td style="text-align:right"><?= rtrim(rtrim(number_format((float)$b['menge'], 3, ',', ''), '0'), ',') ?></td>
                <td><input type="number" class="erp-input eingabe" style="width:85px" min="0" step="1"></td>
                <td><input type="number" class="erp-input schwund" style="width:75px" min="0" step="1"></td>
                <td class="verrechnet" style="text-align:right;font-weight:600">0</td>
                <td><input type="text" class="erp-input preis" style="width:95px;text-align:right" value="<?= number_format((float)$b['preis_fifo'], 2, ',', '') ?>"
                    title="<?= $b['preis_min'] != $b['preis_max'] ? 'Lieferungen zu verschiedenen Preisen — unverändert lassen, dann wird je Lieferung ihr eigener Preis verrechnet' : '' ?>">
                    <?= $b['preis_min'] != $b['preis_max'] ? '<div style="font-size:10px;color:var(--color-text-muted)">je Lieferung</div>' : '' ?></td>
                <td class="summe" style="text-align:right">–</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <input type="text" id="notiz" class="erp-input" style="flex:1;min-width:200px" placeholder="Notiz, z.B. Meldung Q3 / per Mail vom 30.09. (Schwund: Grund angeben)">
        <div id="gesamt" style="font-size:14px;font-weight:600"></div>
        <button class="btn btn-primary" onclick="abrechnen()">Abrechnen &amp; Rechnung erstellen</button>
    </div>
    <div id="fehler" style="color:var(--color-danger);font-size:13px;margin-top:8px"></div>
</div>

<script>
var KUNDE = <?= $kundeId ?>;
var zeilen = () => Array.from(document.querySelectorAll('#zeilen tbody tr[data-artikel]'));
var zahl = v => parseFloat(String(v).replace(',', '.')) || 0;
var modus = () => document.querySelector('input[name=modus]:checked').value;

function rechnen() {
    var stueck = 0, summe = 0, fehler = [];
    zeilen().forEach(function (tr) {
        var bestand = parseFloat(tr.dataset.bestand), ein = tr.querySelector('.eingabe').value, sw = zahl(tr.querySelector('.schwund').value);
        var verkauft = modus() === 'verkauft' ? zahl(ein) : (ein === '' ? 0 : bestand - zahl(ein) - sw);
        if (verkauft < 0 || verkauft + sw > bestand + 0.0001) fehler.push(tr.dataset.nr + ': mehr als beim Händler liegt');
        verkauft = Math.max(0, verkauft);
        tr.dataset.verkauft = verkauft;
        tr.querySelector('.verrechnet').textContent = String(verkauft).replace('.', ',');
        var s = verkauft * zahl(tr.querySelector('.preis').value);
        tr.querySelector('.summe').textContent = verkauft ? '€ ' + s.toFixed(2).replace('.', ',') : '–';
        stueck += verkauft; summe += s;
    });
    document.getElementById('gesamt').textContent = stueck ? stueck + ' Stk · € ' + summe.toFixed(2).replace('.', ',') + ' netto' : '';
    document.getElementById('fehler').textContent = fehler.join(' · ');
    return fehler;
}
function modusWechsel() {
    document.getElementById('kopf-eingabe').textContent = modus() === 'verkauft' ? 'VERKAUFT' : 'NOCH DA';
    zeilen().forEach(tr => tr.querySelector('.eingabe').value = '');
    rechnen();
}
document.addEventListener('input', function (e) { if (e.target.closest('#zeilen')) rechnen(); });
document.getElementById('scan').addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    var code = this.value.trim(); this.value = '';
    var tr = zeilen().find(z => z.dataset.ean === code || z.dataset.nr.toUpperCase() === code.toUpperCase());
    if (!tr) { document.getElementById('fehler').textContent = 'Nicht beim Händler: ' + code; return; }
    var f = tr.querySelector('.eingabe'); f.value = (parseInt(f.value, 10) || 0) + 1; tr.style.background = 'var(--color-bg-hover, #eef6ff)'; rechnen();
});

async function abrechnen() {
    if (rechnen().length) return;
    var verkauf = [], schwund = [];
    zeilen().forEach(function (tr) {
        var v = parseFloat(tr.dataset.verkauft) || 0, sw = zahl(tr.querySelector('.schwund').value);
        var preis = tr.querySelector('.preis').value, original = tr.dataset.preis;
        // Preis nur mitsenden, wenn geändert -- sonst gilt je Lieferung ihr eigener Preis (FIFO)
        if (v > 0) verkauf.push({ artikel_id: parseInt(tr.dataset.artikel, 10), menge: v, preis_netto: zahl(preis).toFixed(2) === parseFloat(original).toFixed(2) ? null : preis });
        if (sw > 0) schwund.push({ artikel_id: parseInt(tr.dataset.artikel, 10), menge: sw });
    });
    if (!verkauf.length && !schwund.length) { document.getElementById('fehler').textContent = 'Nichts eingetragen.'; return; }
    var notiz = document.getElementById('notiz').value;
    if (schwund.length && !notiz.trim()) { document.getElementById('fehler').textContent = 'Für Schwund bitte den Grund in die Notiz schreiben.'; return; }
    var post = d => fetch(window.BASE_PATH + '/haendler/aktion.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(d) }).then(r => r.json());
    if (schwund.length) {
        var s = await post({ aktion: 'buchen', typ: 'schwund', kunde_id: KUNDE, positionen: schwund, notiz: notiz });
        if (!s.erfolg) { document.getElementById('fehler').textContent = 'Schwund: ' + (s.fehler || []).join(' '); return; }
    }
    if (verkauf.length) {
        var v = await post({ aktion: 'verkauf', kunde_id: KUNDE, positionen: verkauf, notiz: notiz });
        if (!v.erfolg) { document.getElementById('fehler').textContent = (v.fehler || ['Fehler']).join(' ') + (schwund.length ? ' (Schwund wurde bereits gebucht)' : ''); return; }
        if (!v.rechnung) alert('Auftrag ' + v.auftrag_nr + ' angelegt, aber die Rechnung konnte nicht erstellt werden: ' + (v.rechnung_fehler || '') + ' — bitte im Auftrag erstellen.');
        location.href = window.BASE_PATH + '/auftraege/detail.php?id=' + v.auftrag_id;
        return;
    }
    location.href = window.BASE_PATH + '/haendler/detail.php?kunde_id=' + KUNDE + '&tab=belege';
}
rechnen();
</script>
<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
