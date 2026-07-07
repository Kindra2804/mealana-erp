// Arbeitsplatz-Erkennung für kasse/index.php — siehe ArbeitsplatzService.php für die
// serverseitige Logik. Ablauf: Token aus localStorage lesen -> Zustand beim Server
// erfragen -> je nach Zustand Auswahl-Overlay oder Kollisions-Overlay zeigen.

var AP_TOKEN_KEY = 'arbeitsplatz_token';

document.addEventListener('DOMContentLoaded', function () {
    apPruefeZustand();
});

function apToken() {
    return localStorage.getItem(AP_TOKEN_KEY) || '';
}

function apOverlayZeigen(id) {
    document.getElementById(id).classList.add('aktiv');
}
function apOverlayVerbergen(id) {
    document.getElementById(id).classList.remove('aktiv');
}
function apZeigeFehler(id, text) {
    var el = document.getElementById(id);
    el.textContent = text;
    el.style.display = text ? 'block' : 'none';
}

function apPruefeZustand() {
    var params = new URLSearchParams({ aktion: 'status', token: apToken() });
    fetch(window.BASE_PATH + '/kasse/ajax_arbeitsplatz.php?' + params.toString())
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.status === 'unbekannt') {
                apZeigeAuswahl(d.kassen);
            } else if (d.status === 'kollision') {
                apZeigeKollision(d.arbeitsplatz, d.andere_session);
            }
            // status === 'gebunden' -> nichts zu tun, normale Seite bleibt sichtbar
        })
        .catch(function () {
            // Netzwerkfehler beim Start blockiert die Kasse nicht — einfach normal weiterlaufen lassen
        });
}

// ── Auswahl-Screen ───────────────────────────────────────────────────────────
function apZeigeAuswahl(kassen) {
    var liste = document.getElementById('ap-kassen-liste');
    liste.innerHTML = '';
    (kassen || []).forEach(function (k) {
        var label = document.createElement('label');
        label.className = 'ap-radio-zeile';
        label.innerHTML = '<input type="radio" name="ap-modus" value="kasse" data-kasse-id="' + k.id + '"> ' +
            k.name + ' <span class="ap-muted">(' + k.kasse_nr + ')</span>';
        liste.appendChild(label);
    });
    var sonstLabel = document.createElement('label');
    sonstLabel.className = 'ap-radio-zeile';
    sonstLabel.innerHTML = '<input type="radio" name="ap-modus" value="sonstiges"> Anderer Arbeitsplatz…';
    liste.appendChild(sonstLabel);

    apZeigeFehler('ap-fehler', '');
    apOverlayZeigen('ap-ov-auswahl');
}

document.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'ap-modus') {
        document.getElementById('ap-sonstiges-felder').style.display =
            e.target.value === 'sonstiges' ? 'block' : 'none';
    }
});

function apAuswahlBestaetigen() {
    var gewaehlt = document.querySelector('input[name="ap-modus"]:checked');
    if (!gewaehlt) {
        apZeigeFehler('ap-fehler', 'Bitte einen Arbeitsplatz auswählen.');
        return;
    }

    var form = new FormData();
    form.append('aktion', 'waehlen');
    form.append('modus', gewaehlt.value);
    if (gewaehlt.value === 'kasse') {
        form.append('kasse_id', gewaehlt.dataset.kasseId);
    } else {
        form.append('typ', document.getElementById('ap-typ').value);
        form.append('name', document.getElementById('ap-name').value);
    }

    fetch(window.BASE_PATH + '/kasse/ajax_arbeitsplatz.php', { method: 'POST', body: form })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) {
                localStorage.setItem(AP_TOKEN_KEY, d.geraete_token);
                apOverlayVerbergen('ap-ov-auswahl');
                location.reload();
            } else {
                apZeigeFehler('ap-fehler', d.fehler || 'Unbekannter Fehler.');
            }
        })
        .catch(function () {
            apZeigeFehler('ap-fehler', 'Netzwerkfehler — bitte erneut versuchen.');
        });
}

// ── Kollisions-Screen ─────────────────────────────────────────────────────────
var apKollisionArbeitsplatzId = null;

function apZeigeKollision(arbeitsplatz, andereSession) {
    apKollisionArbeitsplatzId = arbeitsplatz.id;
    document.getElementById('ap-kollision-text').textContent =
        arbeitsplatz.name + ' wird bereits verwendet — aktiv seit ' +
        (andereSession.formularname || 'einem anderen Benutzer') + ', zuletzt ' +
        andereSession.letzte_aktivitaet + '.';
    document.getElementById('ap-kollision-pin').value = '';
    apZeigeFehler('ap-kollision-fehler', '');
    apOverlayZeigen('ap-ov-kollision');
}

function apKollisionUebernehmen() {
    var pin = document.getElementById('ap-kollision-pin').value.trim();
    if (!/^\d{4,6}$/.test(pin)) {
        apZeigeFehler('ap-kollision-fehler', 'PIN muss 4-6 Ziffern haben.');
        return;
    }

    var form = new FormData();
    form.append('aktion', 'kollision_uebernehmen');
    form.append('arbeitsplatz_id', apKollisionArbeitsplatzId);
    form.append('manager_pin', pin);
    form.append('token', apToken());

    fetch(window.BASE_PATH + '/kasse/ajax_arbeitsplatz.php', { method: 'POST', body: form })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) {
                apOverlayVerbergen('ap-ov-kollision');
                location.reload();
            } else {
                apZeigeFehler('ap-kollision-fehler', d.fehler || 'Unbekannter Fehler.');
            }
        })
        .catch(function () {
            apZeigeFehler('ap-kollision-fehler', 'Netzwerkfehler — bitte erneut versuchen.');
        });
}
