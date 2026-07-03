document.getElementById('lager-select').addEventListener('change', syncLager);
function syncLager() {
    document.getElementById('lager_id_hidden').value = document.getElementById('lager-select').value;
}
syncLager();

document.getElementById('ean-scan').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); eanSuchen(); }
});

function eanSuchen() {
    var ean = document.getElementById('ean-scan').value.trim();
    if (!ean) return;
    fetch(window.BASE_PATH + '/wareneingang/artikel_suche.php?ean=' + encodeURIComponent(ean))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.gefunden) { alert('EAN nicht gefunden: ' + ean); return; }
            var rows = document.querySelectorAll('#positionen-tabelle tbody tr[data-artikel-id="' + data.artikel.id + '"]');
            if (rows.length) positionWaehlen(rows[0]);
            else alert('Artikel in dieser Bestellung nicht gefunden.');
        });
}

function positionWaehlen(row) {
    var artikelId    = row.dataset.artikelId;
    var positionId   = row.dataset.positionId;
    var name         = row.dataset.artikelName;
    var hauptbild    = row.dataset.hauptbild;
    var chargePflicht = row.dataset.chargePflicht == '1';
    var offen        = row.dataset.offen;

    document.getElementById('position_id').value = positionId;
    document.getElementById('artikel_id').value  = artikelId;

    document.getElementById('artikel-info').innerHTML =
        '<div style="font-weight:600;font-size:14px">' + escHtml(name) + '</div>' +
        '<div style="font-size:12px;color:var(--color-text-muted)">Offen: ' + Math.round(offen) + ' Stk</div>';

    var bildBox = document.getElementById('artikel-bild-box');
    if (hauptbild) {
        bildBox.innerHTML = '<img src="' + window.BASE_PATH + '/uploads/artikel/' + artikelId + '/' + escHtml(hauptbild) + '" style="width:90px;height:90px;object-fit:cover;border-radius:6px" onerror="this.parentElement.innerHTML=\'📦\'">';
    } else {
        bildBox.innerHTML = '📦';
    }

    fetch(window.BASE_PATH + '/wareneingang/chargen_ajax.php?artikel_id=' + artikelId)
        .then(function (r) { return r.json(); })
        .then(function (chargen) {
            var sel = document.getElementById('charge-select');
            sel.innerHTML = '<option value="">– keine / zu erfassen –</option>';
            chargen.forEach(function (c) {
                sel.innerHTML += '<option value="' + escHtml(c) + '">' + escHtml(c) + '</option>';
            });
            sel.innerHTML += '<option value="__neu__">+ Neue Charge...</option>';
        });

    var chargeLabel = document.querySelector('label[for="charge-select"]');
    if (chargeLabel) chargeLabel.textContent = 'Charge ' + (chargePflicht ? '(Pflicht)' : '(optional)');

    document.getElementById('menge-input').value = 1;
    document.getElementById('buchungs-form').style.display = 'block';
    document.getElementById('menge-input').focus();
    document.getElementById('menge-input').select();

    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(function (r) { r.style.background = ''; });
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

document.getElementById('eingang-form').addEventListener('submit', function (e) {
    var neuInput = document.getElementById('charge-neu');
    var sel      = document.getElementById('charge-select');
    if (neuInput.style.display !== 'none' && neuInput.value.trim()) {
        sel.innerHTML += '<option value="' + escHtml(neuInput.value.trim()) + '" selected>' + escHtml(neuInput.value.trim()) + '</option>';
        sel.value = neuInput.value.trim();
        neuInput.style.display = 'none';
    }
});

function abschliessenDialog() {
    var offenCount = 0;
    document.querySelectorAll('#positionen-tabelle tbody tr').forEach(function (r) {
        if (parseFloat(r.dataset.offen || 0) > 0) offenCount++;
    });

    var bId = window.WE_BESTELLUNG_ID;
    var abschliessenUrl = window.WE_ABSCHLIESSEN_URL || window.BASE_PATH + '/wareneingang/abschliessen.php';
    var html = '';
    if (offenCount === 0) {
        html  = '<div style="font-weight:600;margin-bottom:12px">Alle Positionen vollständig eingegangen.</div>';
        html += '<form method="post" action="' + abschliessenUrl + '">';
        html += '<input type="hidden" name="bestellung_id" value="' + bId + '">';
        html += '<input type="hidden" name="aktion" value="komplett">';
        html += '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">';
        html += '<button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById(\'abschluss-modal\').style.display=\'none\'">Abbrechen</button>';
        html += '<button type="submit" class="btn btn-primary btn-sm">Bestellung abschliessen</button>';
        html += '</div></form>';
    } else {
        html  = '<div style="font-weight:600;margin-bottom:8px">Nicht alle Positionen eingegangen (' + offenCount + ' offen)</div>';
        html += '<form method="post" action="' + abschliessenUrl + '">';
        html += '<input type="hidden" name="bestellung_id" value="' + bId + '">';
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

    document.querySelectorAll('[name="aktion"]').forEach(function (r) {
        r.addEventListener('change', function () {
            document.getElementById('gutschrift-bereich').style.display = this.value === 'streichen' ? 'block' : 'none';
        });
    });
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

setTimeout(function () { var b = document.getElementById('msg-banner'); if (b) b.style.display = 'none'; }, 3000);
