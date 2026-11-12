function ajax(action, data) {
    return fetch('merkmal_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(Object.assign({ action: action }, data))
    }).then(function (r) { return r.json(); });
}

function reload() { location.reload(); }

function merkmalNeu() {
    document.getElementById('mf-id').value       = '';
    document.getElementById('mf-name').value     = '';
    document.getElementById('mf-slug').value     = '';
    document.getElementById('mf-mehrfach').checked  = false;
    document.getElementById('mf-filterbar').checked = false;
    document.querySelectorAll('.mf-typ-cb').forEach(function (cb) { cb.checked = false; });
    document.getElementById('mf-fehler').textContent = '';
    document.getElementById('merkmal-backdrop').style.display = 'flex';
    document.getElementById('mf-name').focus();
}

function merkmalBearbeiten(id, m) {
    document.getElementById('mf-id').value       = id;
    document.getElementById('mf-name').value     = m.name;
    document.getElementById('mf-slug').value     = m.slug || '';
    document.getElementById('mf-mehrfach').checked  = !!m.mehrfach_auswahl;
    document.getElementById('mf-filterbar').checked = !!m.filterbar;
    document.querySelectorAll('.mf-typ-cb').forEach(function (cb) {
        cb.checked = (m.artikeltyp_ids || []).includes(parseInt(cb.value));
    });
    document.getElementById('mf-fehler').textContent = '';
    document.getElementById('merkmal-backdrop').style.display = 'flex';
}

function merkmalModalSchliessen() { document.getElementById('merkmal-backdrop').style.display = 'none'; }

function merkmalSpeichern() {
    var id       = document.getElementById('mf-id').value;
    var name     = document.getElementById('mf-name').value.trim();
    var slug     = document.getElementById('mf-slug').value.trim();
    var mehrfach = document.getElementById('mf-mehrfach').checked;
    var filterbar = document.getElementById('mf-filterbar').checked;
    var typIds   = Array.from(document.querySelectorAll('.mf-typ-cb:checked')).map(function (cb) { return parseInt(cb.value); });
    var fehlerEl = document.getElementById('mf-fehler');
    if (!name) { fehlerEl.textContent = 'Name darf nicht leer sein'; return; }
    var action = id ? 'merkmal_bearbeiten' : 'merkmal_neu';
    var btn    = document.getElementById('mf-btn-speichern');
    btn.disabled = true;
    var payload = { name: name, slug: slug, mehrfach_auswahl: mehrfach, filterbar: filterbar, artikeltyp_ids: typIds };
    if (id) payload.id = parseInt(id);
    ajax(action, payload)
        .then(function (d) {
            if (d.erfolg) reload();
            else { fehlerEl.textContent = d.fehler || 'Fehler'; btn.disabled = false; }
        });
}

function merkmalLoeschen(id, name) {
    if (!confirm('Merkmal „' + name + '" und alle zugehörigen Werte löschen?')) return;
    ajax('merkmal_loeschen', { id: id }).then(function (d) {
        if (d.erfolg) reload(); else alert(d.fehler);
    });
}

function merkmalSort(id, richtung) {
    ajax('merkmal_sort', { id: id, richtung: richtung }).then(function (d) { if (d.erfolg) reload(); });
}

function wertNeu(merkmalId) {
    var input = document.getElementById('neu-wert-' + merkmalId);
    var wert  = input.value.trim();
    if (!wert) return;
    ajax('wert_neu', { merkmal_id: merkmalId, wert: wert }).then(function (d) {
        if (d.erfolg) reload(); else alert(d.fehler);
    });
}

function wertBearbeiten(id, wertAlt) {
    var neu = prompt('Wert bearbeiten:', wertAlt);
    if (neu === null || neu.trim() === '') return;
    ajax('wert_bearbeiten', { id: id, wert: neu.trim() }).then(function (d) {
        if (d.erfolg) reload(); else alert(d.fehler);
    });
}

// Werte-Liste eines Merkmals durchsuchen (ab 13 Werten eingeblendet)
function werteFiltern(merkmalId, q) {
    q = q.trim().toLowerCase();
    document.querySelectorAll('#werte-' + merkmalId + ' .mw-zeile').forEach(function (z) {
        z.style.display = !q || z.dataset.wert.toLowerCase().includes(q) ? 'flex' : 'none';
    });
}

// Werte zusammenführen: Quellwert geht in einem anderen Wert desselben Merkmals auf
var _zfQuelle = null;
function wertZusammenfuehren(id) {
    var zeile = document.getElementById('wert-' + id);
    _zfQuelle = id;
    document.getElementById('zf-quelle').textContent = zeile.dataset.wert;
    var n = parseInt(zeile.dataset.n, 10);
    document.getElementById('zf-anzahl').textContent = n === 1 ? '1 Artikel' : n + ' Artikel';
    var sel = document.getElementById('zf-ziel');
    sel.innerHTML = '';
    var andere = Array.from(document.querySelectorAll('.mw-zeile[data-merkmal="' + zeile.dataset.merkmal + '"]'))
        .filter(function (z) { return z !== zeile; })
        .sort(function (a, b) { return a.dataset.wert.localeCompare(b.dataset.wert, 'de'); });
    andere.forEach(function (z) {
        var o = document.createElement('option');
        o.value = z.id.replace('wert-', '');
        o.textContent = z.dataset.wert + '  (' + z.dataset.n + ')';
        o.dataset.text = z.dataset.wert.toLowerCase();
        o.dataset.orig = z.dataset.wert;
        sel.appendChild(o);
    });
    document.getElementById('zf-suche').value = '';
    document.getElementById('zf-btn').disabled = false;
    document.getElementById('zf-backdrop').style.display = 'flex';
    document.getElementById('zf-suche').focus();
}
function zfFiltern() {
    var q = document.getElementById('zf-suche').value.trim().toLowerCase();
    var erster = null;
    document.querySelectorAll('#zf-ziel option').forEach(function (o) {
        var passt = !q || o.dataset.text.includes(q);
        o.hidden = !passt;
        if (passt && !erster) erster = o;
    });
    if (erster) erster.selected = true;
}
function zfSchliessen() { document.getElementById('zf-backdrop').style.display = 'none'; }
function zfAusfuehren() {
    var sel = document.getElementById('zf-ziel');
    if (!sel.value) { alert('Bitte einen Zielwert wählen'); return; }
    var zielText = sel.options[sel.selectedIndex].dataset.orig;
    if (!confirm('„' + document.getElementById('zf-quelle').textContent + '“ wirklich in „' + zielText + '“ zusammenführen?')) return;
    document.getElementById('zf-btn').disabled = true;
    ajax('wert_zusammenfuehren', { quelle_id: _zfQuelle, ziel_id: parseInt(sel.value, 10) }).then(function (d) {
        if (d.erfolg) reload();
        else { alert(d.fehler || 'Fehler'); document.getElementById('zf-btn').disabled = false; }
    });
}

function wertLoeschen(id, wert) {
    var n = parseInt((document.getElementById('wert-' + id) || {}).dataset ? document.getElementById('wert-' + id).dataset.n : 0, 10) || 0;
    if (!confirm('Wert „' + wert + '" löschen?' + (n ? '\n\nAchtung: ' + n + ' Artikel verlieren diesen Wert. Zum Umstellen lieber „⇢ Zusammenführen“ nehmen.' : ''))) return;
    ajax('wert_loeschen', { id: id }).then(function (d) {
        if (d.erfolg) reload(); else alert(d.fehler);
    });
}

function wertSort(id, richtung) {
    ajax('wert_sort', { id: id, richtung: richtung }).then(function (d) { if (d.erfolg) reload(); });
}

document.getElementById('mf-name').addEventListener('input', function () {
    var slugField = document.getElementById('mf-slug');
    if (!slugField.dataset.manuell) {
        slugField.value = this.value.toLowerCase()
            .replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
            .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    }
});
document.getElementById('mf-slug').addEventListener('input', function () {
    this.dataset.manuell = this.value ? '1' : '';
});
