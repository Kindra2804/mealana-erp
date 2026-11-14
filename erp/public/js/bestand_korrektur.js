/**
 * Bestand korrigieren (Artikel → Lager). Aufruf: bestandKorrekturOeffnen(artikelId)
 * Lager wählen → Zeile (Charge) wählen → neuen Bestand + Grund → Buchen (artikel/bestand_korrigieren.php).
 * Danach lädt die Seite neu (Bestandsübersicht + Bewegungslog).
 */
function bestandKorrekturOeffnen(artikelId) {
    fetch(window.BASE_PATH + '/artikel/bestand_korrigieren.php?artikel_id=' + artikelId).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.erfolg) { alert(d.fehler || 'Fehler'); return; }
        var esc = function (s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); };
        var zahl = function (v) { return (Math.round(v * 1000) / 1000).toString().replace('.', ','); };
        var alt = document.getElementById('bk-dlg'); if (alt) alt.remove();
        var bd = document.createElement('div');
        bd.id = 'bk-dlg';
        bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1600;display:flex;align-items:center;justify-content:center';
        bd.innerHTML = '<div style="background:#fff;border-radius:8px;padding:20px;width:440px;box-shadow:0 8px 32px rgba(0,0,0,.2)">'
            + '<div style="font-weight:700;font-size:14px;margin-bottom:12px;color:var(--color-nav)">Bestand korrigieren</div>'
            + '<label class="form-label" style="display:block">Lager</label>'
            + '<select id="bk-lager" class="erp-select" style="width:100%;margin-bottom:8px">' + d.lager.map(function (l) {
                return '<option value="' + l.id + '">' + esc(l.name) + ' (Bestand ' + zahl(l.gesamt) + ')</option>'; }).join('') + '</select>'
            + '<div id="bk-zeile-wrap"><label class="form-label" style="display:block">Charge</label><select id="bk-zeile" class="erp-select" style="width:100%;margin-bottom:8px"></select></div>'
            + '<div style="display:flex;gap:10px;align-items:end;margin-bottom:8px">'
            + '<div><label class="form-label" style="display:block">bisher</label><div id="bk-vorher" style="font-size:16px;font-weight:700;padding:6px 0">0</div></div>'
            + '<div><label class="form-label" style="display:block">neuer Bestand *</label><input type="text" id="bk-neu" class="erp-input" style="width:110px" inputmode="decimal" autocomplete="off"></div>'
            + '<div id="bk-diff" style="font-size:13px;padding-bottom:8px"></div></div>'
            + '<label class="form-label" style="display:block">Grund *</label>'
            + '<select id="bk-grund" class="erp-select" style="width:100%;margin-bottom:8px"><option value="">– bitte wählen –</option>'
            + Object.keys(d.gruende).map(function (k) { return '<option value="' + k + '">' + esc(d.gruende[k]) + '</option>'; }).join('') + '</select>'
            + '<label class="form-label" style="display:block">Notiz</label><input type="text" id="bk-notiz" class="erp-input" style="width:100%" maxlength="200" autocomplete="off" placeholder="z.B. versehentlich eingebucht">'
            + '<div id="bk-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">'
            + '<button type="button" class="btn btn-secondary btn-sm" id="bk-ab">Abbrechen</button>'
            + '<button type="button" class="btn btn-primary btn-sm" id="bk-ok">Korrektur buchen</button></div></div>';
        document.body.appendChild(bd);
        var $ = function (id) { return document.getElementById(id); };

        function zeilen() {
            var l = d.lager.find(function (x) { return x.id === parseInt($('bk-lager').value, 10); });
            var z = l.zeilen.slice();
            if (!z.some(function (r) { return r.charge === null; })) z.unshift({ charge: null, bestand: 0 });   // „ohne Charge“ immer wählbar
            $('bk-zeile').innerHTML = z.map(function (r, i) {
                return '<option value="' + i + '" data-charge="' + esc(r.charge === null ? '' : r.charge) + '" data-bestand="' + r.bestand + '">'
                    + (r.charge === null ? 'ohne Charge' : 'Charge ' + esc(r.charge)) + ' – Bestand ' + zahl(r.bestand) + '</option>';
            }).join('');
            var mitBestand = z.findIndex(function (r) { return r.bestand > 0; });   // Zeile mit Bestand vorwählen
            if (mitBestand >= 0) $('bk-zeile').value = String(mitBestand);
            $('bk-zeile-wrap').style.display = z.length > 1 ? '' : 'none';
            vorher();
        }
        function vorher() {
            $('bk-vorher').textContent = zahl(parseFloat($('bk-zeile').selectedOptions[0].dataset.bestand));
            diff();
        }
        function diff() {
            var o = $('bk-zeile').selectedOptions[0], n = parseFloat(String($('bk-neu').value).replace(',', '.'));
            if (isNaN(n)) { $('bk-diff').textContent = ''; return; }
            var dd = Math.round((n - parseFloat(o.dataset.bestand)) * 1000) / 1000;
            $('bk-diff').innerHTML = dd === 0 ? '' : '<b style="color:' + (dd < 0 ? 'var(--color-danger)' : 'var(--color-success,#15803d)') + '">' + (dd > 0 ? '+' : '') + zahl(dd) + '</b>';
        }
        $('bk-lager').addEventListener('change', zeilen);
        $('bk-zeile').addEventListener('change', vorher);
        $('bk-neu').addEventListener('input', diff);
        zeilen();
        $('bk-neu').focus();

        var zu = function () { bd.remove(); };
        $('bk-ab').addEventListener('click', zu);
        bd.addEventListener('click', function (e) { if (e.target === bd) zu(); });
        $('bk-ok').addEventListener('click', function () {
            var knopf = this, o = $('bk-zeile').selectedOptions[0];
            if ($('bk-neu').value.trim() === '') { $('bk-fehler').textContent = 'Bitte den neuen Bestand eintragen.'; return; }
            knopf.disabled = true;
            fetch(window.BASE_PATH + '/artikel/bestand_korrigieren.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ artikel_id: artikelId, lager_id: parseInt($('bk-lager').value, 10), charge: o.dataset.charge,
                                       neu: $('bk-neu').value, grund: $('bk-grund').value, notiz: $('bk-notiz').value })
            }).then(function (r) { return r.json(); }).then(function (r) {
                if (!r.erfolg) { $('bk-fehler').textContent = r.fehler || 'Fehler'; knopf.disabled = false; return; }
                location.href = 'detail.php?id=' + artikelId + '&tab=lager';
            });
        });
    });
}
