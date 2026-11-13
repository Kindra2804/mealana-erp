/**
 * Massenaktion "Eigentümer setzen" (Artikelliste) und Eigentümer-Auswahl im Konfigurator (Artikel-Detail).
 * Erwartet window.EIGENTUEMER_PARTNER = [{id, name, nr_kuerzel}] (nur Partner mit "verkauft online").
 * Endpoint: artikel/eigentuemer_setzen.php
 */
function eigentuemerDialog(ids, vorauswahl) {
    var alt = document.getElementById('eig-dlg');
    if (alt) alt.remove();
    var partner = window.EIGENTUEMER_PARTNER || [];
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    var opts = '<option value="">MEALANA KG (Hauptfirma)</option>' + partner.map(function (p) {
        return '<option value="' + p.id + '"' + (String(vorauswahl || '') === String(p.id) ? ' selected' : '') + '>'
            + esc(p.name) + (p.nr_kuerzel ? ' (' + esc(p.nr_kuerzel) + ')' : '') + '</option>';
    }).join('');
    var dlg = document.createElement('div');
    dlg.id = 'eig-dlg';
    dlg.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;display:flex;align-items:center;justify-content:center';
    dlg.innerHTML = '<div class="card" style="width:min(460px,92vw);padding:18px">'
        + '<h3 style="margin:0 0 6px">Eigentümer setzen</h3>'
        + '<p style="margin:0 0 10px;font-size:13px;color:var(--color-text-muted)">' + ids.length + ' Artikel ausgewählt. '
        + 'Kind-Artikel und fertige Schilder eines Layouts wechseln mit. Der Bestand wird ins Lager des neuen Eigentümers umgebucht.</p>'
        + '<label class="erp-label">Gehört</label>'
        + '<select id="eig-partner" class="erp-select" style="width:100%">' + opts + '</select>'
        + (partner.length ? '' : '<p style="font-size:12px;color:#b45309;margin:6px 0 0">Noch kein Partner mit „verkauft online“ (Partner → bearbeiten).</p>')
        + '<div id="eig-meldung" style="font-size:13px;margin-top:10px"></div>'
        + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">'
        + '<button type="button" class="btn btn-secondary btn-sm" id="eig-abbr">Abbrechen</button>'
        + '<button type="button" class="btn btn-primary btn-sm" id="eig-ok">Übernehmen</button></div></div>';
    document.body.appendChild(dlg);
    document.getElementById('eig-abbr').onclick = function () { dlg.remove(); };
    document.getElementById('eig-ok').onclick = function () {
        var knopf = this, meldung = document.getElementById('eig-meldung');
        knopf.disabled = true; meldung.textContent = 'Wird umgestellt …';
        fetch(window.BASE_PATH + '/artikel/eigentuemer_setzen.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids, partner_id: document.getElementById('eig-partner').value })
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d.erfolg) { meldung.style.color = 'var(--color-danger)'; meldung.textContent = (d.fehler || []).join(' '); knopf.disabled = false; return; }
            meldung.style.color = 'var(--color-success)';
            meldung.textContent = d.artikel + ' Artikel umgestellt, ' + Number(d.umgebucht).toLocaleString('de-AT') + ' Stk. umgebucht. '
                + (d.hinweise || []).join(' ');
            setTimeout(function () { location.reload(); }, 1500);
        }).catch(function () { meldung.textContent = 'Serverfehler'; knopf.disabled = false; });
    };
}
