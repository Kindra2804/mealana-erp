/**
 * Massenaktion "Merkmal setzen" (Artikelliste → artikel/merkmal_massen.php).
 * Fenster: Merkmal wählen → Werte anhaken (mit Suche) → Hinzufügen / Ersetzen / Entfernen.
 * Gewählte Väter nehmen ihre Farben mit. Aufruf: merkmalMassenDialog([artikelId, ...])
 */
function merkmalMassenDialog(ids) {
    ids = (ids || []).map(Number).filter(Boolean);
    if (!ids.length) { alert('Bitte Artikel auswählen'); return; }

    fetch(window.BASE_PATH + '/artikel/merkmal_massen.php').then(r => r.json()).then(function (d) {
        if (!d.erfolg) { alert(d.fehler || 'Fehler'); return; }
        merkmalMassenZeigen(ids, d.merkmale);
    });
}

function merkmalMassenZeigen(ids, merkmale) {
    var alt = document.getElementById('mm-dlg');
    if (alt) alt.remove();
    var n = ids.length;
    var bd = document.createElement('div');
    bd.id = 'mm-dlg';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1600;display:flex;align-items:center;justify-content:center';
    var opts = merkmale.map(function (m, i) { return '<option value="' + i + '">' + m.name.replace(/</g, '&lt;') + '</option>'; }).join('');
    bd.innerHTML =
        '<div style="background:#fff;border-radius:8px;padding:20px;width:460px;max-height:86vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,.2)">'
        + '<div style="font-weight:700;font-size:14px;margin-bottom:4px;color:var(--color-nav)">Merkmal setzen</div>'
        + '<div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px">' + n + (n === 1 ? ' Artikel' : ' Artikel') + ' gewählt · gewählte Vater-Artikel nehmen alle Farben mit</div>'
        + '<select id="mm-merkmal" class="erp-select" style="width:100%;margin-bottom:8px">' + opts + '</select>'
        + '<input type="text" id="mm-suche" class="erp-input" style="width:100%;margin-bottom:6px" placeholder="Werte suchen…">'
        + '<div id="mm-werte" style="overflow-y:auto;flex:1;min-height:120px;max-height:40vh;border:1px solid var(--color-border);border-radius:4px;padding:4px 8px"></div>'
        + '<div id="mm-modi" style="display:flex;gap:14px;font-size:13px;margin-top:10px"></div>'
        + '<div id="mm-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>'
        + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">'
        + '<button type="button" class="btn btn-secondary btn-sm" id="mm-abbrechen">Abbrechen</button>'
        + '<button type="button" class="btn btn-primary btn-sm" id="mm-ok">Anwenden</button>'
        + '</div></div>';
    document.body.appendChild(bd);

    var sel = document.getElementById('mm-merkmal');
    // Pflege vorwählen, weil das der häufigste Fall ist
    merkmale.forEach(function (m, i) { if (m.slug === 'pflege') sel.value = String(i); });

    function zeichnen() {
        var m = merkmale[parseInt(sel.value, 10)];
        var typ = m.mehrfach ? 'checkbox' : 'radio';
        document.getElementById('mm-werte').innerHTML = m.werte.map(function (w) {
            return '<label class="mm-wert" data-text="' + w.wert.toLowerCase().replace(/"/g, '&quot;') + '" style="display:flex;gap:8px;align-items:center;padding:4px 0;font-size:13px;cursor:pointer">'
                + '<input type="' + typ + '" name="mm-wert" value="' + w.id + '"> ' + w.wert.replace(/</g, '&lt;') + '</label>';
        }).join('') || '<div style="font-size:12px;color:var(--color-text-muted);padding:6px 0">Dieses Merkmal hat noch keine Werte (Artikel → Merkmale).</div>';
        document.getElementById('mm-modi').innerHTML = (m.mehrfach
            ? '<label><input type="radio" name="mm-modus" value="hinzufuegen" checked> hinzufügen</label>'
              + '<label><input type="radio" name="mm-modus" value="ersetzen"> ersetzen</label>'
            : '<label><input type="radio" name="mm-modus" value="ersetzen" checked> setzen</label>')
            + '<label><input type="radio" name="mm-modus" value="entfernen"> entfernen</label>';
        document.getElementById('mm-suche').value = '';
        document.getElementById('mm-fehler').textContent = '';
    }
    sel.addEventListener('change', zeichnen);
    document.getElementById('mm-suche').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('#mm-werte .mm-wert').forEach(function (l) { l.style.display = !q || l.dataset.text.includes(q) ? 'flex' : 'none'; });
    });
    zeichnen();

    function zu() { bd.remove(); }
    bd.addEventListener('click', function (e) { if (e.target === bd) zu(); });
    document.getElementById('mm-abbrechen').addEventListener('click', zu);
    document.getElementById('mm-ok').addEventListener('click', function () {
        var m = merkmale[parseInt(sel.value, 10)];
        var werte = Array.from(document.querySelectorAll('input[name="mm-wert"]:checked')).map(function (i) { return parseInt(i.value, 10); });
        var modus = (document.querySelector('input[name="mm-modus"]:checked') || {}).value;
        var fehler = document.getElementById('mm-fehler');
        if (modus !== 'entfernen' && !werte.length) { fehler.textContent = 'Bitte mindestens einen Wert anhaken.'; return; }
        var text = modus === 'entfernen'
            ? (werte.length ? 'Die gewählten Werte von „' + m.name + '“' : 'Das Merkmal „' + m.name + '“ komplett') + ' bei ' + n + ' Artikel(n) entfernen?'
            : '„' + m.name + '“ bei ' + n + ' Artikel(n) ' + (modus === 'ersetzen' ? 'ersetzen' : 'ergänzen') + '?';
        if (!confirm(text)) return;
        this.disabled = true;
        var knopf = this;
        fetch(window.BASE_PATH + '/artikel/merkmal_massen.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids, merkmal_id: m.id, wert_ids: werte, modus: modus })
        }).then(r => r.json()).then(function (d) {
            if (!d.erfolg) { fehler.textContent = d.fehler || 'Fehler'; knopf.disabled = false; return; }
            zu();
            var t = '„' + m.name + '“ bei ' + d.artikel + ' Artikel(n) gesetzt' + (d.gesamt > d.artikel ? ' (mit Farben: ' + d.gesamt + ')' : '');
            if (typeof showFlash === 'function') showFlash(t, 'erfolg'); else alert(t);
        });
    });
}
