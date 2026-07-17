document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.db-aktion-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const auftragId = btn.dataset.auftragId;
            const aktion    = btn.dataset.aktion;

            const bestaetigungstext = aktion === 'stornierung'
                ? 'Auftrag wirklich stornieren? Lagerbestand wird zurückgebucht, Kunde bekommt eine Stornierungsmail. Das kann nicht rückgängig gemacht werden.'
                : 'Zahlungserinnerung jetzt manuell senden?';
            if (!confirm(bestaetigungstext)) return;

            btn.disabled = true;
            const alterText = btn.textContent;
            btn.textContent = '...';

            fetch(window.BASE_PATH + '/auftraege/mahnung_manuell_ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'auftrag_id=' + encodeURIComponent(auftragId) + '&aktion=' + encodeURIComponent(aktion)
            })
                .then(function (r) { return r.json(); })
                .then(function (ergebnis) {
                    if (ergebnis.erfolg) {
                        window.location.reload();
                    } else {
                        alert('Fehler: ' + (ergebnis.fehler || 'Unbekannter Fehler'));
                        btn.disabled = false;
                        btn.textContent = alterText;
                    }
                })
                .catch(function () {
                    alert('Netzwerkfehler — bitte nochmal versuchen.');
                    btn.disabled = false;
                    btn.textContent = alterText;
                });
        });
    });
});
