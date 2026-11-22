/**
 * JTL: neue Nummern (artikel/jtl_benennung.php)
 *  - Häkchen "übernehmen" je Vater/Einzelartikel: speichert sofort (import_ausschluss + import_geprueft)
 *  - "Seite als geprüft markieren": alle noch ungeprüften Väter/Einzelartikel der Seite bestätigen
 */
(function () {
    var URL = window.BASE_PATH + '/artikel/jtl_benennung.php';

    function senden(daten) {
        return fetch(URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(daten)
        }).then(function (r) { return r.json(); });
    }

    // Häkchen: Zeile + Kinder ausgrauen, "noch nicht geprüft" verschwindet
    document.querySelectorAll('.jb-uebernehmen').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var an = cb.checked;
            cb.disabled = true;
            senden({ aktion: 'auswahl', nr: cb.dataset.nr, name: cb.dataset.name, uebernehmen: an })
                .then(function (d) {
                    cb.disabled = false;
                    if (!d.erfolg) { cb.checked = !an; alert(d.fehler || 'Fehler beim Speichern'); return; }
                    var zeile = cb.closest('tr');
                    zeile.style.opacity = an ? '' : '.55';
                    document.querySelectorAll('tr[data-vater="' + CSS.escape(cb.dataset.nr) + '"]').forEach(function (k) {
                        k.style.opacity = an ? '' : '.55';
                    });
                    zeile.querySelectorAll('span').forEach(function (s) {
                        if (s.textContent === 'noch nicht geprüft') s.remove();
                    });
                })
                .catch(function () { cb.disabled = false; cb.checked = !an; alert('Netzwerkfehler – nicht gespeichert'); });
        });
    });

    var knopf = document.getElementById('jb-seite-geprueft');
    if (knopf) {
        knopf.addEventListener('click', function () {
            var nummern = JSON.parse(knopf.dataset.nummern || '[]');
            if (!nummern.length) return;
            knopf.disabled = true;
            senden({ aktion: 'geprueft', nummern: nummern })
                .then(function (d) {
                    if (!d.erfolg) { knopf.disabled = false; alert(d.fehler || 'Fehler'); return; }
                    location.reload();
                })
                .catch(function () { knopf.disabled = false; alert('Netzwerkfehler'); });
        });
    }
})();
