<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/bestellungen/BestellungService.php';

$service     = new BestellungService();
$lieferanten = $service->getAlleLieferanten();
$fehler      = $_SESSION['fehler']   ?? [];
$formdata    = $_SESSION['formdata'] ?? [];
unset($_SESSION['fehler'], $_SESSION['formdata']);

$pageTitle        = 'Neue Bestellung';
$activeModule     = 'einkauf';
$actionBarContent = <<<HTML
<button form="bestellung-form" type="submit" class="btn btn-primary btn-sm">Speichern</button>
<a href="/mealana/bestellungen/liste.php" class="btn btn-secondary btn-sm">Abbrechen</a>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
?>

<?php if (!empty($fehler)): ?>
    <div class="card" style="border-left:3px solid var(--color-danger);margin-bottom:12px">
        <?php foreach ($fehler as $f): ?>
            <p style="color:var(--color-danger);margin:4px 0"><?= htmlspecialchars($f) ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form id="bestellung-form" method="post" action="/mealana/bestellungen/speichern.php">

    <div class="card" style="margin-bottom:12px">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferant *</label>
                <select name="lieferant_id" id="lieferant_id" class="erp-select" style="width:100%" required onchange="ladeReserviert(this.value)">
                    <option value="">– Lieferant wählen –</option>
                    <?php foreach ($lieferanten as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($formdata['lieferant_id'] ?? '') == $l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Bestelldatum *</label>
                <input type="date" name="bestelldatum" class="erp-input" style="width:100%" required value="<?= htmlspecialchars($formdata['bestelldatum'] ?? date('Y-m-d')) ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Zahlungsart</label>
                <select name="zahlungsart" class="erp-select" style="width:100%">
                    <option value="">– wählen –</option>
                    <?php foreach (['vorkasse' => 'Vorkasse', 'rechnung' => 'Rechnung', 'lastschrift' => 'Lastschrift'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($formdata['zahlungsart'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Erwartet am</label>
                <input type="date" name="erwartet_am" class="erp-input" style="width:100%" value="<?= htmlspecialchars($formdata['erwartet_am'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Lieferzeit (Freitext)</label>
                <input type="text" name="lieferzeit_text" class="erp-input" style="width:100%" placeholder="z.B. ab KW38" value="<?= htmlspecialchars($formdata['lieferzeit_text'] ?? '') ?>">
            </div>
            <div>
                <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">AB-Nummer (Lieferant)</label>
                <input type="text" name="ab_nummer" class="erp-input" style="width:100%" value="<?= htmlspecialchars($formdata['ab_nummer'] ?? '') ?>">
            </div>
        </div>
        <div style="margin-top:10px">
            <label style="font-size:12px;color:var(--color-text-muted);display:block;margin-bottom:3px">Notiz</label>
            <textarea name="notiz" class="erp-input" style="width:100%;height:50px;resize:vertical"><?= htmlspecialchars($formdata['notiz'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Reserviert Infobox -->
    <div id="reserviert-box" style="display:none;margin-bottom:12px"></div>

    <!-- Positionen -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <strong style="font-size:13px">Positionen</strong>
            <button type="button" class="btn btn-secondary btn-sm" onclick="positionHinzufuegen()">+ Position</button>
        </div>

        <div id="positionen-container">
            <!-- wird per JS befüllt -->
        </div>

        <div style="margin-top:12px;text-align:right;font-size:13px;color:var(--color-text-muted)">
            Gesamt EK (netto): <strong id="gesamt-ek" style="color:var(--color-nav)">0,00 €</strong>
        </div>
    </div>

</form>

<script>
    // var artikelListe = [];

    var posCount = 0;

    // function ladeArtikel(lieferantId) {
    //     if (!lieferantId) { artikelListe = []; return; }
    //     fetch('/mealana/bestellungen/artikel_ajax.php?lieferant_id=' + lieferantId)
    //         .then(r => r.json())
    //         .then(data => { artikelListe = data; });
    // }

    function ladeReserviert(lieferantId) {
        var box = document.getElementById('reserviert-box');
        if (!lieferantId) {
            box.style.display = 'none';
            box.innerHTML = '';
            return;
        }
        fetch('/mealana/bestellungen/reserviert_ajax.php?lieferant_id=' + lieferantId)
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    box.style.display = 'none';
                    box.innerHTML = '';
                    return;
                }
                var html = '<div style="border-left:3px solid var(--color-warning);background:#fffbf0;padding:12px;border-radius:4px">';
                html += '<div style="font-weight:600;font-size:13px;margin-bottom:8px;color:#c0820a">⚠ Reserviert, nicht lagernd</div>';
                data.forEach(function(r) {
                    var vpe = r.vpe_menge ? parseInt(r.vpe_menge) : 1;
                    var benoetigt = Math.ceil(r.reserviert_gesamt / vpe);
                    var vorschlag = benoetigt * vpe;
                    var info = vpe > 1 ?
                        benoetigt + ' VPE nötig (= ' + vorschlag + ' Stk)' :
                        '1 VPE deckt Bedarf';
                    if (vpe > 1 && vorschlag > r.reserviert_gesamt) {
                        info = benoetigt + ' VPE nötig (= ' + vorschlag + ' Stk, ' + (vorschlag - r.reserviert_gesamt) + ' übrig)';
                    }
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0e8d0" data-artikel-id="' + r.id + '">';
                    html += '<div>';
                    html += '<div style="font-size:13px;font-weight:500">' + escHtml(r.artikel_name) + '</div>';
                    html += '<div style="font-size:12px;color:var(--color-text-muted)">Reserviert: ' + r.reserviert_gesamt + ' Stk &nbsp;|&nbsp; VPE: ' + vpe + ' Stk</div>';
                    html += '<div style="font-size:12px;color:#c0820a">→ ' + info + '</div>';
                    html += '</div>';
                    html += '<button type="button" class="btn btn-secondary btn-sm" onclick="reserviertUebernehmen(this,' + r.id + ',' + escHtml(JSON.stringify(r.artikel_name)) + ',' + vorschlag + ',' + (r.netto_ek || 0) + ')">+ Zur Bestellung</button>';
                    html += '</div>';
                });
                html += '</div>';
                box.innerHTML = html;
                box.style.display = 'block';
            });
    }

    function reserviertUebernehmen(btn, artikelId, artikelName, menge, ekPreis) {
        positionHinzufuegen(artikelId, artikelName, menge, ekPreis);
        var zeile = btn.closest('[data-artikel-id]');
        zeile.remove();
        var box = document.getElementById('reserviert-box');
        if (!box.querySelector('[data-artikel-id]')) box.style.display = 'none';
    }

    function positionHinzufuegen(artikelId, artikelName, menge, ekPreis) {
        var idx = posCount++;
        var div = document.createElement('div');
        div.id = 'pos-' + idx;
        div.style.cssText = 'display:grid;grid-template-columns:2fr 80px 120px 150px auto;gap:8px;align-items:end;padding:8px 0;border-bottom:1px solid var(--color-border)';

        div.innerHTML = `<div style="position:relative">
            <input type="text" style="width:100%"
                id="asuche-${idx}"
                name="positionen[${idx}][artikel_suche]"
                class="erp-input"
                placeholder="Artikel suchen..."
                autocomplete="off"
                oninput="artikelSuchen(this, ${idx})">
            <div id="dropdown-${idx}"
                style="display:none; position:absolute; top:100%; left:0; right:0;
                       background:#fff; border:1px solid var(--color-border);
                       border-radius:4px; z-index:100; max-height:200px; overflow-y:auto">
            </div>
            <input type="hidden" name="positionen[${idx}][artikel_id]" id="aid-${idx}" required>
        </div>
        <input name="positionen[${idx}][menge_bestellt]" type="number" min="1" step="1"
               class="erp-input" placeholder="Menge" value="${menge || ''}" required oninput="berechneGesamt()">
        <input name="positionen[${idx}][ek_preis]" type="number" min="0" step="0.0001"
               class="erp-input" placeholder="EK-Preis" value="${ekPreis || ''}" oninput="berechneGesamt()">
        <input name="positionen[${idx}][lieferzeit_text]" type="text"
               class="erp-input" placeholder="Lieferzeit" id="lz-${idx}">
        <button type="button" class="btn btn-danger btn-sm" onclick="positionEntfernen(${idx})">🗑</button>`;

        document.getElementById('positionen-container').appendChild(div);

        if (artikelId) {
            document.getElementById('aid-' + idx).value = artikelId;
            document.getElementById('asuche-' + idx).value = artikelName;
            // EK-Preis kommt schon als Parameter rein:
            // ekPreis ist schon im value="${ekPreis || ''}" oben
        }

        berechneGesamt();

    }

    function artikelSuchen(input, idx) {
        var q = input.value.trim();

        if (q.length < 2) {
            // Dropdown verstecken
            document.getElementById('dropdown-' + idx).style.display = 'none';
            return;
        }

        var lieferantId = document.getElementById('lieferant_id').value;

        fetch('/mealana/bestellungen/artikel_ajax.php?lieferant_id=' + lieferantId + '&q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(function(daten) {
                var dropdown = document.getElementById('dropdown-' + idx);

                if (daten.length === 0) {
                    dropdown.style.display = 'none';
                    return;
                }

                var html = '';
                daten.forEach(function(a) {
                    // hier baust du einen klickbaren div pro Artikel
                    // a.name, a.variante_name, a.artikelnummer sind verfügbar
                    html += '<div style="cursor:pointer;padding:6px 8px;border-bottom:1px solid var(--color-border)" onclick="artikelWaehlen(' + a.id + ', ' + idx + ', \'' + escHtml(a.name + (a.variante_name ? ' — ' + a.variante_name : '')) + '\')">';
                    html += '<div style="font-size:13px;font-weight:500">' + escHtml(a.name) + '</div>';
                    html += a.variante_name ? '<div style="font-size:12px;">— ' + escHtml(a.variante_name) + '</div>' : '';
                    html += '<div style="font-size:12px;">( ' + a.artikelnummer + ')</div>';
                    html += '</div>';
                });

                dropdown.innerHTML = html;
                dropdown.style.display = 'block';
            });

    }

    function artikelWaehlen(artikelId, idx, anzeigeText) {
        document.getElementById('aid-' + idx).value = artikelId;
        document.getElementById('asuche-' + idx).value = anzeigeText;
        document.getElementById('dropdown-' + idx).style.display = 'none';
    }


    function positionEntfernen(idx) {
        var el = document.getElementById('pos-' + idx);
        if (el) el.remove();
        berechneGesamt();
    }

    function berechneGesamt() {
        var total = 0;
        document.querySelectorAll('[name$="[menge_bestellt]"]').forEach(function(m, i) {
            var container = m.closest('div[id^="pos-"]');
            if (!container) return;
            var idx = container.id.replace('pos-', '');
            var ek = parseFloat(document.querySelector('[name="positionen[' + idx + '][ek_preis]"]')?.value || 0);
            total += parseFloat(m.value || 0) * ek;
        });
        document.getElementById('gesamt-ek').textContent = total.toLocaleString('de-AT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' €';
    }

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    document.getElementById('bestellung-form').addEventListener('submit', function(e) {
        var positionen = document.querySelectorAll('[id^="pos-"]');
        if (!positionen.length) {
            e.preventDefault();
            alert('Bitte mindestens eine Position hinzufügen.');
            return;
        }
        for (var i = 0; i < positionen.length; i++) {
            var idx = positionen[i].id.replace('pos-', '');
            var aid = document.getElementById('aid-' + idx);
            if (aid && !aid.value) {
                e.preventDefault();
                alert('Bitte bei jeder Position einen Artikel aus der Suche anklicken (nicht nur tippen).');
                document.getElementById('asuche-' + idx).focus();
                return;
            }
        }
    });

    // Auto-Laden wenn Lieferant vorgewählt (nach Validierungsfehler)
    document.addEventListener('DOMContentLoaded', function() {
        var sel = document.getElementById('lieferant_id');
        if (sel.value) {
            ladeReserviert(sel.value);
        }
        <?php if (!empty($formdata['positionen'])): ?>
            // Positionen nach Fehler wiederherstellen
            var saved = <?= json_encode($formdata['positionen'] ?? []) ?>;
            saved.forEach(function(p) {
                positionHinzufuegen(p.artikel_id, '', p.menge_bestellt, p.ek_preis);
            });
        <?php endif; ?>
    });
</script>

<?php require_once __DIR__ . '/../includes/shell_bottom.php'; ?>