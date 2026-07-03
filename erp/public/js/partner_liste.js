function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent       = msg;
    b.style.background  = ok ? '#2ecc71' : '#e74c3c';
    b.style.color       = '#fff';
    b.style.display     = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

function typToggle(prefix) {
    var typ       = document.getElementById(prefix + 'typ').value;
    var provZeile = document.getElementById(prefix + 'provision-zeile');
    var belegSel  = document.getElementById(prefix + 'abrechnungs_beleg_typ');
    if (provZeile) provZeile.style.display = ['kommission', 'beides'].includes(typ) ? '' : 'none';
    if (belegSel) {
        var autoMap = { mietfach: 'fremdrechnung', kommission: 'gutschrift', spende: 'info', beides: 'gutschrift' };
        if (autoMap[typ]) belegSel.value = autoMap[typ];
    }
}

function modalNeuOeffnen() {
    document.getElementById('form-neu').reset();
    typToggle('');
    document.getElementById('modal-neu').style.display = 'block';
}
function modalNeuSchliessen() { document.getElementById('modal-neu').style.display = 'none'; }

async function partnerSpeichern(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/partner/speichern.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Partner gespeichert.'); modalNeuSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalBearbeitenOeffnen(p) {
    var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val != null ? val : ''; };
    var chk = function (id, val) { var el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
    document.getElementById('edit-id').value = p.id;
    set('edit-name',                  p.name);
    set('edit-typ',                   p.typ);
    set('edit-email',                 p.email);
    set('edit-telefon',               p.telefon);
    set('edit-iban',                  p.iban);
    set('edit-uid_nummer',            p.uid_nummer);
    set('edit-zvr_nummer',            p.zvr_nummer);
    set('edit-provisions_satz',       p.provisions_satz);
    set('edit-abrechnungs_modus',     p.abrechnungs_modus);
    set('edit-abrechnungs_beleg_typ', p.abrechnungs_beleg_typ);
    set('edit-notiz',                 p.notiz);
    chk('edit-kleinunternehmer',      p.kleinunternehmer);
    typToggle('edit-');
    document.getElementById('modal-bearbeiten').style.display = 'block';
}
function modalBearbeitenSchliessen() { document.getElementById('modal-bearbeiten').style.display = 'none'; }

async function partnerAktualisieren(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/partner/aktualisieren.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Partner gespeichert.'); modalBearbeitenSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

async function statusToggle(id, aktiv) {
    var fd = new FormData();
    fd.append('id', id); fd.append('aktiv', aktiv);
    var res  = await fetch(window.BASE_PATH + '/partner/status_setzen.php', { method: 'POST', body: fd });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner(aktiv ? 'Aktiviert.' : 'Deaktiviert.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner('Fehler beim Speichern.', false); }
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    modalNeuSchliessen();
    modalBearbeitenSchliessen();
});
