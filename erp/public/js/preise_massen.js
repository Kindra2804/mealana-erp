/**
 * Massenaktion "Preise ändern" (artikel/preise_massen.php)
 * Einstellungen → Vorschau (Tabelle alt → neu, neu je Zeile überschreibbar) → Übernehmen.
 * Kinder eines gewählten Vaters sind eingeklappt; Kinder mit eigenem Preis (!) bleiben sichtbar
 * und sind nicht angehakt. Ändert man den neuen Preis beim Vater, ziehen seine Kinder mit dem
 * gleichen Preis mit.
 */
(function () {
    var $ = function (id) { return document.getElementById(id); };
    if (!$('pm-vorschau')) return;

    var zeilen = [];       // letzte Vorschau
    var einstellung = {};  // Einstellungen der letzten Vorschau (fürs Speichern + Protokoll)

    var eur = function (v) { return v === null || v === undefined ? '–' : Number(v).toFixed(2).replace('.', ','); };
    var zahl = function (s) { var n = parseFloat(String(s).trim().replace(/\s/g, '').replace(',', '.')); return isNaN(n) ? null : n; };
    var esc = function (s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); };

    // Basis folgt dem Ziel, bis man sie selbst umstellt
    var basisVonHand = false;
    $('pm-basis').addEventListener('change', function () { basisVonHand = true; });
    $('pm-ziel').addEventListener('change', function () { if (!basisVonHand) $('pm-basis').value = this.value; });
    $('pm-art').addEventListener('change', function () {
        $('pm-wert-label').textContent = { prozent: 'Wert in %', euro: 'Wert in €', fest: 'neuer Preis in €' }[this.value];
        $('pm-basis').disabled = this.value === 'fest';
    });

    $('pm-vorschau').addEventListener('click', function () {
        var wert = zahl($('pm-wert').value);
        $('pm-fehler').textContent = '';
        if (wert === null || wert === 0) { $('pm-fehler').textContent = 'Bitte einen Wert eingeben.'; return; }
        einstellung = { ziel: $('pm-ziel').value, basis: $('pm-basis').value, art: $('pm-art').value, wert: wert, rundung: $('pm-rundung').value };
        var knopf = this; knopf.disabled = true;
        fetch(location.pathname, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ aktion: 'vorschau', ids: window.PM_IDS }, einstellung))
        }).then(function (r) { return r.json(); }).then(function (d) {
            knopf.disabled = false;
            if (!d.erfolg) { $('pm-fehler').textContent = d.fehler || 'Fehler'; return; }
            zeilen = d.zeilen;
            zeichnen(d);
        }).catch(function () { knopf.disabled = false; $('pm-fehler').textContent = 'Verbindung fehlgeschlagen'; });
    });

    function zeichnen(d) {
        letzteAntwort = d;
        var kinderJeVater = {};
        zeilen.forEach(function (z) { if (z.vater_id) (kinderJeVater[z.vater_id] = kinderJeVater[z.vater_id] || []).push(z); });

        $('pm-zeilen').innerHTML = zeilen.map(function (z, i) {
            var kinder = kinderJeVater[z.id] || [];
            var abwKinder = kinder.filter(function (k) { return k.abweichend; }).length;
            var sichtbar = !z.vater_id || z.abweichend || z.neu === null;
            var name = esc(z.artikelnummer) + ' · ' + esc(z.name) + (z.aktiv ? '' : ' <span style="color:var(--color-text-muted)">(inaktiv)</span>');
            if (kinder.length) {
                name = '<button type="button" class="btn btn-secondary btn-sm pm-auf" data-vater="' + z.id + '" style="padding:0 6px;margin-right:6px">▸ ' + kinder.length + '</button>' + name
                     + (abwKinder ? ' <span class="pm-ausruf" title="' + abwKinder + ' Kind(er) mit eigenem Preis">!</span>' : '');
            }
            var diff = z.neu !== null && z.alt !== null ? z.neu - z.alt : null;
            return '<tr data-i="' + i + '"' + (z.vater_id ? ' class="pm-kind" data-vater="' + z.vater_id + '"' : '') + (sichtbar ? '' : ' style="display:none"') + '>'
                + '<td><input type="checkbox" class="pm-an"' + (z.anhaken ? ' checked' : '') + (z.neu === null ? ' disabled' : '') + '></td>'
                + '<td>' + name + '</td>'
                + '<td style="text-align:right">' + eur(z.basis) + '</td>'
                + '<td style="text-align:right">' + eur(z.alt) + '</td>'
                + '<td style="text-align:right">' + (z.neu === null ? '–' : '<input type="text" class="erp-input pm-neu" inputmode="decimal" value="' + eur(z.neu) + '">') + '</td>'
                + '<td style="text-align:right" class="pm-diff">' + diffText(diff, z.alt) + '</td>'
                + '<td style="font-size:12px">' + (z.abweichend ? '<span class="pm-ausruf">!</span> ' : '') + esc(z.hinweis) + '</td></tr>';
        }).join('');

        var abw = zeilen.filter(function (z) { return z.abweichend; }).length;
        $('pm-abw-alle-wrap').style.display = abw ? '' : 'none';
        $('pm-abw-alle').checked = false;
        $('pm-ergebnis').style.display = '';
        zusammenfassung(d);
    }

    function diffText(diff, alt) {
        if (diff === null) return '';
        var pz = alt ? ' (' + (diff >= 0 ? '+' : '') + (diff / alt * 100).toFixed(1).replace('.', ',') + ' %)' : '';
        return (diff >= 0 ? '+' : '') + eur(diff) + pz;
    }

    function zusammenfassung(d) {
        var an = document.querySelectorAll('#pm-zeilen .pm-an:checked').length;
        $('pm-zusammenfassung').innerHTML = '<b>' + an + '</b> von ' + zeilen.length + ' Artikeln werden geändert'
            + (d ? ' · ' + esc(d.ziel_name) + (einstellung.art === 'fest' ? '' : ' aus ' + esc(d.basis_name)) : '');
    }
    var letzteAntwort = null;

    // Ein-/Ausklappen der Kinder
    $('pm-zeilen').addEventListener('click', function (e) {
        var b = e.target.closest('.pm-auf');
        if (!b) return;
        var auf = b.textContent.charAt(0) === '▸';
        b.textContent = (auf ? '▾ ' : '▸ ') + b.textContent.slice(2);
        document.querySelectorAll('#pm-zeilen tr[data-vater="' + b.dataset.vater + '"]').forEach(function (tr) {
            var z = zeilen[tr.dataset.i];
            tr.style.display = auf || z.abweichend || z.neu === null ? '' : 'none';
        });
    });

    // Haken + Preis von Hand
    $('pm-zeilen').addEventListener('change', function (e) {
        var tr = e.target.closest('tr');
        var z = zeilen[tr.dataset.i];
        if (e.target.classList.contains('pm-neu')) {
            var n = zahl(e.target.value);
            if (n === null || n <= 0) { e.target.value = eur(z.neu); return; }
            var vorher = z.neu;
            z.neu = Math.round(n * 100) / 100;
            e.target.value = eur(z.neu);
            e.target.classList.add('pm-hand');
            tr.querySelector('.pm-diff').textContent = diffText(z.alt !== null ? z.neu - z.alt : null, z.alt);
            tr.querySelector('.pm-an').checked = true;
            // Kinder mit gleichem Preis wie der Vater ziehen mit (nicht die mit eigenem Preis)
            document.querySelectorAll('#pm-zeilen tr[data-vater="' + z.id + '"]').forEach(function (ktr) {
                var k = zeilen[ktr.dataset.i];
                var inp = ktr.querySelector('.pm-neu');
                if (!inp || k.abweichend || inp.classList.contains('pm-hand') || Math.abs(k.neu - vorher) > 0.004) return;
                k.neu = z.neu;
                inp.value = eur(k.neu);
                ktr.querySelector('.pm-diff').textContent = diffText(k.alt !== null ? k.neu - k.alt : null, k.alt);
                ktr.querySelector('.pm-an').checked = true;
            });
        }
        zusammenfassung(letzteAntwort);
    });

    $('pm-alle').addEventListener('change', function () {
        var an = this.checked;
        document.querySelectorAll('#pm-zeilen .pm-an:not(:disabled)').forEach(function (c) {
            if (!zeilen[c.closest('tr').dataset.i].abweichend || $('pm-abw-alle').checked) c.checked = an;
        });
        zusammenfassung(letzteAntwort);
    });
    $('pm-abw-alle').addEventListener('change', function () {
        var an = this.checked;
        document.querySelectorAll('#pm-zeilen tr').forEach(function (tr) {
            var z = zeilen[tr.dataset.i];
            if (z.abweichend && z.neu !== null) tr.querySelector('.pm-an').checked = an;
        });
        zusammenfassung(letzteAntwort);
    });

    $('pm-speichern').addEventListener('click', function () {
        var positionen = [];
        document.querySelectorAll('#pm-zeilen tr').forEach(function (tr) {
            var z = zeilen[tr.dataset.i];
            if (tr.querySelector('.pm-an').checked && z.neu !== null) positionen.push({ id: z.id, neu: z.neu });
        });
        if (!positionen.length) { $('pm-fehler').textContent = 'Keine Artikel angehakt.'; return; }
        if (!confirm(positionen.length + ' Preise jetzt ändern?')) return;
        var knopf = this; knopf.disabled = true;
        fetch(location.pathname, {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ aktion: 'speichern', ziel: einstellung.ziel, positionen: positionen, einstellung: einstellung })
        }).then(function (r) { return r.json(); }).then(function (d) {
            knopf.disabled = false;
            if (!d.erfolg) { $('pm-fehler').textContent = d.fehler || 'Fehler'; return; }
            var t = d.anzahl + ' Preise geändert. Shop-Abgleich und „Schilder veraltet“ ziehen nach.';
            if (typeof showFlash === 'function') showFlash(t, 'erfolg'); else alert(t);
            // Nicht automatisch neu rechnen (sonst käme z.B. +5 % ein zweites Mal drauf)
            $('pm-ergebnis').style.display = 'none';
            $('pm-wert').value = '';
            $('pm-fehler').innerHTML = '<span style="color:var(--color-success,#15803d)">✓ ' + esc(t) + '</span>';
        }).catch(function () { knopf.disabled = false; $('pm-fehler').textContent = 'Verbindung fehlgeschlagen'; });
    });

})();
