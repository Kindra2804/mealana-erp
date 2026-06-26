<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../../src/modules/wareneingang/WareneingangService.php';
require_once __DIR__ . '/../../../src/modules/bestellungen/BestellungService.php';
require_once __DIR__ . '/../../../src/core/Database.php';

$service     = new WareneingangService();
$bestService = new BestellungService();

$bestellungId = (int)($_GET['bestellung_id'] ?? 0);
if (!$bestellungId) { header('Location: index.php'); exit; }

$bestellung = $bestService->getById($bestellungId);
if (!$bestellung || !in_array($bestellung['status'], ['offen', 'teilgeliefert'])) {
    header('Location: index.php');
    exit;
}

$positionen = $service->getPositionenMitArtikel($bestellungId);
$lager      = $service->getAlleLager();
$nr         = BestellungService::bestellnummer($bestellungId, $bestellung['bestelldatum']);

$erfolg = $_SESSION['erfolg'] ?? '';
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

$scanArtikelId = (int)($_GET['scan_artikel_id'] ?? 0);
$scanEan       = $_GET['scan_ean'] ?? '';

$aktivArtikelId  = $scanArtikelId;
$aktivPositionId = 0;
$aktivArtikel    = null;
foreach ($positionen as $p) {
    if ($aktivArtikelId && $p['artikel_id'] == $aktivArtikelId && !$p['gestrichen'] && $p['menge_eingegangen'] < $p['menge_bestellt']) {
        $aktivPositionId = $p['id'];
        $aktivArtikel    = $p;
        break;
    }
}
$chargen = $aktivArtikelId ? $service->getChargenFuerArtikel($aktivArtikelId) : [];

$pageTitle = 'WE — ' . $nr;
$backUrl   = '/mealana/packplatz/wareneingang/index.php';
$headerSub = $nr . ' · ' . htmlspecialchars($bestellung['lieferant_name'] ?? '');
require_once __DIR__ . '/../shell_top.php';
?>

<style>
.we-card { background:#16213e; border:1px solid #0f3460; border-radius:10px; padding:16px 20px; margin-bottom:14px; }
.we-input { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:20px; padding:10px 14px; outline:none; }
.we-input:focus { border-color:#e94560; }
.we-select { background:#0a0a1a; border:2px solid #0f3460; border-radius:8px; color:#fff; font-size:16px; padding:10px 12px; outline:none; }
.we-select:focus { border-color:#e94560; }
.we-label { font-size:12px; color:#aaa; display:block; margin-bottom:4px; }
.we-table { width:100%; border-collapse:collapse; }
.we-table th { background:#0f3460; color:#aaa; font-size:12px; text-align:left; padding:8px 12px; text-transform:uppercase; letter-spacing:.5px; }
.we-table td { padding:11px 12px; border-bottom:1px solid #1a1a3e; font-size:15px; vertical-align:middle; }
.we-table tr.we-offen td { color:#eee; }
.we-table tr.we-teilweise td { background:#1a2a0a; color:#8bc34a; }
.we-table tr.we-fertig td { background:#0d2d1a; color:#4caf50; opacity:.7; }
.we-table tr.we-aktiv td { background:#1e2a4e; }
.we-table tr[data-klickbar="1"] { cursor:pointer; }
.we-table tr[data-klickbar="1"]:hover td { background:#1e2a4e; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.85); z-index:100; align-items:center; justify-content:center; }
.modal-overlay.aktiv { display:flex; }
.modal-box { background:#16213e; border:2px solid #e94560; border-radius:16px; padding:32px; text-align:center; min-width:360px; }
</style>

<?php if ($erfolg): ?>
<div id="msg-banner" style="background:#0d2d1a;border:1px solid #4caf50;border-radius:8px;padding:10px 16px;margin-bottom:14px;color:#4caf50">
    <?= htmlspecialchars($erfolg) ?>
</div>
<?php endif; ?>
<?php if (!empty($fehler)): ?>
<div style="background:#2d0d0d;border:1px solid #e94560;border-radius:8px;padding:10px 16px;margin-bottom:14px;color:#ef5350">
    <?= is_array($fehler) ? implode('<br>', array_map('htmlspecialchars', $fehler)) : htmlspecialchars($fehler) ?>
</div>
<?php endif; ?>

<div style="max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1.6fr;gap:20px;align-items:start">

    <!-- LINKS: Scan-Bereich + Buchungs-Form -->
    <div>

        <!-- Lager-Auswahl -->
        <div class="we-card">
            <label class="we-label">Ziellager</label>
            <select id="lager-select" class="we-select" style="width:100%">
                <?php foreach ($lager as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- EAN-Scan -->
        <div class="we-card">
            <label class="we-label">EAN scannen oder eingeben</label>
            <div style="display:flex;gap:8px;margin-bottom:14px">
                <input type="text" id="ean-scan" class="we-input" style="flex:1"
                       placeholder="EAN…"
                       value="<?= htmlspecialchars($scanEan) ?>"
                       autofocus autocomplete="off">
                <button class="pp-btn pp-btn-secondary" style="padding:10px 18px;font-size:16px" onclick="eanSuchen()">→</button>
            </div>

            <!-- Artikel-Info + Bild -->
            <div style="display:flex;gap:14px;align-items:center">
                <div id="artikel-bild-box" class="pp-scan-bild-placeholder">
                    <?php if ($aktivArtikel && !empty($aktivArtikel['hauptbild'])): ?>
                        <img src="/mealana/uploads/artikel/<?= $aktivArtikelId ?>/<?= htmlspecialchars($aktivArtikel['hauptbild']) ?>"
                             class="pp-scan-bild">
                    <?php else: ?>📦<?php endif; ?>
                </div>
                <div id="artikel-info" style="flex:1">
                    <?php if ($aktivArtikel): ?>
                        <div style="font-weight:700;font-size:16px"><?= htmlspecialchars($aktivArtikel['artikel_name']) ?><?= $aktivArtikel['variante_name'] ? ' — ' . htmlspecialchars($aktivArtikel['variante_name']) : '' ?></div>
                        <div style="font-size:13px;color:#aaa;margin-top:4px">Bestellt: <?= (int)$aktivArtikel['menge_bestellt'] ?> &nbsp;·&nbsp; Offen: <?= (int)($aktivArtikel['menge_bestellt'] - $aktivArtikel['menge_eingegangen']) ?></div>
                    <?php else: ?>
                        <div style="color:#555;font-size:14px">Artikel scannen um Buchung zu starten</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Buchungs-Formular -->
        <div id="buchungs-form" class="we-card" style="display:<?= $aktivArtikel ? 'block' : 'none' ?>">
            <form method="post" action="/mealana/packplatz/wareneingang/speichern.php" id="eingang-form">
                <input type="hidden" name="bestellung_id" value="<?= $bestellungId ?>">
                <input type="hidden" name="position_id"   id="position_id" value="<?= $aktivPositionId ?>">
                <input type="hidden" name="artikel_id"    id="artikel_id"  value="<?= $aktivArtikelId ?>">
                <input type="hidden" name="lager_id"      id="lager_id_hidden">

                <div style="display:flex;flex-direction:column;gap:14px">
                    <div>
                        <label class="we-label">Menge</label>
                        <input type="number" name="menge" id="menge-input" min="1" step="1" class="we-input" style="width:100%;font-size:28px;font-weight:700;text-align:center" required placeholder="0">
                    </div>
                    <div>
                        <label class="we-label" for="charge-select">Charge (optional)</label>
                        <div style="display:flex;gap:8px">
                            <select id="charge-select" name="charge" class="we-select" style="flex:1" onchange="chargeGeaendert(this)">
                                <option value="">– keine / zu erfassen –</option>
                                <?php foreach ($chargen as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                                <option value="__neu__">+ Neue Charge...</option>
                            </select>
                            <input type="text" id="charge-neu" name="charge_neu" class="we-input" style="display:none;flex:1;font-size:16px" placeholder="Charge eingeben">
                        </div>
                    </div>
                    <button type="submit" class="pp-btn pp-btn-success" style="width:100%;font-size:22px;padding:18px">
                        ✓ Buchen
                    </button>
                </div>
            </form>
        </div>

    </div>

    <!-- RECHTS: Positionen-Liste -->
    <div class="we-card" style="margin-bottom:0">
        <div style="font-size:16px;font-weight:700;margin-bottom:12px;color:#e94560">Positionen</div>
        <table class="we-table" id="positionen-tabelle">
            <thead>
                <tr><th>Artikel</th><th>EAN</th><th>Best.</th><th>Eingeg.</th><th>Offen</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($positionen as $p):
                    $offen    = (float)$p['menge_bestellt'] - (float)$p['menge_eingegangen'];
                    $klickbar = !$p['gestrichen'] && $offen > 0;
                    $rowClass = $p['gestrichen'] ? '' : ($offen <= 0 ? 'we-fertig' : ($p['menge_eingegangen'] > 0 ? 'we-teilweise' : 'we-offen'));
                    if ($p['artikel_id'] == $aktivArtikelId && $klickbar) $rowClass = 'we-aktiv';
                    $icon = $p['gestrichen'] ? '✕' : ($offen <= 0 ? '✅' : ($p['menge_eingegangen'] > 0 ? '🔄' : ''));
                ?>
                    <tr class="<?= $rowClass ?>"
                        data-artikel-id="<?= $p['artikel_id'] ?>"
                        data-position-id="<?= $p['id'] ?>"
                        data-charge-pflicht="<?= $p['charge_pflicht'] ?>"
                        data-hauptbild="<?= htmlspecialchars($p['hauptbild'] ?? '') ?>"
                        data-artikel-name="<?= htmlspecialchars($p['artikel_name'] . ($p['variante_name'] ? ' — ' . $p['variante_name'] : '')) ?>"
                        data-offen="<?= $offen ?>"
                        data-klickbar="<?= $klickbar ? 1 : 0 ?>"
                        <?= $klickbar ? 'onclick="positionWaehlen(this)"' : '' ?>
                        <?= $p['gestrichen'] ? 'style="opacity:.4;text-decoration:line-through"' : '' ?>>
                        <td style="max-width:200px">
                            <div style="font-weight:600"><?= htmlspecialchars($p['artikel_name']) ?></div>
                            <?php if ($p['variante_name']): ?>
                                <div style="font-size:11px;color:#aaa">
                                    <span style="color:#6c8ebf"><?= htmlspecialchars($p['kind_artikelnummer']) ?></span>
                                    — <?= htmlspecialchars($p['variante_name']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;white-space:nowrap" onclick="event.stopPropagation()">
                            <?php if (!empty($p['ean'])): ?>
                                <span style="color:#6c8ebf"><?= htmlspecialchars($p['ean']) ?></span>
                            <?php elseif (!$p['gestrichen']): ?>
                                <button class="pp-btn" onclick="eanModal(<?= (int)$p['artikel_id'] ?>, this)"
                                    style="font-size:11px;padding:3px 8px;background:#3a2200;border-color:#ff9800;color:#ff9800">
                                    + EAN
                                </button>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$p['menge_bestellt'] ?></td>
                        <td><?= (int)$p['menge_eingegangen'] ?></td>
                        <td style="font-weight:700;color:<?= $offen > 0 ? '#ff9800' : '#4caf50' ?>"><?= $p['gestrichen'] ? '—' : (int)$offen ?></td>
                        <td style="font-size:18px"><?= $icon ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:16px;text-align:right">
            <button class="pp-btn pp-btn-warning" onclick="abschliessenDialog()">
                Bestellung abschliessen
            </button>
        </div>
    </div>

</div>

<!-- EAN-Nachtragen-Modal -->
<div id="ean-modal" class="modal-overlay" onclick="if(event.target===this)eanModalSchliessen()">
    <div class="modal-box" style="min-width:320px">
        <div style="font-size:16px;font-weight:700;margin-bottom:16px;color:#e94560">EAN nachtragen</div>
        <input type="hidden" id="ean-artikel-id">
        <input type="text" id="ean-input" class="we-input" style="width:100%;font-size:20px;text-align:center;letter-spacing:2px"
               placeholder="EAN scannen / eingeben" autocomplete="off">
        <div style="display:flex;gap:10px;margin-top:16px">
            <button class="pp-btn pp-btn-success" style="flex:1;font-size:16px;padding:12px" onclick="eanSpeichern()">✓ Speichern</button>
            <button class="pp-btn pp-btn-secondary" style="padding:12px 18px" onclick="eanModalSchliessen()">✕</button>
        </div>
        <div id="ean-fehler" style="color:#ef5350;font-size:13px;margin-top:10px;display:none"></div>
    </div>
</div>

<!-- Abschluss-Modal (dunkles Design) -->
<div id="abschluss-modal" class="modal-overlay" onclick="if(event.target===this)document.getElementById('abschluss-modal').classList.remove('aktiv')">
    <div class="modal-box">
        <div id="abschluss-inhalt"></div>
    </div>
</div>

<script>
window.WE_BESTELLUNG_ID    = <?= (int)$bestellungId ?>;
window.WE_ABSCHLIESSEN_URL = '/mealana/packplatz/wareneingang/abschliessen.php';
</script>
<script>
// Lager-Sync
document.getElementById('lager-select').addEventListener('change', syncLager);
function syncLager() {
    document.getElementById('lager_id_hidden').value = document.getElementById('lager-select').value;
}
syncLager();

// EAN-Scan
document.getElementById('ean-scan').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); eanSuchen(); }
});

function eanSuchen() {
    var ean = document.getElementById('ean-scan').value.trim();
    if (!ean) return;
    fetch('/mealana/wareneingang/artikel_suche.php?ean=' + encodeURIComponent(ean))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.gefunden) { alert('EAN nicht gefunden: ' + ean); return; }
            var rows = document.querySelectorAll('#positionen-tabelle tbody tr[data-artikel-id="' + data.artikel.id + '"]');
            if (rows.length) positionWaehlen(rows[0]);
            else alert('Artikel in dieser Bestellung nicht gefunden.');
        });
}

function positionWaehlen(row) {
    if (row.dataset.klickbar !== '1') return;
    var artikelId     = row.dataset.artikelId;
    var positionId    = row.dataset.positionId;
    var name          = row.dataset.artikelName;
    var hauptbild     = row.dataset.hauptbild;
    var chargePflicht = row.dataset.chargePflicht == '1';
    var offen         = row.dataset.offen;

    document.getElementById('position_id').value = positionId;
    document.getElementById('artikel_id').value  = artikelId;

    document.getElementById('artikel-info').innerHTML =
        '<div style="font-weight:700;font-size:16px">' + escH(name) + '</div>' +
        '<div style="font-size:13px;color:#aaa;margin-top:4px">Offen: ' + Math.round(offen) + ' Stk</div>';

    var bildBox = document.getElementById('artikel-bild-box');
    if (hauptbild) {
        bildBox.innerHTML = '<img src="/mealana/uploads/artikel/' + artikelId + '/' + escH(hauptbild) + '" class="pp-scan-bild" onerror="this.parentElement.innerHTML=\'📦\'">';
    } else {
        bildBox.innerHTML = '📦';
        bildBox.className = 'pp-scan-bild-placeholder';
    }

    fetch('/mealana/wareneingang/chargen_ajax.php?artikel_id=' + artikelId)
        .then(function (r) { return r.json(); })
        .then(function (chargen) {
            var sel = document.getElementById('charge-select');
            sel.innerHTML = '<option value="">– keine / zu erfassen –</option>';
            chargen.forEach(function (c) {
                sel.innerHTML += '<option value="' + escH(c) + '">' + escH(c) + '</option>';
            });
            sel.innerHTML += '<option value="__neu__">+ Neue Charge...</option>';
        });

    var chargeLabel = document.querySelector('label[for="charge-select"]');
    if (chargeLabel) chargeLabel.textContent = 'Charge ' + (chargePflicht ? '(Pflicht)' : '(optional)');

    document.getElementById('menge-input').value = 1;
    document.getElementById('buchungs-form').style.display = 'block';
    document.getElementById('menge-input').focus();
    document.getElementById('menge-input').select();

    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(function (r) { r.classList.remove('we-aktiv'); });
    row.classList.add('we-aktiv');
}

function chargeGeaendert(sel) {
    var neuInput = document.getElementById('charge-neu');
    if (sel.value === '__neu__') {
        neuInput.style.display = 'block'; neuInput.required = true; neuInput.focus(); sel.value = '';
    } else {
        neuInput.style.display = 'none'; neuInput.required = false; neuInput.value = '';
    }
}

document.getElementById('eingang-form').addEventListener('submit', function () {
    var neuInput = document.getElementById('charge-neu');
    var sel = document.getElementById('charge-select');
    if (neuInput.style.display !== 'none' && neuInput.value.trim()) {
        sel.innerHTML += '<option value="' + escH(neuInput.value.trim()) + '" selected>' + escH(neuInput.value.trim()) + '</option>';
        sel.value = neuInput.value.trim();
        neuInput.style.display = 'none';
    }
});

function abschliessenDialog() {
    var offenCount = 0;
    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(function (r) {
        if (parseFloat(r.dataset.offen || 0) > 0) offenCount++;
    });
    var bId  = window.WE_BESTELLUNG_ID;
    var url  = window.WE_ABSCHLIESSEN_URL;
    var html = '';
    var btnStyle = 'background:#e94560;color:#fff;border:none;border-radius:8px;padding:12px 24px;font-size:16px;font-weight:700;cursor:pointer';
    var btnSecStyle = 'background:#0f3460;color:#eee;border:none;border-radius:8px;padding:12px 24px;font-size:16px;cursor:pointer';

    if (offenCount === 0) {
        html  = '<div style="font-size:18px;font-weight:700;margin-bottom:16px;color:#eee">Alle Positionen vollständig eingegangen.</div>';
        html += '<form method="post" action="' + url + '">';
        html += '<input type="hidden" name="bestellung_id" value="' + bId + '">';
        html += '<input type="hidden" name="aktion" value="komplett">';
        html += '<div style="display:flex;gap:10px;justify-content:center;margin-top:16px">';
        html += '<button type="button" style="' + btnSecStyle + '" onclick="document.getElementById(\'abschluss-modal\').classList.remove(\'aktiv\')">Abbrechen</button>';
        html += '<button type="submit" style="' + btnStyle + '">Abschliessen ✓</button>';
        html += '</div></form>';
    } else {
        html  = '<div style="font-size:18px;font-weight:700;margin-bottom:12px;color:#eee">' + offenCount + ' Position(en) noch offen</div>';
        html += '<form method="post" action="' + url + '">';
        html += '<input type="hidden" name="bestellung_id" value="' + bId + '">';
        html += '<div style="text-align:left;margin-bottom:16px;display:flex;flex-direction:column;gap:10px">';
        html += '<label style="display:block;padding:12px 16px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee"><input type="radio" name="aktion" value="warten" checked style="margin-right:8px"> Auf Nachlieferung warten</label>';
        html += '<label style="display:block;padding:12px 16px;border:2px solid #0f3460;border-radius:8px;cursor:pointer;color:#eee"><input type="radio" name="aktion" value="streichen" style="margin-right:8px"> Rest streichen &amp; abschliessen</label>';
        html += '</div>';
        html += '<div id="gutschrift-bereich" style="display:none;padding:12px;background:#0f1a2e;border-radius:8px;margin-bottom:12px;text-align:left">';
        html += '<label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Gutschrift-Betrag (€)</label>';
        html += '<input type="number" name="gutschrift_betrag" step="0.01" style="width:100%;margin-bottom:8px;background:#0a0a1a;border:2px solid #0f3460;border-radius:8px;color:#fff;font-size:16px;padding:10px;outline:none">';
        html += '<label style="font-size:12px;color:#aaa;display:block;margin-bottom:4px">Notiz</label>';
        html += '<input type="text" name="gutschrift_notiz" style="width:100%;background:#0a0a1a;border:2px solid #0f3460;border-radius:8px;color:#fff;font-size:16px;padding:10px;outline:none">';
        html += '</div>';
        html += '<div style="display:flex;gap:10px;justify-content:center">';
        html += '<button type="button" style="' + btnSecStyle + '" onclick="document.getElementById(\'abschluss-modal\').classList.remove(\'aktiv\')">Abbrechen</button>';
        html += '<button type="submit" style="' + btnStyle + '">Bestätigen</button>';
        html += '</div></form>';
    }

    document.getElementById('abschluss-inhalt').innerHTML = html;
    document.getElementById('abschluss-modal').classList.add('aktiv');

    document.querySelectorAll('[name="aktion"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('gutschrift-bereich').style.display = this.value === 'streichen' ? 'block' : 'none';
        });
    });
}

function escH(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

setTimeout(function () { var b = document.getElementById('msg-banner'); if (b) b.style.display = 'none'; }, 3000);

// EAN-Nachtragen
var eanBtn = null;
function eanModal(artikelId, btn) {
    eanBtn = btn;
    document.getElementById('ean-artikel-id').value = artikelId;
    document.getElementById('ean-input').value = '';
    document.getElementById('ean-fehler').style.display = 'none';
    document.getElementById('ean-modal').classList.add('aktiv');
    setTimeout(function () { document.getElementById('ean-input').focus(); }, 50);
}
function eanModalSchliessen() {
    document.getElementById('ean-modal').classList.remove('aktiv');
}
document.getElementById('ean-input').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); eanSpeichern(); }
});
async function eanSpeichern() {
    var artikelId = document.getElementById('ean-artikel-id').value;
    var ean       = document.getElementById('ean-input').value.trim();
    var fehlerEl  = document.getElementById('ean-fehler');
    if (!ean) { fehlerEl.textContent = 'Bitte EAN eingeben.'; fehlerEl.style.display = 'block'; return; }
    var body = new FormData();
    body.append('artikel_id', artikelId);
    body.append('ean', ean);
    var r    = await fetch('/mealana/packplatz/wareneingang/ean_nachtragen.php', { method: 'POST', body });
    var data = await r.json();
    if (data.erfolg) {
        if (eanBtn) {
            eanBtn.parentElement.innerHTML = '<span style="color:#6c8ebf">' + escH(ean) + '</span>';
        }
        eanModalSchliessen();
    } else {
        fehlerEl.textContent = data.fehler || 'Fehler beim Speichern.';
        fehlerEl.style.display = 'block';
    }
}
</script>

<?php require_once __DIR__ . '/../shell_bottom.php'; ?>
