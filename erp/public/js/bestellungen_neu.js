var posCount = 0;

function ladeReserviert(lieferantId) {
    var box = document.getElementById('reserviert-box');
    if (!lieferantId) { box.style.display = 'none'; box.innerHTML = ''; return; }
    fetch('/mealana/bestellungen/reserviert_ajax.php?lieferant_id=' + lieferantId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.length) { box.style.display = 'none'; box.innerHTML = ''; return; }
            var html = '<div style="border-left:3px solid var(--color-warning);background:#fffbf0;padding:12px;border-radius:4px">';
            html += '<div style="font-weight:600;font-size:13px;margin-bottom:8px;color:#c0820a">⚠ Reserviert, nicht lagernd</div>';
            data.forEach(function (r) {
                var vpe       = r.vpe_menge ? parseInt(r.vpe_menge) : 1;
                var benoetigt = Math.ceil(r.reserviert_gesamt / vpe);
                var vorschlag = benoetigt * vpe;
                var info = vpe > 1 ? benoetigt + ' VPE nötig (= ' + vorschlag + ' Stk)' : '1 VPE deckt Bedarf';
                if (vpe > 1 && vorschlag > r.reserviert_gesamt) {
                    info = benoetigt + ' VPE nötig (= ' + vorschlag + ' Stk, ' + (vorschlag - r.reserviert_gesamt) + ' übrig)';
                }
                html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid #f0e8d0" data-artikel-id="' + r.id + '">';
                html += '<div><div style="font-size:13px;font-weight:500">' + escHtml(r.artikel_name) + '</div>';
                html += '<div style="font-size:12px;color:var(--color-text-muted)">Reserviert: ' + r.reserviert_gesamt + ' Stk &nbsp;|&nbsp; VPE: ' + vpe + ' Stk</div>';
                html += '<div style="font-size:12px;color:#c0820a">→ ' + info + '</div></div>';
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
    div.innerHTML = '<div style="position:relative">' +
        '<input type="text" style="width:100%" id="asuche-' + idx + '" name="positionen[' + idx + '][artikel_suche]" class="erp-input" placeholder="Artikel suchen..." autocomplete="off" oninput="artikelSuchen(this,' + idx + ')">' +
        '<div id="dropdown-' + idx + '" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--color-border);border-radius:4px;z-index:100;max-height:200px;overflow-y:auto"></div>' +
        '<input type="hidden" name="positionen[' + idx + '][artikel_id]" id="aid-' + idx + '" required>' +
        '</div>' +
        '<input name="positionen[' + idx + '][menge_bestellt]" type="number" min="1" step="1" class="erp-input" placeholder="Menge" value="' + (menge || '') + '" required oninput="berechneGesamt()">' +
        '<input name="positionen[' + idx + '][ek_preis]" type="number" min="0" step="0.0001" class="erp-input" placeholder="EK-Preis" value="' + (ekPreis || '') + '" oninput="berechneGesamt()">' +
        '<input name="positionen[' + idx + '][lieferzeit_text]" type="text" class="erp-input" placeholder="Lieferzeit" id="lz-' + idx + '">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="positionEntfernen(' + idx + ')">🗑</button>';
    document.getElementById('positionen-container').appendChild(div);
    if (artikelId) {
        document.getElementById('aid-' + idx).value   = artikelId;
        document.getElementById('asuche-' + idx).value = artikelName;
    }
    berechneGesamt();
}

function artikelSuchen(input, idx) {
    var q = input.value.trim();
    if (q.length < 2) { document.getElementById('dropdown-' + idx).style.display = 'none'; return; }
    var lieferantId = document.getElementById('lieferant_id').value;
    fetch('/mealana/bestellungen/artikel_ajax.php?lieferant_id=' + lieferantId + '&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (daten) {
            var dropdown = document.getElementById('dropdown-' + idx);
            if (!daten.length) { dropdown.style.display = 'none'; return; }
            var html = '';
            daten.forEach(function (a) {
                html += '<div style="cursor:pointer;padding:6px 8px;border-bottom:1px solid var(--color-border)" onclick="artikelWaehlen(' + a.id + ',' + idx + ',\'' + escHtml(a.name + (a.variante_name ? ' — ' + a.variante_name : '')).replace(/'/g, "\\'") + '\')">';
                html += '<div style="font-size:13px;font-weight:500">' + escHtml(a.name) + '</div>';
                html += a.variante_name ? '<div style="font-size:12px;">— ' + escHtml(a.variante_name) + '</div>' : '';
                html += '<div style="font-size:12px;">( ' + escHtml(a.artikelnummer) + ')</div>';
                html += '</div>';
            });
            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
        });
}

function artikelWaehlen(artikelId, idx, anzeigeText) {
    document.getElementById('aid-' + idx).value    = artikelId;
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
    document.querySelectorAll('[name$="[menge_bestellt]"]').forEach(function (m) {
        var container = m.closest('div[id^="pos-"]');
        if (!container) return;
        var idx = container.id.replace('pos-', '');
        var ek  = parseFloat(document.querySelector('[name="positionen[' + idx + '][ek_preis]"]')?.value || 0);
        total  += parseFloat(m.value || 0) * ek;
    });
    document.getElementById('gesamt-ek').textContent = total.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.getElementById('bestellung-form').addEventListener('submit', function (e) {
    var positionen = document.querySelectorAll('[id^="pos-"]');
    if (!positionen.length) { e.preventDefault(); alert('Bitte mindestens eine Position hinzufügen.'); return; }
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

document.addEventListener('DOMContentLoaded', function () {
    var sel = document.getElementById('lieferant_id');
    if (sel.value) ladeReserviert(sel.value);
    var saved = window.BESTELLUNGEN_SAVED_POS || [];
    saved.forEach(function (p) { positionHinzufuegen(p.artikel_id, '', p.menge_bestellt, p.ek_preis); });
});
