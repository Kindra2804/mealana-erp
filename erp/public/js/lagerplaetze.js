function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent       = msg;
    b.style.background  = ok ? '#2ecc71' : '#e74c3c';
    b.style.color       = '#fff';
    b.style.display     = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

// Kürzel wie LagerService::lagerplatzKuerzel(): [Bereich-]R<Regal>-F<Fach>
function kuerzelAus(bereich, regal, fach) {
    var t = [];
    if (bereich.trim()) t.push(bereich.trim().toUpperCase());
    if (regal.trim())   t.push('R' + regal.trim().toUpperCase());
    if (fach.trim())    t.push('F' + fach.trim().toUpperCase());
    return t.join('-');
}
function kuerzelVorschau(p) {
    var k = kuerzelAus(document.getElementById(p + 'bereich').value, document.getElementById(p + 'regal').value, document.getElementById(p + 'fach').value);
    document.getElementById(p + 'kuerzel').textContent = k || document.getElementById(p + 'bezeichnung').value || '—';
}

function modalNeuOeffnen() {
    document.getElementById('form-neu').reset();
    kuerzelVorschau('');
    document.getElementById('modal-neu').style.display = 'block';
    document.getElementById('regal').focus();
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
    set('edit-bereich',     lp.bereich);
    set('edit-regal',       lp.regal);
    set('edit-fach',        lp.fach);
    set('edit-bezeichnung', lp.bezeichnung);
    kuerzelVorschau('edit-');
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

// ── Regal mit Fächern auf einmal ──
function modalSerieOeffnen() {
    serieVorschau();
    document.getElementById('modal-serie').style.display = 'block';
    document.getElementById('serie-regal').focus();
}
function modalSerieSchliessen() { document.getElementById('modal-serie').style.display = 'none'; }
function serieVorschau() {
    var b = document.getElementById('serie-bereich').value, r = document.getElementById('serie-regal').value;
    var von = parseInt(document.getElementById('serie-von').value, 10), bis = parseInt(document.getElementById('serie-bis').value, 10);
    var el = document.getElementById('serie-vorschau');
    if (!r.trim() || !(von >= 1) || !(bis >= von)) { el.textContent = ''; return; }
    el.textContent = (bis - von + 1) + ' Fächer: ' + kuerzelAus(b, r, String(von)) + ' … ' + kuerzelAus(b, r, String(bis));
}
async function serieSpeichern(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/lager/lagerplaetze_serie.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) {
        zeigeBanner(data.angelegt + ' Lagerplätze angelegt' + (data.vorhanden ? ', ' + data.vorhanden + ' gab es schon' : '') + '.');
        modalSerieSchliessen(); setTimeout(function () { location.reload(); }, 800);
    } else {
        zeigeBanner(data.fehler.join(' | '), false);
    }
}

// ── Etiketten mit QR-Code (angehakte Plätze, sonst alle angezeigten) ──
function etikettenDrucken() {
    var ids = Array.from(document.querySelectorAll('.lp-cb:checked')).map(function (c) { return c.value; });
    if (!ids.length) ids = Array.from(document.querySelectorAll('.lp-cb')).map(function (c) { return c.value; });
    if (!ids.length) { zeigeBanner('Keine Lagerplätze vorhanden.', false); return; }
    window.open(window.BASE_PATH + '/lager/lagerplaetze_etiketten.php?ids=' + ids.join(','), '_blank');
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    modalNeuSchliessen();
    modalBearbeitenSchliessen();
    modalSerieSchliessen();
});
