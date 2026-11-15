// Mobil-Messe: offene Belege nacheinander an dieser Kasse nacherfassen (messe_mobil_belege.php).
// Nacheinander, nicht parallel: RKSV-Belegnummern laufen fortlaufend, und beim ersten Fehler
// (z.B. BFR nicht erreichbar) wird angehalten — der Rest bleibt offen und kann später nachgeholt werden.
async function mbAlleNacherfassen() {
    const btn = document.getElementById('mb-alle');
    const status = document.getElementById('mb-status');
    const zeilen = Array.from(document.querySelectorAll('tr[data-offen="1"]'));
    if (!zeilen.length) return;
    btn.disabled = true;
    let ok = 0;
    for (const tr of zeilen) {
        status.textContent = 'Erfasse ' + (ok + 1) + ' von ' + zeilen.length + ' …';
        const fd = new FormData();
        fd.append('aktion', 'mobil_nacherfassen');
        fd.append('beleg_id', tr.dataset.belegId);
        let d;
        try {
            const r = await fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd });
            d = await r.json();
        } catch (e) {
            d = { erfolg: false, fehler: 'Netzwerkfehler.' };
        }
        if (!d.erfolg) {
            status.innerHTML = '<span style="color:#dc2626">Angehalten: ' + (d.fehler || 'unbekannter Fehler')
                + '</span> — ' + ok + ' erfasst, der Rest bleibt offen.';
            btn.disabled = false;
            return;
        }
        ok++;
        tr.dataset.offen = '0';
        tr.querySelector('.mb-bon').innerHTML = '<span style="color:#16a34a">✓ ' + (d.bon_nr || 'Bon') + '</span>';
        document.getElementById('mb-offen').textContent = String(zeilen.length - ok);
    }
    status.innerHTML = '<span style="color:#16a34a">Fertig — ' + ok + ' Beleg(e) nacherfasst.</span>';
    btn.style.display = 'none';
}
