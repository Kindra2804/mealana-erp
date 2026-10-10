/**
 * Messe-Abschluss (Papier-Messe): optionale Begründung der Differenz
 * Belege ↔ Strichliste speichern. Nach dem Speichern wird neu geladen,
 * damit der Text auch im Druckbereich steht.
 */
(function () {
    var knopf = document.getElementById('diff-speichern');
    if (!knopf) return;

    knopf.addEventListener('click', function () {
        var status = document.getElementById('diff-status');
        var fd = new FormData();
        fd.append('aktion', 'differenz_begruendung');
        fd.append('sync_id', knopf.dataset.syncId);
        fd.append('text', document.getElementById('diff-begruendung').value);

        knopf.disabled = true;
        fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.erfolg) {
                    status.textContent = d.fehler || 'Speichern fehlgeschlagen.';
                    knopf.disabled = false;
                    return;
                }
                location.reload();
            })
            .catch(function () {
                status.textContent = 'Netzwerkfehler — bitte nochmal versuchen.';
                knopf.disabled = false;
            });
    });
})();
