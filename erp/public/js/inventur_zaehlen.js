function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent      = msg;
    b.style.background = ok ? '#2ecc71' : '#e74c3c';
    b.style.color      = '#fff';
    b.style.display    = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

async function buchePosition(payload) {
    var res = await fetch(window.BASE_PATH + '/inventur/zaehlung_speichern.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    });
    return res.json();
}

// --- Zeilen aus der Soll-Liste speichern ---
document.querySelectorAll('#zaehl-tabelle .zeile-speichern').forEach(function (btn) {
    btn.addEventListener('click', async function () {
        var tr = btn.closest('tr');
        var istInput   = tr.querySelector('.ist-eingabe');
        var notizInput = tr.querySelector('.notiz-eingabe');
        if (istInput.value === '') { zeigeBanner('Bitte eine Menge eingeben.', false); return; }

        var data = await buchePosition({
            lauf_id:       window.INVENTUR_LAUF_ID,
            artikel_id:    parseInt(tr.dataset.artikel, 10),
            lager_id:      parseInt(tr.dataset.lager, 10),
            lagerplatz_id: tr.dataset.lagerplatz ? parseInt(tr.dataset.lagerplatz, 10) : null,
            charge:        tr.dataset.charge || null,
            soll_menge:    tr.dataset.soll !== '' ? parseFloat(tr.dataset.soll) : null,
            ist_menge:     parseFloat(istInput.value),
            notiz:         notizInput.value || null,
        });

        if (data.erfolg) {
            zeigeBanner('Gespeichert.');
            tr.style.background = '#e6f7ee';
        } else {
            zeigeBanner(data.fehler.join(' | '), false);
        }
    });
});

// --- Neue Position (Artikel-Suche) ---
(function () {
    var suchfeld = document.getElementById('neu_artikel_suche');
    var treffer  = document.getElementById('neu_artikel_treffer');
    var hiddenId = document.getElementById('neu_artikel_id');
    var gewaehlt = document.getElementById('neu_gewaehlt');
    var timer;

    suchfeld.addEventListener('input', function () {
        clearTimeout(timer);
        hiddenId.value = '';
        gewaehlt.textContent = '';
        var q = suchfeld.value.trim();
        if (q.length < 2) { treffer.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(window.BASE_PATH + '/inventur/artikel_suche_ajax.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    treffer.innerHTML = '';
                    data.forEach(function (a) {
                        var div = document.createElement('div');
                        div.style.cssText = 'padding:6px 10px;cursor:pointer;font-size:13px';
                        div.textContent = a.name + ' (' + a.artikelnummer + ')';
                        div.onclick = function () {
                            hiddenId.value = a.id;
                            gewaehlt.textContent = 'Gewählt: ' + a.name + ' (' + a.artikelnummer + ')';
                            suchfeld.value = a.name;
                            treffer.style.display = 'none';
                        };
                        treffer.appendChild(div);
                    });
                    treffer.style.display = data.length ? 'block' : 'none';
                });
        }, 250);
    });
})();

async function neuePositionSpeichern() {
    var artikelId = document.getElementById('neu_artikel_id').value;
    var menge     = document.getElementById('neu_menge').value;
    if (!artikelId) { zeigeBanner('Bitte zuerst einen Artikel aus der Trefferliste wählen.', false); return; }
    if (menge === '') { zeigeBanner('Bitte eine Menge eingeben.', false); return; }

    // Lager/Lagerplatz ergibt sich meist direkt aus dem Scope des Laufs;
    // nur bei Kategorie/Artikel/Mietfach-Scope muss der Zähler es selbst wählen.
    var lagerId      = null;
    var lagerplatzId = null;
    if (window.INVENTUR_SCOPE_TABELLE === 'lager') {
        lagerId = window.INVENTUR_SCOPE_ID;
    } else if (window.INVENTUR_SCOPE_TABELLE === 'lagerplaetze') {
        lagerplatzId = window.INVENTUR_SCOPE_ID;
    } else {
        var lagerSelect = document.getElementById('neu_lager_id');
        if (lagerSelect) lagerId = parseInt(lagerSelect.value, 10);
    }

    var data = await buchePosition({
        lauf_id:    window.INVENTUR_LAUF_ID,
        artikel_id: parseInt(artikelId, 10),
        lager_id:   lagerId,
        lagerplatz_id: lagerplatzId,
        charge:     document.getElementById('neu_charge').value || null,
        soll_menge: null,
        ist_menge:  parseFloat(menge),
        notiz:      document.getElementById('neu_notiz').value || null,
    });

    if (data.erfolg) {
        zeigeBanner('Erfasst — Seite lädt neu, damit die Position in der Liste erscheint.');
        setTimeout(function () { location.reload(); }, 800);
    } else {
        zeigeBanner(data.fehler.join(' | '), false);
    }
}
