/**
 * Kuvert-Etikett drucken (konfigurator/kuvert_etikett.php): kleines Fenster "oben/unten" + Schnittlinie,
 * danach öffnet das PDF in einem neuen Tab. Verwendet in Artikelliste (Massenaktion), Artikel-Detail
 * und "Fertiges Schild". Aufruf: kuvertEtikettDialog([artikelId, ...])
 * Die Schnittlinien-Wahl wird im Browser gemerkt.
 */
function kuvertEtikettDialog(ids) {
    ids = (ids || []).map(String).filter(Boolean);
    if (!ids.length) { alert('Bitte Artikel auswählen'); return; }

    var alt = document.getElementById('kuvert-dlg');
    if (alt) alt.remove();

    var schnitt = '1';
    try { schnitt = localStorage.getItem('kuvert_schnittlinie') || '1'; } catch (e) {}

    var n = ids.length;
    var bd = document.createElement('div');
    bd.id = 'kuvert-dlg';
    bd.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1600;display:flex;align-items:center;justify-content:center';
    bd.innerHTML =
        '<div style="background:#fff;border-radius:8px;padding:20px;width:380px;box-shadow:0 8px 32px rgba(0,0,0,.2)">'
        + '<div style="font-weight:700;font-size:14px;margin-bottom:4px;color:var(--color-nav)">🖨 Kuvert-Etikett drucken</div>'
        + '<div style="font-size:12px;color:var(--color-text-muted);margin-bottom:12px">' + n + (n === 1 ? ' Etikett' : ' Etiketten') + ' · 2 pro A4-Blatt</div>'
        + '<div style="font-size:13px;margin-bottom:6px">Wo beginnen?</div>'
        + '<label style="display:block;font-size:13px;margin-bottom:4px"><input type="radio" name="kuvert-start" value="oben" checked> Oben (neues Blatt)</label>'
        + '<label style="display:block;font-size:13px;margin-bottom:12px"><input type="radio" name="kuvert-start" value="unten"> Unten (obere Hälfte ist schon weg)</label>'
        + '<label style="display:block;font-size:13px"><input type="checkbox" id="kuvert-schnitt"' + (schnitt === '1' ? ' checked' : '') + '> Schnittlinie drucken</label>'
        + '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">'
        + '<button type="button" class="btn btn-secondary btn-sm" id="kuvert-abbrechen">Abbrechen</button>'
        + '<button type="button" class="btn btn-primary btn-sm" id="kuvert-drucken">Drucken</button>'
        + '</div></div>';
    document.body.appendChild(bd);

    function zu() { bd.remove(); }
    bd.addEventListener('click', function (e) { if (e.target === bd) zu(); });
    document.getElementById('kuvert-abbrechen').addEventListener('click', zu);
    document.getElementById('kuvert-drucken').addEventListener('click', function () {
        var start = bd.querySelector('input[name="kuvert-start"]:checked').value;
        var s = document.getElementById('kuvert-schnitt').checked ? '1' : '0';
        try { localStorage.setItem('kuvert_schnittlinie', s); } catch (e) {}
        window.open(window.BASE_PATH + '/konfigurator/kuvert_etikett.php?ids=' + encodeURIComponent(ids.join(','))
            + '&start=' + start + '&schnitt=' + s, '_blank');
        zu();
    });
}
