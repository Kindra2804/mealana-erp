document.addEventListener('DOMContentLoaded', function () {
    var fragen = {
        freigeben:      'Mahnung jetzt freigeben? Das PDF wird erstellt und per Mail an den Kunden geschickt, die Mahngebühr wird fällig.',
        verwerfen:      'Vorschlag verwerfen? Für diesen Auftrag wird diese Stufe dann nicht mehr vorgeschlagen.',
        erlassen:       'Mahngebühr erlassen? Sie wird dem Kunden nicht mehr verrechnet.',
        alle_freigeben: 'Alle vorgeschlagenen Mahnungen freigeben und verschicken?'
    };

    document.querySelectorAll('.mw-aktion').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var aktion = btn.dataset.aktion;
            if (!confirm(fragen[aktion])) return;

            var alterText = btn.textContent;
            btn.disabled = true;
            btn.textContent = '...';

            fetch(window.BASE_PATH + '/auftraege/mahnwesen_aktion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'aktion=' + encodeURIComponent(aktion) + '&mahnung_id=' + encodeURIComponent(btn.dataset.id || '')
            })
                .then(function (r) { return r.json(); })
                .then(function (ergebnis) {
                    if (!ergebnis.erfolg) {
                        alert('Fehler: ' + (ergebnis.fehler || 'Unbekannter Fehler'));
                        btn.disabled = false;
                        btn.textContent = alterText;
                        return;
                    }
                    var hinweise = [];
                    if (ergebnis.ohne_email) hinweise.push('Kunde hat keine E-Mail-Adresse — bitte das PDF ausdrucken und per Post schicken (Verlauf → PDF).');
                    if (ergebnis.mail_fehler) hinweise.push('Mail konnte nicht gesendet werden: ' + ergebnis.mail_fehler);
                    if (ergebnis.fehler_liste && ergebnis.fehler_liste.length) hinweise.push('Nicht freigegeben:\n' + ergebnis.fehler_liste.join('\n'));
                    if (hinweise.length) alert(hinweise.join('\n\n'));
                    window.location.reload();
                })
                .catch(function () {
                    alert('Netzwerkfehler — bitte nochmal versuchen.');
                    btn.disabled = false;
                    btn.textContent = alterText;
                });
        });
    });
});
