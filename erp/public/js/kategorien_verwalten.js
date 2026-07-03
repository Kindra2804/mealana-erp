var katvLoeschId       = null;
var katvLoeschParentId = null;

function katNeuOeffnen(parentId, parentName) {
    var titel = parentName ? 'Neue Unterkategorie unter "' + parentName + '"' : 'Neue Hauptkategorie';
    document.getElementById('katv-modal-titel').textContent = titel;
    document.getElementById('katv-edit-id').value  = '';
    document.getElementById('katv-name').value     = '';
    document.getElementById('katv-parent').value   = parentId != null ? parentId : '';
    document.getElementById('katv-ist-aktions-kat').checked = false;
    document.getElementById('katv-fehler').textContent = '';
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('katv-name').focus(); }, 50);
}

function katBearbeiten(id, name, parentId) {
    var zeile = document.querySelector('.katv-zeile[data-id="' + id + '"]');
    var iak   = zeile ? parseInt(zeile.dataset.iak || '0') : 0;
    document.getElementById('katv-modal-titel').textContent = 'Kategorie bearbeiten';
    document.getElementById('katv-edit-id').value  = id;
    document.getElementById('katv-name').value     = name;
    document.getElementById('katv-parent').value   = parentId != null ? parentId : '';
    document.getElementById('katv-ist-aktions-kat').checked = !!iak;
    document.getElementById('katv-fehler').textContent = '';
    document.querySelectorAll('#katv-parent option').forEach(function (o) { o.disabled = (parseInt(o.value) === id); });
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('katv-name').focus(); }, 50);
}

function katvModalSchliessen() {
    document.getElementById('katv-modal').style.display = 'none';
    document.querySelectorAll('#katv-parent option').forEach(function (o) { o.disabled = false; });
}

function katvSpeichern() {
    var editId   = document.getElementById('katv-edit-id').value;
    var name     = document.getElementById('katv-name').value.trim();
    var parentId = document.getElementById('katv-parent').value;
    var fehler   = document.getElementById('katv-fehler');
    if (!name) { fehler.textContent = 'Name ist Pflichtfeld'; return; }
    fehler.textContent = '';
    var iak  = document.getElementById('katv-ist-aktions-kat').checked ? '1' : '0';
    var url  = editId ? window.BASE_PATH + '/artikel/kategorie_bearbeiten_ajax.php' : window.BASE_PATH + '/artikel/kategorie_erstellen.php';
    var body = 'name=' + encodeURIComponent(name) + '&parent_id=' + encodeURIComponent(parentId) + '&ist_aktions_kategorie=' + iak;
    if (editId) body += '&id=' + encodeURIComponent(editId);
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else fehler.textContent = d.fehler || 'Fehler beim Speichern';
        });
}

function katLoeschenVorschau(id, name) {
    katvLoeschId       = id;
    katvLoeschParentId = null;
    document.getElementById('katv-del-info').textContent = 'Kategorie "' + name + '" wird gelöscht. Bitte warten…';
    document.getElementById('katv-del-warnungen').innerHTML = '';
    document.getElementById('katv-del-optionen').style.display = 'none';
    document.getElementById('katv-del-btn').disabled = true;
    document.getElementById('katv-del-modal').style.display = 'flex';
    fetch(window.BASE_PATH + '/artikel/kategorie_loeschen_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + id + '&aktion=vorschau'
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        var warnHTML = '';
        if (d.kinder_anzahl > 0) {
            warnHTML += '<div style="background:#fff8e1;border:1px solid #f0c000;border-radius:4px;padding:8px 10px;margin-bottom:8px;font-size:12.5px">'
                + '⚠ Diese Kategorie hat <strong>' + d.kinder_anzahl + ' Unterkategorie(n)</strong> — diese werden ebenfalls gelöscht.</div>';
        }
        if (d.artikel_ohne_kat && d.artikel_ohne_kat.length > 0) {
            warnHTML += '<div style="background:#fff0f0;border:1px solid #f5b8b8;border-radius:4px;padding:8px 10px;font-size:12.5px">'
                + '<strong style="color:var(--color-danger)">⚠ ' + d.artikel_ohne_kat.length + ' Artikel würden dadurch kategorielos:</strong>'
                + '<div style="margin-top:6px;max-height:100px;overflow-y:auto">';
            d.artikel_ohne_kat.forEach(function (a) {
                warnHTML += '<div style="font-size:12px;padding:2px 0;border-bottom:1px solid #f5b8b8"><strong>' + a.artikelnummer + '</strong> – ' + a.name + '</div>';
            });
            warnHTML += '</div></div>';
        }
        document.getElementById('katv-del-info').textContent = warnHTML
            ? 'Kategorie "' + name + '" löschen – bitte Warnungen beachten:'
            : 'Kategorie "' + name + '" löschen?';
        document.getElementById('katv-del-warnungen').innerHTML = warnHTML;
        if (d.parent) {
            katvLoeschParentId = d.parent.id;
            document.getElementById('katv-del-parent-name').textContent = '"' + d.parent.name + '"';
            document.getElementById('katv-del-optionen').style.display = '';
            var radVers = document.querySelector('input[name="katv-del-modus"][value="verschieben"]');
            if (radVers) radVers.checked = true;
        }
        document.getElementById('katv-del-btn').disabled = false;
    });
}

function katvDelSchliessen() {
    document.getElementById('katv-del-modal').style.display = 'none';
    katvLoeschId = null; katvLoeschParentId = null;
}

function katvLoeschenBestaetigt() {
    if (!katvLoeschId) return;
    document.getElementById('katv-del-btn').disabled = true;
    var modus       = document.querySelector('input[name="katv-del-modus"]:checked');
    var verschiebeId = '';
    if (modus && modus.value === 'verschieben' && katvLoeschParentId) verschiebeId = katvLoeschParentId;
    fetch(window.BASE_PATH + '/artikel/kategorie_loeschen_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + katvLoeschId + '&aktion=loeschen&verschiebe_zu_parent_id=' + verschiebeId
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else { alert(d.fehler || 'Fehler beim Löschen'); katvDelSchliessen(); }
    });
}

function katSortieren(id, richtung) {
    fetch(window.BASE_PATH + '/artikel/kategorie_sort_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: id, richtung: richtung })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else alert(d.fehler || 'Fehler beim Sortieren');
    });
}
