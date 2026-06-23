function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent      = msg;
    b.style.background = ok ? '#2ecc71' : '#e74c3c';
    b.style.color      = '#fff';
    b.style.display    = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

function modalFachNeuOeffnen() {
    document.getElementById('form-fach-neu').reset();
    document.getElementById('modal-fach-neu').style.display = 'block';
    document.querySelector('#form-fach-neu [name="aktiv"]').checked = true;
}

async function fachSpeichern(e) {
    e.preventDefault();
    var res  = await fetch('/mealana/partner/fach_speichern.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Fach gespeichert.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalFachBearbeitenOeffnen(f) {
    var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val != null ? val : ''; };
    document.getElementById('fb-id').value = f.id;
    set('fb-bezeichnung', f.fach_bezeichnung);
    set('fb-ort',         f.ort_beschreibung);
    set('fb-laenge',      f.laenge_cm);
    set('fb-breite',      f.breite_cm);
    set('fb-hoehe',       f.hoehe_cm);
    set('fb-preis',       f.standard_preis);
    set('fb-notiz',       f.notiz);
    document.getElementById('fb-aktiv').checked = parseInt(f.aktiv) === 1;
    document.getElementById('modal-fach-bearbeiten').style.display = 'block';
}

async function fachAktualisieren(e) {
    e.preventDefault();
    var res  = await fetch('/mealana/partner/fach_aktualisieren.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Fach gespeichert.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalVertragOeffnen(fachId, bezeichnung, standardPreis) {
    document.getElementById('v-fach-id').value = fachId;
    document.getElementById('v-preis').value   = standardPreis > 0 ? standardPreis : '';
    document.getElementById('v-mwst').value    = '20';
    document.getElementById('v-beginn').value  = new Date().toISOString().slice(0, 10);
    document.getElementById('v-ende').value    = '';
    document.getElementById('v-notiz').value   = '';
    document.getElementById('v-partner').value = '';
    document.getElementById('vertrag-titel').textContent = 'Vermieten: ' + bezeichnung;
    document.getElementById('modal-vertrag').style.display = 'block';
}

async function vertragSpeichern(e) {
    e.preventDefault();
    var res  = await fetch('/mealana/partner/vertrag_speichern.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Vertrag gestartet.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

function modalVertragBeendenOeffnen(vertragId, bezeichnung, mieterName) {
    document.getElementById('k-vertrag-id').value = vertragId;
    document.getElementById('k-datum').value      = new Date().toISOString().slice(0, 10);
    document.getElementById('k-hinweis').textContent =
        'Mietvertrag für "' + bezeichnung + '" (Mieter: ' + mieterName + ') beenden.';
    document.getElementById('modal-kuendigen').style.display = 'block';
}

async function vertragBeenden(e) {
    e.preventDefault();
    var res  = await fetch('/mealana/partner/vertrag_beenden.php', { method: 'POST', body: new FormData(e.target) });
    var data = await res.json();
    if (data.erfolg) { zeigeBanner('Vertrag beendet. Fach ist wieder frei.'); setTimeout(function () { location.reload(); }, 600); }
    else             { zeigeBanner(data.fehler.join(' | '), false); }
}

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ['modal-fach-neu', 'modal-fach-bearbeiten', 'modal-vertrag', 'modal-kuendigen']
        .forEach(function (id) { document.getElementById(id).style.display = 'none'; });
});
