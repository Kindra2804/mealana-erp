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
// Für "+ Neuer Artikel" direkt beim Übernehmen (gleiches Speichern wie Reiter "Artikel")
$steuerklassen = Database::getInstance()->query("SELECT id, name, satz FROM steuerklassen ORDER BY satz DESC")->fetchAll(PDO::FETCH_ASSOC);
$plaetze       = $svc->getPlaetze($id);
$istKommission = $partner['typ'] === 'kommission';

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
    <input type="text" id="scan" class="erp-input" style="margin-top:10px;width:420px" placeholder="EAN scannen oder Nummer/Name tippen → Liste filtert, Enter = +1" autocomplete="off" autofocus>
    <span id="scan-info" style="font-size:12px;color:var(--color-text-muted);margin-left:8px"></span>
    <?php if ($belegTyp === 'uebernahme'): ?>
    <button type="button" class="btn btn-secondary btn-sm" style="margin-left:8px" onclick="naToggle()">+ Neuer Artikel</button>

    <!-- Neuer Partner-Artikel ohne Umweg über den Reiter "Artikel": landet sofort mit Menge 1 in der Liste -->
    <div id="na-box" style="display:none;margin-top:12px;padding:12px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:2;min-width:200px"><label class="erp-label">Bezeichnung *</label>
                <input type="text" id="na-name" class="erp-input" style="width:100%;box-sizing:border-box" maxlength="200"></div>
            <div><label class="erp-label">VK brutto *</label><input type="text" id="na-preis" class="erp-input" style="width:90px" placeholder="0,00"></div>
            <div><label class="erp-label">MwSt</label>
                <select id="na-steuer" class="erp-select">
                    <?php foreach ($steuerklassen as $s): ?><option value="<?= $s['id'] ?>"><?= rtrim(rtrim(number_format((float)$s['satz'], 1, ',', ''), '0'), ',') ?> %</option><?php endforeach; ?>
                </select></div>
            <div><label class="erp-label">Nummer (leer = auto)</label>
                <div style="display:flex;align-items:center;gap:3px"><code style="font-size:12px"><?= PartnerLagerService::nummernPraefix($id) ?></code>
                <input type="text" id="na-suffix" class="erp-input" style="width:100px" maxlength="40"></div></div>
            <div><label class="erp-label">EAN</label><input type="text" id="na-ean" class="erp-input" style="width:130px" maxlength="20"></div>
            <div><label class="erp-label">Fach</label>
                <select id="na-fach" class="erp-select"><option value="">—</option>
                    <?php foreach ($plaetze as $lp): ?><option value="<?= $lp['id'] ?>"><?= htmlspecialchars($lp['bezeichnung']) ?></option><?php endforeach; ?>
                </select></div>
            <?php if ($istKommission): ?>
            <div><label class="erp-label">EK netto</label><input type="text" id="na-ek" class="erp-input" style="width:80px" placeholder="0,00"></div>
            <?php endif; ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="naSpeichern()">Anlegen + in Liste</button>
        </div>
        <div id="na-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>
    </div>
    <?php endif; ?>
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
            <tr data-artikel="<?= (int)$a['id'] ?>" data-ean="<?= htmlspecialchars($a['ean'] ?? '') ?>" data-nr="<?= htmlspecialchars($a['artikelnummer']) ?>"
                data-name="<?= htmlspecialchars($a['name']) ?>" data-bestand="<?= (float)$a['bestand'] ?>">
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
// Scan/Suche: nur Artikel dieses Partners (die Tabelle enthält nichts anderes).
// Tippen filtert nach Name, Artikelnummer (mit oder ohne XP-Präfix) und EAN;
// Enter = +1, wenn der Code exakt passt oder genau eine Zeile übrig ist.
var PRAEFIX = <?= json_encode(PartnerLagerService::nummernPraefix($id)) ?>;
var zeilen  = Array.from(document.querySelectorAll('#beleg-tabelle tbody tr[data-artikel]'));
var scan    = document.getElementById('scan');

function scanNorm(s) { return s.trim().toUpperCase(); }
function scanExakt(tr, code) {
    var nr = tr.dataset.nr.toUpperCase();
    return tr.dataset.ean === code || nr === code || nr === PRAEFIX + code;
}
function scanPasst(tr, q) {
    return tr.dataset.name.toUpperCase().indexOf(q) !== -1
        || tr.dataset.nr.toUpperCase().indexOf(q) !== -1
        || (tr.dataset.ean && tr.dataset.ean.indexOf(q) !== -1);
}
function scanFiltern() {
    var q = scanNorm(scan.value), n = 0;
    zeilen.forEach(function (tr) {
        var sichtbar = !q || scanPasst(tr, q);
        tr.style.display = sichtbar ? '' : 'none';
        if (sichtbar) n++;
    });
    document.getElementById('scan-info').textContent = q ? n + ' Treffer' : '';
    return zeilen.filter(function (tr) { return tr.style.display !== 'none'; });
}

scan.addEventListener('input', scanFiltern);
scan.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { scan.value = ''; scanFiltern(); return; }
    if (e.key !== 'Enter') return;
    var code = scanNorm(scan.value);
    if (!code) return;
    var fehler = document.getElementById('fehler');
    var zeile  = zeilen.find(function (tr) { return scanExakt(tr, code); });
    if (!zeile) {
        var treffer = scanFiltern();
        if (treffer.length === 1) zeile = treffer[0];
        else {
            fehler.textContent = treffer.length
                ? treffer.length + ' Artikel passen — bitte genauer tippen oder die Menge direkt in der Zeile eintragen.'
                : 'Kein Artikel dieses Partners mit „' + scan.value.trim() + '“.';
            return;
        }
    }
    fehler.textContent = '';
    scan.value = '';
    scanFiltern();
    var m = zeile.querySelector('.menge'); m.value = (parseInt(m.value, 10) || 0) + 1;
    zeile.style.background = '#e6f7ee'; zeile.scrollIntoView({ block: 'center' });
});
function naToggle() {
    var box = document.getElementById('na-box');
    box.style.display = box.style.display === 'none' ? '' : 'none';
    if (box.style.display === '') {
        // Gesuchter Text, der nichts gefunden hat, ist meist schon die Bezeichnung
        if (scan.value.trim() && !document.getElementById('na-name').value) document.getElementById('na-name').value = scan.value.trim();
        document.getElementById('na-name').focus();
    }
}
function naSpeichern() {
    var ek = document.getElementById('na-ek');
    var daten = {
        partner_id: <?= $id ?>, id: null,
        nummer_suffix: document.getElementById('na-suffix').value,
        name: document.getElementById('na-name').value,
        brutto_vk: document.getElementById('na-preis').value,
        steuerklasse_id: document.getElementById('na-steuer').value,
        ean: document.getElementById('na-ean').value,
        stammplatz_id: document.getElementById('na-fach').value || null,
        aktiv: 1,
        ek_netto: ek ? ek.value : ''
    };
    fetch(window.BASE_PATH + '/partner/artikel_speichern.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) })
        .then(function (r) { return r.json(); }).then(function (d) {
            if (!d.erfolg) { document.getElementById('na-fehler').textContent = (d.fehler || ['Fehler']).join(' '); return; }
            // Neue Zeile oben einfügen, Menge 1
            var fach = document.getElementById('na-fach');
            var tr = document.createElement('tr');
            tr.dataset.artikel = d.id; tr.dataset.ean = daten.ean.trim(); tr.dataset.nr = d.artikelnummer;
            tr.dataset.name = daten.name.trim(); tr.dataset.bestand = 0;
            tr.innerHTML = '<td style="font-family:monospace"></td><td style="font-family:monospace;font-size:12px"></td><td></td>'
                + '<td><input type="text" class="erp-input charge" style="width:110px" placeholder="optional"></td>'
                + '<td style="text-align:right">0</td>'
                + '<td><input type="number" class="erp-input menge" style="width:90px" min="0" step="1" value="1"></td>';
            tr.children[0].textContent = fach.value ? fach.options[fach.selectedIndex].text : '–';
            tr.children[1].textContent = d.artikelnummer;
            tr.children[2].textContent = daten.name.trim() + ' (neu)';
            tr.style.background = '#e6f7ee';
            var tbody = document.querySelector('#beleg-tabelle tbody');
            var leer = tbody.querySelector('tr:not([data-artikel])');
            if (leer) leer.remove();
            tbody.insertBefore(tr, tbody.firstChild);
            zeilen.unshift(tr);
            ['na-name', 'na-preis', 'na-suffix', 'na-ean'].forEach(function (i) { document.getElementById(i).value = ''; });
            if (ek) ek.value = '';
            document.getElementById('na-fehler').textContent = '';
            document.getElementById('na-box').style.display = 'none';
            scan.value = ''; scanFiltern(); scan.focus();
        });
}
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
