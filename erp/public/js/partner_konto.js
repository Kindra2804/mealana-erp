// Partner-Detail, Reiter "Konto & Auszahlung" (Fremdrechnung/Spende) — PartnerKontoService

function azArtWechsel() {
    document.getElementById('az-kasse-box').style.display =
        document.getElementById('az-art').value === 'bar_kasse' ? '' : 'none';
}

function azBuchen(partnerId, von, bis) {
    var art = document.getElementById('az-art').value;
    var daten = {
        partner_id:   partnerId,
        betrag:       document.getElementById('az-betrag').value,
        datum:        document.getElementById('az-datum').value,
        art:          art,
        kasse_id:     art === 'bar_kasse' ? document.getElementById('az-kasse').value : null,
        notiz:        document.getElementById('az-notiz').value,
        zeitraum_von: von,
        zeitraum_bis: bis
    };
    var fehler = document.getElementById('az-fehler');
    fehler.textContent = '';
    fetch(window.BASE_PATH + '/partner/auszahlung_buchen.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten)
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.erfolg) { fehler.textContent = (d.fehler || ['Fehler']).join(' '); return; }
            // Aviso gleich zum Drucken/Unterschreiben öffnen
            window.open(window.BASE_PATH + '/partner/abrechnung_pdf.php?id=' + partnerId + '&aviso=' + d.id, '_blank');
            location.reload();
        })
        .catch(function () { fehler.textContent = 'Netzwerkfehler.'; });
}

function azStorno(id, nummer) {
    if (!confirm('Auszahlung ' + nummer + ' stornieren?\nBar aus der Kasse wird per Kassenbuch-Einlage zurückgebucht.')) return;
    fetch(window.BASE_PATH + '/partner/auszahlung_storno.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id })
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d.erfolg) { alert((d.fehler || ['Fehler']).join(' ')); return; }
            location.reload();
        });
}

document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('az-art')) azArtWechsel();
});
