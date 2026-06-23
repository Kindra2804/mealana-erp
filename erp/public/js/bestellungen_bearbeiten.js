var posCount = 0;

function positionHinzufuegen() {
    var idx = posCount++;
    var div = document.createElement('div');
    div.id = 'pos-' + idx;
    div.style.cssText = 'display:grid;grid-template-columns:2fr 80px 120px auto;gap:8px;align-items:end;padding:8px 0;border-bottom:1px solid var(--color-border)';
    div.innerHTML = '<div style="position:relative">' +
        '<input type="text" style="width:100%" id="asuche-' + idx + '" class="erp-input" placeholder="Artikel suchen..." autocomplete="off" oninput="artikelSuchen(this,' + idx + ')">' +
        '<div id="dropdown-' + idx + '" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid var(--color-border);border-radius:4px;z-index:100;max-height:200px;overflow-y:auto"></div>' +
        '<input type="hidden" name="positionen[' + idx + '][artikel_id]" id="aid-' + idx + '">' +
        '</div>' +
        '<input name="positionen[' + idx + '][menge_bestellt]" type="number" min="1" step="1" class="erp-input" placeholder="Menge" required oninput="berechneNeu()">' +
        '<input name="positionen[' + idx + '][ek_preis]" type="number" min="0" step="0.0001" class="erp-input" placeholder="EK-Preis" oninput="berechneNeu()">' +
        '<button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'[id^=pos-]\').remove()">🗑</button>';
    document.getElementById('positionen-container').appendChild(div);
}

function artikelSuchen(input, idx) {
    var q = input.value.trim();
    if (q.length < 2) { document.getElementById('dropdown-' + idx).style.display = 'none'; return; }
    fetch('/mealana/bestellungen/artikel_ajax.php?alle=1&q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (daten) {
            var dropdown = document.getElementById('dropdown-' + idx);
            if (!daten.length) { dropdown.style.display = 'none'; return; }
            var html = '';
            daten.forEach(function (a) {
                html += '<div style="cursor:pointer;padding:6px 8px;border-bottom:1px solid var(--color-border)" onclick="artikelWaehlen(' + a.id + ',' + idx + ',\'' + escHtml(a.name + (a.variante_name ? ' — ' + a.variante_name : '')).replace(/'/g, "\\'") + '\')">';
                html += '<div style="font-size:13px;font-weight:500">' + escHtml(a.name) + '</div>';
                html += a.variante_name ? '<div style="font-size:12px">— ' + escHtml(a.variante_name) + '</div>' : '';
                html += '<div style="font-size:12px;color:var(--color-text-muted)">(' + escHtml(a.artikelnummer) + ')</div>';
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

function berechneNeu() {
    // optional: Gesamtsumme anzeigen
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.getElementById('bestellung-edit-form').addEventListener('submit', function (e) {
    var positionen = document.querySelectorAll('[id^="pos-"]');
    for (var i = 0; i < positionen.length; i++) {
        var idx = positionen[i].id.replace('pos-', '');
        var aid = document.getElementById('aid-' + idx);
        if (aid && !aid.value) {
            e.preventDefault();
            alert('Bitte bei jeder neuen Position einen Artikel aus der Suche anklicken.');
            document.getElementById('asuche-' + idx).focus();
            return;
        }
    }
});
