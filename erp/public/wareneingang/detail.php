<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/wareneingang/WareneingangService.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';
require_once __DIR__ . '/../../src/core/Database.php';

$service       = new WareneingangService();
$bestService   = new BestellungService();

$bestellungId  = (int)($_GET['bestellung_id'] ?? 0);
if (!$bestellungId) { header('Location: /mealana/wareneingang/index.php'); exit; }

$bestellung = $bestService->getById($bestellungId);
if (!$bestellung || !in_array($bestellung['status'], ['offen', 'teilgeliefert'])) {
    header('Location: /mealana/wareneingang/index.php');
    exit;
}

$positionen = $service->getPositionenMitArtikel($bestellungId);
$lager      = $service->getAlleLager();
$nr         = BestellungService::bestellnummer($bestellungId, $bestellung['bestelldatum']);

$erfolg = $_SESSION['erfolg'] ?? '';
$fehler = $_SESSION['fehler'] ?? [];
unset($_SESSION['erfolg'], $_SESSION['fehler']);

// Vorausgewählter Artikel nach EAN-Scan vom Index
$scanArtikelId = (int)($_GET['scan_artikel_id'] ?? 0);
$scanEan       = $_GET['scan_ean'] ?? '';

$pageTitle    = 'Wareneingang — ' . $nr;
$activeModule = 'einkauf';
$actionBarContent = '<a href="/mealana/wareneingang/index.php" class="btn btn-secondary btn-sm">← Wareneingang</a>'
    . ' <a href="/mealana/bestellungen/detail.php?id=' . $bestellungId . '" class="btn btn-secondary btn-sm">Bestellung</a>';
require_once __DIR__ . '/../includes/shell_top.php';

// Aktuell gescannter Artikel (aus Session oder GET-Parameter)
$aktivArtikelId  = $scanArtikelId ?: ($_SESSION['we_aktiv_artikel'] ?? 0);
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
?>

<?php if ($erfolg): ?>
    <div id="msg-banner" class="card" style="border-left:3px solid var(--color-success);margin-bottom:8px;padding:8px 14px;color:var(--color-success)"><?= htmlspecialchars($erfolg) ?></div>
    <script>setTimeout(function(){ var b=document.getElementById('msg-banner'); if(b) b.style.display='none'; }, 3000);</script>
<?php endif; ?>

<!-- Lager-Auswahl -->
<div class="card" style="margin-bottom:10px;display:flex;align-items:center;gap:12px">
    <label style="font-size:13px;font-weight:500;white-space:nowrap">Ziellager:</label>
    <select id="lager-select" class="erp-select" style="min-width:200px">
        <?php foreach ($lager as $l): ?>
            <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <span style="font-size:12px;color:var(--color-text-muted)"><?= htmlspecialchars($nr) ?> — <?= htmlspecialchars($bestellung['lieferant_name']) ?></span>
</div>

<!-- Scan-Bereich -->
<div class="card" style="margin-bottom:10px">
    <div style="display:flex;gap:14px;align-items:flex-start">

        <!-- Artikelbild -->
        <div id="artikel-bild-box" style="width:90px;height:90px;background:#f0f0f0;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:28px">
            <?php if ($aktivArtikel && $aktivArtikel['hauptbild']): ?>
                <img src="/mealana/uploads/artikel/<?= $aktivArtikelId ?>/<?= htmlspecialchars($aktivArtikel['hauptbild']) ?>"
                     style="width:90px;height:90px;object-fit:cover;border-radius:6px">
            <?php else: ?>
                📦
            <?php endif; ?>
        </div>

        <div style="flex:1">
            <!-- EAN-Scan Eingabe -->
            <div style="display:flex;gap:8px;margin-bottom:10px">
                <input type="text" id="ean-scan" class="erp-input" style="flex:1;font-size:15px"
                       placeholder="EAN scannen oder eingeben..."
                       value="<?= htmlspecialchars($scanEan) ?>"
                       autofocus autocomplete="off">
                <button class="btn btn-secondary btn-sm" onclick="eanSuchen()">Suchen</button>
            </div>

            <!-- Artikel-Info -->
            <div id="artikel-info">
                <?php if ($aktivArtikel): ?>
                    <div style="font-weight:600;font-size:14px"><?= htmlspecialchars($aktivArtikel['artikel_name']) ?><?= $aktivArtikel['variante_name'] ? ' — ' . htmlspecialchars($aktivArtikel['variante_name']) : '' ?></div>
                    <div style="font-size:12px;color:var(--color-text-muted)">Bestellt: <?= (int)$aktivArtikel['menge_bestellt'] ?> &nbsp;|&nbsp; Offen: <?= (int)($aktivArtikel['menge_bestellt'] - $aktivArtikel['menge_eingegangen']) ?></div>
                <?php else: ?>
                    <div style="color:var(--color-text-muted);font-size:13px">Artikel scannen um Buchung zu starten</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Buchungs-Formular -->
    <div id="buchungs-form" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--color-border);display:<?= $aktivArtikel ? 'block' : 'none' ?>">
        <form method="post" action="/mealana/wareneingang/speichern.php" id="eingang-form">
            <input type="hidden" name="bestellung_id" value="<?= $bestellungId ?>">
            <input type="hidden" name="position_id"   id="position_id" value="<?= $aktivPositionId ?>">
            <input type="hidden" name="artikel_id"    id="artikel_id"  value="<?= $aktivArtikelId ?>">

            <div style="display:flex;gap:12px;align-items:flex-end">
                <div>
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Menge</label>
                    <input type="number" name="menge" id="menge-input" min="1" step="1" class="erp-input" style="width:100px;font-size:18px;font-weight:600;text-align:center" required placeholder="0">
                </div>
                <div style="flex:1">
                    <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">
                        Charge <?= $aktivArtikel && $aktivArtikel['charge_pflicht'] ? '(Pflicht)' : '(optional)' ?>
                    </label>
                    <div style="display:flex;gap:6px">
                        <select id="charge-select" name="charge" class="erp-select" style="flex:1" onchange="chargeGeaendert(this)">
                            <option value="">– keine / zu erfassen –</option>
                            <?php foreach ($chargen as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                            <option value="__neu__">+ Neue Charge...</option>
                        </select>
                        <input type="text" id="charge-neu" name="charge_neu" class="erp-input" style="display:none;flex:1" placeholder="Neue Charge eingeben">
                    </div>
                </div>
                <div>
                    <input type="hidden" name="lager_id" id="lager_id_hidden">
                    <button type="submit" class="btn btn-primary" style="padding:10px 24px;font-size:15px">Buchen</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Positionen Übersicht -->
<div class="card">
    <strong style="font-size:13px;display:block;margin-bottom:8px">Positionen</strong>
    <table class="erp-table" id="positionen-tabelle">
        <thead>
            <tr><th>Artikel</th><th>Bestellt</th><th>Eingeg.</th><th>Offen</th><th></th><th></th></tr>
        </thead>
        <tbody>
            <?php foreach ($positionen as $p):
                $offen = (float)$p['menge_bestellt'] - (float)$p['menge_eingegangen'];
                $icon  = $p['gestrichen'] ? '✕' : ($offen <= 0 ? '✅' : ($p['menge_eingegangen'] > 0 ? '🔄' : '⬜'));
                $klickbar = !$p['gestrichen'] && $offen > 0;
            ?>
                <tr data-artikel-id="<?= $p['artikel_id'] ?>"
                    data-position-id="<?= $p['id'] ?>"
                    data-charge-pflicht="<?= $p['charge_pflicht'] ?>"
                    data-hauptbild="<?= htmlspecialchars($p['hauptbild'] ?? '') ?>"
                    data-artikel-name="<?= htmlspecialchars($p['artikel_name'] . ($p['variante_name'] ? ' — ' . $p['variante_name'] : '')) ?>"
                    data-offen="<?= $offen ?>"
                    <?= $klickbar ? 'style="cursor:pointer" onclick="positionWaehlen(this)"' : ($p['gestrichen'] ? 'style="opacity:.4;text-decoration:line-through"' : 'style="opacity:.6"') ?>>
                    <td><?= htmlspecialchars($p['artikel_name']) ?><?= $p['variante_name'] ? ' <span style="font-size:11px;color:var(--color-text-muted)">— ' . htmlspecialchars($p['variante_name']) . '</span>' : '' ?></td>
                    <td><?= (int)$p['menge_bestellt'] ?></td>
                    <td><?= (int)$p['menge_eingegangen'] ?></td>
                    <td><?= $p['gestrichen'] ? '—' : (int)$offen ?></td>
                    <td><?= $icon ?></td>
                    <td onclick="event.stopPropagation()">
                        <a href="/mealana/wareneingang/artikel_bearbeiten_vorbereiten.php?artikel_id=<?= $p['artikel_id'] ?>&bestellung_id=<?= $bestellungId ?>"
                           class="btn btn-secondary btn-sm" style="padding:2px 8px;font-size:11px">✏</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Abschliessen -->
    <div style="margin-top:14px;text-align:right">
        <button class="btn btn-primary" onclick="abschliessenDialog()" id="btn-abschliessen">Bestellung abschliessen</button>
    </div>
</div>

<!-- Dialog: Abschliessen -->
<div id="abschluss-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:8px;padding:24px;width:420px;box-shadow:0 4px 24px rgba(0,0,0,.2)">
        <div id="abschluss-inhalt"></div>
    </div>
</div>

<script>
// Lager-ID hidden field synchron halten
document.getElementById('lager-select').addEventListener('change', syncLager);
function syncLager() {
    document.getElementById('lager_id_hidden').value = document.getElementById('lager-select').value;
}
syncLager();

// EAN Suche innerhalb der Bestellung
document.getElementById('ean-scan').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); eanSuchen(); }
});

function eanSuchen() {
    var ean = document.getElementById('ean-scan').value.trim();
    if (!ean) return;
    fetch('/mealana/wareneingang/artikel_suche.php?ean=' + encodeURIComponent(ean))
        .then(r => r.json())
        .then(data => {
            if (!data.gefunden) {
                alert('EAN nicht gefunden: ' + ean);
                return;
            }
            // Passende Position in Tabelle suchen
            var rows = document.querySelectorAll('#positionen-tabelle tbody tr[data-artikel-id="' + data.artikel.id + '"]');
            if (rows.length) {
                positionWaehlen(rows[0]);
            } else {
                alert('Artikel in dieser Bestellung nicht gefunden.');
            }
        });
}

function positionWaehlen(row) {
    var artikelId   = row.dataset.artikelId;
    var positionId  = row.dataset.positionId;
    var name        = row.dataset.artikelName;
    var hauptbild   = row.dataset.hauptbild;
    var chargePflicht = row.dataset.chargePflicht == '1';
    var offen       = row.dataset.offen;

    // Hidden fields setzen
    document.getElementById('position_id').value = positionId;
    document.getElementById('artikel_id').value  = artikelId;

    // Artikel-Info aktualisieren
    document.getElementById('artikel-info').innerHTML =
        '<div style="font-weight:600;font-size:14px">' + escHtml(name) + '</div>' +
        '<div style="font-size:12px;color:var(--color-text-muted)">Offen: ' + Math.round(offen) + ' Stk</div>';

    // Bild aktualisieren
    var bildBox = document.getElementById('artikel-bild-box');
    if (hauptbild) {
        bildBox.innerHTML = '<img src="/mealana/uploads/artikel/' + artikelId + '/' + escHtml(hauptbild) + '" style="width:90px;height:90px;object-fit:cover;border-radius:6px" onerror="this.parentElement.innerHTML=\'📦\'">';
    } else {
        bildBox.innerHTML = '📦';
    }

    // Chargen nachladen
    fetch('/mealana/wareneingang/chargen_ajax.php?artikel_id=' + artikelId)
        .then(r => r.json())
        .then(chargen => {
            var sel = document.getElementById('charge-select');
            sel.innerHTML = '<option value="">– keine / zu erfassen –</option>';
            chargen.forEach(function(c) {
                sel.innerHTML += '<option value="' + escHtml(c) + '">' + escHtml(c) + '</option>';
            });
            sel.innerHTML += '<option value="__neu__">+ Neue Charge...</option>';
        });

    // Charge-Label aktualisieren
    var chargeLabel = document.querySelector('label[for="charge-select"]');
    if (chargeLabel) chargeLabel.textContent = 'Charge ' + (chargePflicht ? '(Pflicht)' : '(optional)');

    // Menge vorschlagen (1)
    document.getElementById('menge-input').value = 1;
    document.getElementById('buchungs-form').style.display = 'block';
    document.getElementById('menge-input').focus();
    document.getElementById('menge-input').select();

    // Zeile hervorheben
    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(r => r.style.background = '');
    row.style.background = '#f0f6ff';
}

function chargeGeaendert(sel) {
    var neuInput = document.getElementById('charge-neu');
    if (sel.value === '__neu__') {
        neuInput.style.display = 'block';
        neuInput.required = true;
        neuInput.focus();
        sel.value = '';
    } else {
        neuInput.style.display = 'none';
        neuInput.required = false;
        neuInput.value = '';
    }
}

// Vor dem Submit: charge_neu → charge
document.getElementById('eingang-form').addEventListener('submit', function(e) {
    var neuInput = document.getElementById('charge-neu');
    var sel      = document.getElementById('charge-select');
    if (neuInput.style.display !== 'none' && neuInput.value.trim()) {
        sel.innerHTML += '<option value="' + escHtml(neuInput.value.trim()) + '" selected>' + escHtml(neuInput.value.trim()) + '</option>';
        sel.value = neuInput.value.trim();
        neuInput.style.display = 'none';
    }
});

// Abschliessen-Dialog
function abschliessenDialog() {
    var offenCount = 0;
    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(function(r) {
        if (r.dataset.offen > 0 && !r.dataset.positionId) return;
        if (parseFloat(r.dataset.offen || 0) > 0) offenCount++;
    });

    var html = '';
    if (offenCount === 0) {
        html = '<div style="font-weight:600;margin-bottom:12px">Alle Positionen vollständig eingegangen.</div>';
        html += '<form method="post" action="/mealana/wareneingang/abschliessen.php">';
        html += '<input type="hidden" name="bestellung_id" value="<?= $bestellungId ?>">';
        html += '<input type="hidden" name="aktion" value="komplett">';
        html += '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">';
        html += '<button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById(\'abschluss-modal\').style.display=\'none\'">Abbrechen</button>';
        html += '<button type="submit" class="btn btn-primary btn-sm">Bestellung abschliessen</button>';
        html += '</div></form>';
    } else {
        html = '<div style="font-weight:600;margin-bottom:8px">Nicht alle Positionen eingegangen (' + offenCount + ' offen)</div>';
        html += '<form method="post" action="/mealana/wareneingang/abschliessen.php">';
        html += '<input type="hidden" name="bestellung_id" value="<?= $bestellungId ?>">';
        html += '<div style="margin-bottom:12px">';
        html += '<label style="display:block;padding:10px;border:1px solid var(--color-border);border-radius:4px;cursor:pointer;margin-bottom:6px"><input type="radio" name="aktion" value="warten" checked style="margin-right:6px"> Auf Nachlieferung warten (Bestellung bleibt offen)</label>';
        html += '<label style="display:block;padding:10px;border:1px solid var(--color-border);border-radius:4px;cursor:pointer"><input type="radio" name="aktion" value="streichen" style="margin-right:6px"> Abschliessen — Rest streichen</label>';
        html += '</div>';
        html += '<div id="gutschrift-bereich" style="display:none;padding:10px;background:#fffbf0;border-radius:4px;margin-bottom:10px">';
        html += '<label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:4px">Gutschrift-Betrag (€)</label>';
        html += '<input type="number" name="gutschrift_betrag" step="0.01" class="erp-input" style="width:100%;margin-bottom:6px">';
        html += '<label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:4px">Notiz (z.B. Gutschrift erwartet von DROPS)</label>';
        html += '<input type="text" name="gutschrift_notiz" class="erp-input" style="width:100%">';
        html += '</div>';
        html += '<div style="display:flex;gap:8px;justify-content:flex-end">';
        html += '<button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById(\'abschluss-modal\').style.display=\'none\'">Abbrechen</button>';
        html += '<button type="submit" class="btn btn-primary btn-sm">Bestätigen</button>';
        html += '</div></form>';
    }

    document.getElementById('abschluss-inhalt').innerHTML = html;
    document.getElementById('abschluss-modal').style.display = 'flex';

    // Gutschrift-Bereich einblenden wenn "streichen" gewählt
    document.querySelectorAll('[name="aktion"]').forEach(function(r) {
        r.addEventListener('change', function() {
            document.getElementById('gutschrift-bereich').style.display = this.value === 'streichen' ? 'block' : 'none';
        });
    });
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>
