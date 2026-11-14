/**
 * Mengen-Aktionen (aktionen/mengen.php): Formular-Helfer
 * - Felder je nach Art ein-/ausblenden (Paketpreis bei "X für Y €", "davon bezahlt" bei "Kauf X, zahl Y")
 * - Erklärung + Badge-Vorschlag live
 * - Kategorien-Suche
 * - Erfolgsmeldung nach 3 s ausblenden
 * Außerdem: mengenAktionDialog(ids) für die Massenaktion in der Artikelliste (artikel/liste.php)
 */
(function () {
    var ok = document.getElementById('ma-ok');
    if (ok) setTimeout(function () { ok.style.display = 'none'; }, 3000);

    var art = document.getElementById('ma-art');
    if (!art) return;
    var $ = function (id) { return document.getElementById(id); };
    var zahl = function (s) { var n = parseFloat(String(s || '').replace(',', '.')); return isNaN(n) ? null : n; };
    var badgeVonHand = $('ma-badge').value.trim() !== '';
    $('ma-badge').addEventListener('input', function () { badgeVonHand = this.value.trim() !== ''; });

    function aktualisieren() {
        var kauf = art.value === 'kauf_x_zahl_y', paket = art.value === 'paketpreise';
        document.querySelectorAll('.ma-nur-x').forEach(function (e) { e.style.display = art.value === 'x_fuer_y' ? '' : 'none'; });
        document.querySelectorAll('.ma-nur-kauf').forEach(function (e) { e.style.display = kauf ? '' : 'none'; });
        document.querySelectorAll('.ma-nur-paket').forEach(function (e) { e.style.display = paket ? '' : 'none'; });
        document.querySelectorAll('.ma-nicht-paket').forEach(function (e) { e.style.display = paket ? 'none' : ''; });
        if (paket) {
            // Stückpreis je Paketzeile anzeigen, Badge-Vorschlag ab kleinster Größe
            var kleinste = 0;
            document.querySelectorAll('.ma-pk-menge').forEach(function (inp, i) {
                var m = parseInt(inp.value, 10), pr = zahl(document.querySelectorAll('.ma-pk-preis')[i].value);
                document.querySelectorAll('.ma-pk-stueck')[i].textContent = m >= 2 && pr > 0 ? 'je Stück ' + (pr / m).toFixed(2).replace('.', ',') + ' €' : '';
                if (m >= 2 && pr > 0 && (!kleinste || m < kleinste)) kleinste = m;
            });
            $('ma-erklaerung').textContent = '';
            if (!badgeVonHand) $('ma-badge').placeholder = kleinste ? 'Mengenpreis ab ' + kleinste + ' Stk' : 'automatisch';
            return;
        }
        var x = parseInt($('ma-menge').value, 10) || 0, y = parseInt($('ma-zahl').value, 10) || 0, p = zahl($('ma-preis').value);
        var badge = '', text = '';
        if (kauf && x >= 2 && y >= 1 && y < x) {
            badge = x + ' für ' + y;
            text = 'Je ' + x + ' Stück zahlt man ' + y + ' – das günstigste' + (x - y > 1 ? 'n ' + (x - y) + ' Stück sind' : ' Stück ist') + ' gratis (Rabatt verteilt auf alle ' + x + ').';
        } else if (!kauf && x >= 2 && p > 0) {
            var pt = p % 1 === 0 ? String(p) : p.toFixed(2).replace('.', ',');
            badge = x + ' für ' + pt + ' €';
            text = 'Je ' + x + ' Stück zusammen ' + p.toFixed(2).replace('.', ',') + ' € (teuerste Stücke zuerst ins Paket).';
        }
        $('ma-erklaerung').textContent = text;
        if (!badgeVonHand) $('ma-badge').placeholder = badge || 'automatisch';
    }
    ['ma-art', 'ma-menge', 'ma-zahl', 'ma-preis'].forEach(function (id) { $(id).addEventListener('input', aktualisieren); });
    document.querySelectorAll('.ma-pk-menge, .ma-pk-preis').forEach(function (e) { e.addEventListener('input', aktualisieren); });
    art.addEventListener('change', aktualisieren);
    aktualisieren();

    $('ma-katsuche').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.ma-kat').forEach(function (l) {
            l.style.display = !q || l.dataset.text.includes(q) || l.querySelector('input').checked ? 'flex' : 'none';
        });
    });
})();

/** Massenaktion Artikelliste: gewählte Artikel einer Mengen-Aktion zuordnen */
function mengenAktionDialog(ids) {
    ids = (ids || []).map(Number).filter(Boolean);
    if (!ids.length) { alert('Bitte Artikel auswählen'); return; }
    fetch(window.BASE_PATH + '/aktionen/mengen_ajax.php').then(function (r) { return r.json(); }).then(function (d) {
        if (!d.erfolg) { alert(d.fehler || 'Fehler'); return; }
        if (!d.aktionen.length) {
            if (confirm('Es gibt noch keine Mengen-Aktion. Jetzt eine anlegen?')) window.open(window.BASE_PATH + '/aktionen/mengen.php?neu=1', '_blank');
            return;
        }
        var alt = document.getElementById('ma-dlg'); if (alt) alt.remove();
        var bd = document.createElement('div');
        bd.id = 'ma-dlg';
        bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1600;display:flex;align-items:center;justify-content:center';
        bd.innerHTML = '<div style="background:#fff;border-radius:8px;padding:20px;width:420px;box-shadow:0 8px 32px rgba(0,0,0,.2)">'
            + '<div style="font-weight:700;font-size:14px;margin-bottom:4px;color:var(--color-nav)">Zu Mengen-Aktion hinzufügen</div>'
            + '<div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px">' + ids.length + ' Artikel gewählt · Väter nehmen ihre Kinder mit</div>'
            + '<select id="ma-dlg-aktion" class="erp-select" style="width:100%">' + d.aktionen.map(function (a) {
                return '<option value="' + a.id + '">' + String(a.name).replace(/</g, '&lt;') + ' (' + String(a.badge_text).replace(/</g, '&lt;') + ', ' + a.status + ')</option>';
            }).join('') + '</select>'
            + '<div id="ma-dlg-fehler" style="color:var(--color-danger);font-size:12px;min-height:16px;margin-top:6px"></div>'
            + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:8px">'
            + '<button type="button" class="btn btn-secondary btn-sm" id="ma-dlg-ab">Abbrechen</button>'
            + '<button type="button" class="btn btn-primary btn-sm" id="ma-dlg-ok">Hinzufügen</button></div></div>';
        document.body.appendChild(bd);
        var zu = function () { bd.remove(); };
        bd.addEventListener('click', function (e) { if (e.target === bd) zu(); });
        document.getElementById('ma-dlg-ab').addEventListener('click', zu);
        document.getElementById('ma-dlg-ok').addEventListener('click', function () {
            var knopf = this; knopf.disabled = true;
            var aktionId = parseInt(document.getElementById('ma-dlg-aktion').value, 10);
            fetch(window.BASE_PATH + '/aktionen/mengen_ajax.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ aktion_id: aktionId, ids: ids })
            }).then(function (r) { return r.json(); }).then(function (r) {
                if (!r.erfolg) { document.getElementById('ma-dlg-fehler').textContent = r.fehler || 'Fehler'; knopf.disabled = false; return; }
                zu();
                var t = r.neu + ' Artikel zugeordnet' + (r.neu < ids.length ? ' (' + (ids.length - r.neu) + ' waren schon dabei)' : '');
                if (typeof showFlash === 'function') showFlash(t, 'erfolg'); else alert(t);
            });
        });
    });
}
