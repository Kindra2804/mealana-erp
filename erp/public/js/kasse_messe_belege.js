// Papier-Messe: händische Belege nacherfassen — siehe kasse/messe_belege.php
// Ablauf pro Beleg: Nr./Datum/Zahlart (bleiben vom letzten Beleg stehen) →
// Zeilen Gruppe + Betrag (Steuer aus der Gruppe vorbelegt, änderbar) → Enter/Button.

var mbZahlartAktiv = MB_ZAHLART || 'bar';

document.addEventListener('DOMContentLoaded', function () {
    mbZahlart(mbZahlartAktiv);
    mbZeile();
    var nr = document.getElementById('mb-nr');
    (nr.value ? document.querySelector('#mb-zeilen .mb-betrag') : nr).focus();
});

function mbZahlart(za) {
    mbZahlartAktiv = za;
    document.querySelectorAll('.mb-zahlart button').forEach(function (b) {
        b.classList.toggle('aktiv', b.dataset.za === za);
    });
}

function mbStandardGruppe() {
    return MB_GRUPPEN.find(function (g) { return g.ist_standard == 1; }) || MB_GRUPPEN[0] || null;
}

function mbZeile() {
    var tr = document.createElement('tr');
    var std = mbStandardGruppe();
    tr.innerHTML =
        '<td><select class="ks-select mb-gruppe" onchange="mbGruppeGeaendert(this)">' +
            MB_GRUPPEN.map(function (g) {
                return '<option value="' + g.id + '"' + (std && g.id == std.id ? ' selected' : '') + '>' +
                       mbEsc(g.name) + ' (' + mbEsc(g.konto_nr) + ')</option>';
            }).join('') +
        '</select></td>' +
        '<td style="text-align:right"><input type="text" inputmode="decimal" class="ks-input mb-betrag" placeholder="0,00" ' +
            'style="width:120px;text-align:right" oninput="mbSumme()" onkeydown="mbEnter(event)"></td>' +
        '<td><select class="ks-select mb-steuer">' +
            '<option value="20">20 %</option><option value="13">13 %</option><option value="10">10 %</option><option value="0">0 %</option>' +
        '</select></td>' +
        '<td><button type="button" class="ks-btn ks-btn-secondary" style="padding:3px 8px" onclick="mbZeileWeg(this)">✕</button></td>';
    document.getElementById('mb-zeilen').appendChild(tr);
    mbGruppeGeaendert(tr.querySelector('.mb-gruppe'));
    return tr;
}

function mbZeileWeg(btn) {
    var tbody = document.getElementById('mb-zeilen');
    if (tbody.children.length > 1) btn.closest('tr').remove();
    mbSumme();
}

// Steuersatz aus der Gruppe vorbelegen (Standard-Steuer, sonst 20 %)
function mbGruppeGeaendert(sel) {
    var g = MB_GRUPPEN.find(function (x) { return x.id == sel.value; });
    var satz = g && g.standard_steuer_prozent !== null ? parseFloat(g.standard_steuer_prozent) : 20;
    sel.closest('tr').querySelector('.mb-steuer').value = String(satz);
}

function mbBetrag(input) {
    return Math.round((parseFloat(input.value.replace(',', '.')) || 0) * 100) / 100;
}

function mbSumme() {
    var s = 0;
    document.querySelectorAll('#mb-zeilen .mb-betrag').forEach(function (i) { s += mbBetrag(i); });
    document.getElementById('mb-summe').textContent = s.toFixed(2).replace('.', ',');
    return Math.round(s * 100) / 100;
}

// Enter im Betragsfeld = Beleg erfassen
function mbEnter(e) {
    if (e.key === 'Enter') { e.preventDefault(); mbErfassen(); }
}

function mbErfassen() {
    var fb  = document.getElementById('mb-feedback');
    var nr  = document.getElementById('mb-nr').value.trim();
    var dat = document.getElementById('mb-datum').value;
    var zeilen = [];
    document.querySelectorAll('#mb-zeilen tr').forEach(function (tr) {
        var betrag = mbBetrag(tr.querySelector('.mb-betrag'));
        if (betrag <= 0) return;
        zeilen.push({
            artikel_gruppe_id: tr.querySelector('.mb-gruppe').value,
            betrag:            betrag,
            steuer_prozent:    parseFloat(tr.querySelector('.mb-steuer').value),
        });
    });

    if (!nr)            { fb.innerHTML = '<div class="ks-feedback fehler">Bitte die Beleg-Nr. eingeben.</div>'; return; }
    if (!dat)           { fb.innerHTML = '<div class="ks-feedback fehler">Bitte das Belegdatum eingeben.</div>'; return; }
    if (!zeilen.length) { fb.innerHTML = '<div class="ks-feedback fehler">Bitte einen Betrag eingeben.</div>'; return; }

    var btn = document.getElementById('mb-btn');
    btn.disabled = true;
    fb.innerHTML = '<div class="ks-feedback info">Wird erfasst …</div>';

    var fd = new FormData();
    fd.append('aktion', 'beleg_nacherfassen');
    fd.append('sync_id', MB_SYNC_ID);
    fd.append('beleg_nr', nr);
    fd.append('beleg_datum', dat);
    fd.append('zahlungsart', mbZahlartAktiv);
    fd.append('zeilen', JSON.stringify(zeilen));

    fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.erfolg) {
                fb.innerHTML = '<div class="ks-feedback fehler">' + mbEsc(d.fehler || 'Unbekannter Fehler') + '</div>';
                btn.disabled = false;
                return;
            }
            // Seite neu laden: Liste + Summen aktualisiert, nächste Nr. vorgeschlagen
            location.reload();
        })
        .catch(function () {
            fb.innerHTML = '<div class="ks-feedback fehler">Netzwerkfehler — bitte prüfen, ob der Beleg schon in der Liste steht, bevor erneut erfasst wird.</div>';
            btn.disabled = false;
        });
}

function mbEsc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
}
