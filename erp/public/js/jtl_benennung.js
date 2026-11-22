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

    // ✎ ändern: Zeile unter dem Vater mit Nummer + Hersteller einblenden
    function el(tag, attr, text) {
        var e = document.createElement(tag);
        Object.keys(attr || {}).forEach(function (k) { e.setAttribute(k, attr[k]); });
        if (text) e.textContent = text;
        return e;
    }

    document.querySelectorAll('.jb-aendern').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var zeile = btn.closest('tr');
            var offen = zeile.nextElementSibling;
            if (offen && offen.classList.contains('jb-edit')) { offen.remove(); return; }

            var d = btn.dataset;
            var tr = el('tr', { 'class': 'jb-edit' });
            var td = el('td', { colspan: '5', style: 'background:#EBF4FF;padding:8px 10px' });
            var box = el('div', { style: 'display:flex;gap:10px;align-items:end;flex-wrap:wrap' });

            var lNr = el('label', { style: 'font-size:12px' }, 'Neue Nummer (genau so, inkl. Kürzel)');
            var inNr = el('input', { type: 'text', 'class': 'erp-input', maxlength: '30',
                style: 'display:block;width:220px;font-family:monospace' });
            inNr.value = d.vorgabeNr || d.nrNeu;
            lNr.appendChild(inNr);

            var lH = el('label', { style: 'font-size:12px' }, 'Hersteller');
            var sel = el('select', { 'class': 'erp-select', style: 'display:block;width:240px' });
            sel.appendChild(el('option', { value: '' }, '(kein Hersteller)'));
            (window.JB_HERSTELLER || []).forEach(function (h) {
                var o = el('option', { value: h.id }, h.name + (h.kuerzel ? ' (' + h.kuerzel + ')' : ''));
                if (String(h.id) === String(d.hersteller)) o.selected = true;
                sel.appendChild(o);
            });
            lH.appendChild(sel);

            var speichern = el('button', { type: 'button', 'class': 'btn btn-primary btn-sm' }, 'Speichern');
            var zurueck = el('button', { type: 'button', 'class': 'btn btn-secondary btn-sm',
                title: 'Vorgabe löschen – es gilt wieder das Schema' }, 'Vorgabe löschen');
            var abbr = el('button', { type: 'button', 'class': 'btn btn-secondary btn-sm' }, 'Abbrechen');
            var hinweis = el('div', { style: 'font-size:11px;color:var(--color-text-muted);flex-basis:100%' },
                'Die Kinder bekommen die neue Nummer automatisch (z.B. SO-KAZ-7004-0001). Der Hersteller bestimmt das Kürzel, wenn die Nummer nicht vorgegeben ist.');

            [lNr, lH, speichern, zurueck, abbr, hinweis].forEach(function (x) { box.appendChild(x); });
            td.appendChild(box);
            tr.appendChild(td);
            zeile.parentNode.insertBefore(tr, zeile.nextSibling);
            inNr.focus();
            inNr.select();

            function schicken(nummerNeu, herstellerId) {
                speichern.disabled = zurueck.disabled = true;
                senden({ aktion: 'vorgabe', nr: d.nr, nummer_neu: nummerNeu, hersteller_id: herstellerId })
                    .then(function (r) {
                        if (!r.erfolg) { speichern.disabled = zurueck.disabled = false; alert(r.fehler || 'Fehler'); return; }
                        location.reload();
                    })
                    .catch(function () { speichern.disabled = zurueck.disabled = false; alert('Netzwerkfehler – nicht gespeichert'); });
            }

            speichern.addEventListener('click', function () {
                var nr = inNr.value.trim();
                // Nur vorgeben, was wirklich geändert wurde (sonst rechnet weiter das Schema)
                var nrNeu = (d.vorgabeNr || nr !== d.nrNeu) ? nr : '';
                var hId = (d.vorgabeHersteller || sel.value !== String(d.hersteller)) ? sel.value : '';
                schicken(nrNeu, hId);
            });
            inNr.addEventListener('keydown', function (e) { if (e.key === 'Enter') speichern.click(); });
            zurueck.addEventListener('click', function () { schicken('', ''); });
            abbr.addEventListener('click', function () { tr.remove(); });
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
