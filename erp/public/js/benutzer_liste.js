function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent       = msg;
    b.style.background  = ok ? '#2ecc71' : '#e74c3c';
    b.style.color       = '#fff';
    b.style.display     = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

// -------------------------------------------------------------------------
// Neu-Modal: Auto-Vorschlag Formularname/Benutzername aus Vorname+Nachname,
// solange der Benutzer diese Felder nicht selbst manuell bearbeitet hat.
// -------------------------------------------------------------------------
var manuellBearbeitet = { formularname: false, username: false };

function markiereManuellBearbeitet(feld) {
    manuellBearbeitet[feld] = true;
}

function autoVorschlaege() {
    var vorname  = document.getElementById('vorname').value.trim();
    var nachname = document.getElementById('nachname').value.trim();

    if (!manuellBearbeitet.formularname) {
        var formularEl = document.getElementById('formularname');
        formularEl.value = [vorname, nachname].filter(Boolean).join(' ');
    }
    if (!manuellBearbeitet.username) {
        var userEl = document.getElementById('username');
        userEl.value = [vorname, nachname].filter(Boolean).join('.').toLowerCase()
            .replace(/[äáàâ]/g, 'a').replace(/[öóòô]/g, 'o').replace(/[üúùû]/g, 'u')
            .replace(/ß/g, 'ss').replace(/[^a-z0-9.]/g, '');
    }
}

function passwortModusToggle() {
    var modus = document.querySelector('input[name="passwort_modus"]:checked').value;
    document.getElementById('passwort-direkt-felder').style.display = modus === 'direkt' ? 'flex' : 'none';
}

function modalNeuOeffnen() {
    document.getElementById('form-neu').reset();
    manuellBearbeitet.formularname = false;
    manuellBearbeitet.username     = false;
    passwortModusToggle();
    document.getElementById('modal-neu').style.display = 'block';
}
function modalNeuSchliessen() { document.getElementById('modal-neu').style.display = 'none'; }

async function benutzerSpeichern(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/benutzer/speichern.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Benutzer angelegt.'); modalNeuSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalBearbeitenOeffnen(b) {
    var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val != null ? val : ''; };
    var chk = function (id, val) { var el = document.getElementById(id); if (el) el.checked = !!parseInt(val); };
    document.getElementById('edit-id').value = b.id;
    document.getElementById('edit-username').value = '@' + b.username;
    set('edit-vorname',      b.vorname);
    set('edit-nachname',     b.nachname);
    set('edit-formularname', b.formularname);
    set('edit-email',        b.email);
    set('edit-rolle_id',     b.rolle_id);
    chk('edit-aktiv',        b.aktiv);
    document.getElementById('modal-bearbeiten').style.display = 'block';
}
function modalBearbeitenSchliessen() { document.getElementById('modal-bearbeiten').style.display = 'none'; }

async function benutzerAktualisieren(e) {
    e.preventDefault();
    var res  = await fetch(window.BASE_PATH + '/benutzer/aktualisieren.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Gespeichert.'); modalBearbeitenSchliessen(); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

async function statusDeaktivieren(id, name) {
    if (!confirm('Benutzer «' + name + '» wirklich deaktivieren?')) return;
    var fd = new FormData();
    fd.append('id', id); fd.append('aktiv', 0);
    var res  = await fetch(window.BASE_PATH + '/benutzer/status_setzen.php', { method: 'POST', body: fd });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Deaktiviert.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler ? data.fehler.join(' | ') : 'Fehler beim Speichern.', false); }
}

async function linkErneutSenden(id) {
    var fd = new FormData();
    fd.append('id', id);
    var res  = await fetch(window.BASE_PATH + '/benutzer/link_erneut_senden.php', { method: 'POST', body: fd });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Link wurde verschickt (falls nicht vor Kurzem schon einer versendet wurde).'); }
    else             { zeigeBanner(data.fehler ? data.fehler.join(' | ') : 'Fehler beim Versenden.', false); }
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    modalNeuSchliessen();
    modalBearbeitenSchliessen();
});
