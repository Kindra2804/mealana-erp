function achseSortieren(id, richtung) {
    fetch(window.BASE_PATH + '/achsen/sort_ajax.php', {
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

function achseNeuOeffnen() {
    document.getElementById('edit-modal-titel').textContent = 'Neue Achse';
    document.getElementById('edit-id').value        = '0';
    document.getElementById('edit-name').value      = '';
    document.getElementById('edit-code').value      = '';
    document.getElementById('edit-darstellung').value = 'swatches';
    document.getElementById('edit-ist-gruppe').checked = false;
    document.getElementById('edit-abhaengig').value = '';
    document.getElementById('edit-sort').value      = '0';
    document.getElementById('edit-fehler').textContent = '';
    document.getElementById('edit-btn').disabled    = false;
    Array.from(document.getElementById('edit-abhaengig').options).forEach(function (o) { o.hidden = false; });
    document.getElementById('edit-modal').style.display = 'flex';
    document.getElementById('edit-name').focus();
}

function achseBearbeitenOeffnen(id, name, code, darstellung, istGruppe, sort, abhaengigId) {
    document.getElementById('edit-modal-titel').textContent = 'Achse bearbeiten';
    document.getElementById('edit-id').value        = id;
    document.getElementById('edit-name').value      = name;
    document.getElementById('edit-code').value      = code;
    document.getElementById('edit-darstellung').value = darstellung;
    document.getElementById('edit-ist-gruppe').checked = istGruppe == 1;
    document.getElementById('edit-abhaengig').value = abhaengigId || '';
    document.getElementById('edit-sort').value      = sort;
    document.getElementById('edit-fehler').textContent = '';
    document.getElementById('edit-btn').disabled    = false;
    Array.from(document.getElementById('edit-abhaengig').options).forEach(function (o) {
        o.hidden = (o.value !== '' && parseInt(o.value) === id);
    });
    document.getElementById('edit-modal').style.display = 'flex';
    document.getElementById('edit-name').focus();
}

function editSchliessen() { document.getElementById('edit-modal').style.display = 'none'; }

function editAbsenden() {
    var id        = parseInt(document.getElementById('edit-id').value);
    var name      = document.getElementById('edit-name').value.trim();
    var code      = document.getElementById('edit-code').value.trim();
    var darstl    = document.getElementById('edit-darstellung').value;
    var abhaengig = document.getElementById('edit-abhaengig').value;
    var sort      = parseInt(document.getElementById('edit-sort').value) || 0;
    document.getElementById('edit-fehler').textContent = '';
    if (!name) { document.getElementById('edit-fehler').textContent = 'Name ist Pflichtfeld'; document.getElementById('edit-name').focus(); return; }
    if (!code) { document.getElementById('edit-fehler').textContent = 'Code ist Pflichtfeld'; document.getElementById('edit-code').focus(); return; }
    var url       = id > 0 ? window.BASE_PATH + '/achsen/achse_aktualisieren_ajax.php' : window.BASE_PATH + '/achsen/achse_speichern_ajax.php';
    var istGruppe = document.getElementById('edit-ist-gruppe').checked ? '1' : '0';
    var body = new FormData();
    if (id > 0) body.append('id', id);
    body.append('name', name);
    body.append('code', code);
    body.append('darstellungsform', darstl);
    body.append('ist_gruppe', istGruppe);
    body.append('abhaengig_von_achse_id', abhaengig);
    body.append('sort_order', sort);
    document.getElementById('edit-btn').disabled = true;
    fetch(url, { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) { window.location.reload(); }
            else {
                var fehler = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Unbekannter Fehler');
                document.getElementById('edit-fehler').textContent = fehler;
                document.getElementById('edit-btn').disabled = false;
            }
        })
        .catch(function () {
            document.getElementById('edit-fehler').textContent = 'Serverfehler, bitte nochmal versuchen.';
            document.getElementById('edit-btn').disabled = false;
        });
}

document.getElementById('edit-code').addEventListener('blur', function () {
    this.value = this.value.toLowerCase().replace(/\s+/g, '_');
});

var delAchseId = null;

function achseLoeschen(id, name) {
    delAchseId = id;
    document.getElementById('del-name').textContent  = name;
    document.getElementById('del-fehler').textContent = '';
    document.getElementById('del-btn').disabled      = false;
    document.getElementById('del-modal').style.display = 'flex';
}

function delSchliessen() { document.getElementById('del-modal').style.display = 'none'; delAchseId = null; }

function delBestaetigt() {
    if (!delAchseId) return;
    document.getElementById('del-btn').disabled = true;
    var form    = document.createElement('form');
    form.method = 'POST';
    form.action = window.BASE_PATH + '/achsen/loeschen.php';
    var input   = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'id';
    input.value = delAchseId;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
