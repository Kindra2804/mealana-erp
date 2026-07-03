function zeigeBanner(text, erfolg) {
    var b = document.getElementById('banner');
    if (!b) return;
    b.textContent    = text;
    b.style.display  = 'block';
    b.style.background = erfolg ? '#e8f5e8' : '#fde8e8';
    b.style.color      = erfolg ? '#107c10'  : '#c42b1c';
    b.style.border     = erfolg ? '1px solid #c3e6c3' : '1px solid #f5b8b8';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

function stammdatenSpeichern() {
    var name        = document.getElementById('akt-name').value.trim();
    var beschreibung = document.getElementById('akt-beschreibung').value.trim();
    if (!name) { zeigeBanner('Name ist Pflichtfeld', false); return; }
    fetch(window.BASE_PATH + '/aktionen/aktion_speichern.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'modus=update&id=' + AKTION_ID + '&name=' + encodeURIComponent(name) + '&beschreibung=' + encodeURIComponent(beschreibung)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) { zeigeBanner(d.erfolg ? 'Gespeichert' : (d.fehler || 'Fehler'), d.erfolg); });
}

function aktionStarten() {
    fetch(window.BASE_PATH + '/aktionen/aktion_starten_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + AKTION_ID + '&aktion=starten'
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else zeigeBanner(d.fehler || 'Fehler', false);
    });
}

function aktionStoppen() {
    fetch(window.BASE_PATH + '/aktionen/aktion_starten_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + AKTION_ID + '&aktion=stoppen'
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else zeigeBanner(d.fehler || 'Fehler', false);
    });
}

function katHinzufuegen() {
    var katId  = document.getElementById('kat-neu-id').value;
    var von    = document.getElementById('kat-neu-von').value;
    var bis    = document.getElementById('kat-neu-bis').value;
    var fehler = document.getElementById('kat-fehler');
    if (!katId)      { fehler.textContent = 'Bitte Kategorie wählen'; return; }
    if (!von || !bis){ fehler.textContent = 'Von und Bis sind Pflichtfelder'; return; }
    fehler.textContent = '';
    fetch(window.BASE_PATH + '/aktionen/aktion_kategorie_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'aktion=hinzufuegen&aktion_id=' + AKTION_ID + '&kategorie_id=' + katId + '&von=' + von + '&bis=' + bis
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else fehler.textContent = d.fehler || 'Fehler beim Hinzufügen';
    });
}

function katEntfernen(akId, btn) {
    if (!confirm('Kategorie-Zuweisung entfernen?')) return;
    btn.disabled = true;
    fetch(window.BASE_PATH + '/aktionen/aktion_kategorie_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'aktion=entfernen&ak_id=' + akId
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else { btn.disabled = false; zeigeBanner(d.fehler || 'Fehler', false); }
    });
}

var preiseDaten = [];

function artikelLaden() {
    var katId  = document.getElementById('preis-kat-id').value;
    var kgId   = document.getElementById('preis-kg-id').value;
    var inhalt = document.getElementById('preis-inhalt');
    if (!katId) {
        inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Bitte Kategorie wählen um Artikel anzuzeigen.</p>';
        return;
    }
    inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Lade Artikel…</p>';
    fetch(window.BASE_PATH + '/aktionen/aktion_artikel_laden.php?aktion_id=' + AKTION_ID + '&kategorie_id=' + katId + '&kg_id=' + kgId)
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.erfolg) {
                inhalt.innerHTML = '<p style="color:var(--color-danger)">' + (d.fehler || 'Fehler') + '</p>';
                return;
            }
            preiseDaten = d.artikel;
            renderPreisTabelle(d.artikel, kgId);
        });
}

function renderPreisTabelle(artikel, kgId) {
    var inhalt = document.getElementById('preis-inhalt');
    if (!artikel.length) {
        inhalt.innerHTML = '<p style="color:var(--color-text-muted);font-size:12px">Keine Vater-Artikel in dieser Kategorie.</p>';
        return;
    }
    var html = '<table class="erp-table" style="margin-bottom:10px"><thead><tr><th style="width:35%">ARTIKEL</th>';
    var achsenNamen = {};
    artikel.forEach(function (a) {
        if (a.sub_achsen && a.sub_achsen.length) {
            a.sub_achsen.forEach(function (sa) { achsenNamen[sa.achse_id] = sa.achse_name; });
        }
    });
    var hatSubAchsen = Object.keys(achsenNamen).length > 0;
    if (hatSubAchsen) {
        Object.values(achsenNamen).forEach(function (n) {
            html += '<th style="text-align:right;white-space:nowrap">' + escH(n) + '</th>';
        });
    } else {
        html += '<th style="text-align:right">AKTIONSPREIS</th>';
    }
    html += '</tr></thead><tbody>';
    artikel.forEach(function (a) {
        var normalVkText = a.normal_vk
            ? ' <span style="font-size:11px;color:var(--color-text-muted);font-weight:400">Normal: ' + parseFloat(a.normal_vk).toFixed(2).replace('.', ',') + ' €</span>'
            : '';
        html += '<tr><td style="font-weight:500">' + escH(a.name) + normalVkText +
            '<div style="font-size:11px;color:var(--color-text-muted)">' + escH(a.artikelnummer) + '</div></td>';
        if (hatSubAchsen) {
            Object.keys(achsenNamen).forEach(function (achseId) {
                var sa  = (a.sub_achsen || []).find(function (s) { return String(s.achse_id) === String(achseId); });
                var brt = sa && sa.preis ? parseFloat(sa.preis.brutto_vk).toFixed(2) : '';
                var net = sa && sa.preis ? parseFloat(sa.preis.netto_vk).toFixed(4)  : '';
                html += '<td style="text-align:right">' + preisZelle('preis_' + a.id + '_' + achseId, a.id, achseId, a.mwst_satz || 20, brt, net) + '</td>';
            });
        } else {
            var brt = a.preis ? parseFloat(a.preis.brutto_vk).toFixed(2) : '';
            var net = a.preis ? parseFloat(a.preis.netto_vk).toFixed(4)  : '';
            html += '<td style="text-align:right">' + preisZelle('preis_' + a.id + '_0', a.id, '', a.mwst_satz || 20, brt, net) + '</td>';
        }
        html += '</tr>';
    });
    html += '</tbody></table>';
    html += '<div style="display:flex;align-items:center;gap:12px;justify-content:flex-end">' +
        '<span id="preis-save-info" style="font-size:12px;color:var(--color-text-muted)"></span>' +
        '<button onclick="preiseSpeichern(' + kgId + ')" class="btn btn-primary btn-sm">Preise speichern</button>' +
        '</div>';
    inhalt.innerHTML = html;
}

function preiseSpeichern(kgId) {
    var inputs = document.querySelectorAll('.preis-input');
    var preise = [];
    inputs.forEach(function (inp) {
        var nettoEl = document.querySelector('[data-brutto-id="' + inp.id + '"]');
        preise.push({
            artikel_id:   inp.dataset.artikelId,
            sub_achse_id: inp.dataset.subAchseId,
            brutto_vk:    inp.value,
            netto_vk:     nettoEl ? nettoEl.value : '',
            mwst_satz:    inp.dataset.mwst
        });
    });
    var info = document.getElementById('preis-save-info');
    info.textContent = 'Speichern…';
    fetch(window.BASE_PATH + '/aktionen/aktion_preise_speichern.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ aktion_id: AKTION_ID, kg_id: kgId, preise: preise })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) {
            info.textContent = d.gespeichert + ' Preis(e) gespeichert' + (d.geloescht ? ', ' + d.geloescht + ' gelöscht' : '');
            info.style.color = 'var(--color-success, #107c10)';
        } else {
            info.textContent = d.fehler || 'Fehler';
            info.style.color = 'var(--color-danger)';
        }
    });
}

function preisZelle(id, artikelId, subAchseId, mwst, brt, net) {
    return '<div style="display:flex;flex-direction:column;align-items:flex-end;gap:2px">' +
        '<input type="text" class="erp-input preis-input" style="width:90px;text-align:right"' +
        ' data-artikel-id="' + artikelId + '" data-sub-achse-id="' + subAchseId + '" data-mwst="' + mwst + '"' +
        ' id="' + id + '" value="' + brt + '" placeholder="brutto €" oninput="syncNetto(this)">' +
        '<input type="text" class="erp-input preis-netto-input" style="width:90px;text-align:right;font-size:11px;color:var(--color-text-muted)"' +
        ' data-brutto-id="' + id + '" data-mwst="' + mwst + '" value="' + net + '" placeholder="netto €" oninput="syncBrutto(this)">' +
        '</div>';
}

function syncNetto(bruttoInput) {
    var brutto  = parseFloat(String(bruttoInput.value).replace(',', '.'));
    var mwst    = parseFloat(bruttoInput.dataset.mwst) || 20;
    var nettoEl = document.querySelector('[data-brutto-id="' + bruttoInput.id + '"]');
    if (nettoEl) nettoEl.value = (isNaN(brutto) || brutto === 0) ? '' : (brutto / (1 + mwst / 100)).toFixed(4);
}

function syncBrutto(nettoInput) {
    var netto    = parseFloat(String(nettoInput.value).replace(',', '.'));
    var mwst     = parseFloat(nettoInput.dataset.mwst) || 20;
    var bruttoEl = document.getElementById(nettoInput.dataset.bruttoId);
    if (bruttoEl) bruttoEl.value = (isNaN(netto) || netto === 0) ? '' : (netto * (1 + mwst / 100)).toFixed(2);
}

function escH(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
