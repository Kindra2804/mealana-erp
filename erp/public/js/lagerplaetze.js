function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent       = msg;
    b.style.background  = ok ? '#2ecc71' : '#e74c3c';
    b.style.color       = '#fff';
    b.style.display     = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

function modalNeuOeffnen() {
    document.getElementById('form-neu').reset();
    document.getElementById('modal-neu').style.display = 'block';
}
function modalNeuSchliessen() { document.getElementById('modal-neu').style.display = 'none'; }

async function lagerplatzSpeichern(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/lager/lagerplaetze_speichern.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Lagerplatz gespeichert.'); modalNeuSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalBearbeitenOeffnen(lp) {
    var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val != null ? val : ''; };
    var chk = function (id, val) { var el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
    document.getElementById('edit-id').value = lp.id;
    set('edit-lager_id',    lp.lager_id);
    set('edit-bezeichnung', lp.bezeichnung);
    chk('edit-aktiv',       lp.aktiv);
    document.getElementById('modal-bearbeiten').style.display = 'block';
}
function modalBearbeitenSchliessen() { document.getElementById('modal-bearbeiten').style.display = 'none'; }

async function lagerplatzAktualisieren(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/lager/lagerplaetze_aktualisieren.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Lagerplatz gespeichert.'); modalBearbeitenSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

async function statusDeaktivieren(id, bezeichnung) {
    if (!confirm('Lagerplatz «' + bezeichnung + '» wirklich deaktivieren?')) return;
    var fd = new FormData();
    fd.append('id', id); fd.append('aktiv', 0);
    var res  = await fetch(window.BASE_PATH + '/lager/lagerplaetze_status_setzen.php', { method: 'POST', body: fd });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Deaktiviert.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler ? data.fehler.join(' | ') : 'Fehler beim Speichern.', false); }
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    modalNeuSchliessen();
    modalBearbeitenSchliessen();
});
