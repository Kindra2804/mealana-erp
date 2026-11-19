/**
 * JTL-Kunden- und Archiv-Import (kunden/jtl_import.php):
 * Datei in 4-MB-Stücken hochladen, Probelauf/Import in Abschnitten mit Fortschritt, Prüfliste offener Aufträge.
 */
(function () {
    'use strict';
    const URL_SEITE = window.BASE_PATH + '/kunden/jtl_import.php';
    const STUECK = 4 * 1024 * 1024;
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const post = body => fetch(URL_SEITE, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(r => r.json()).then(d => { if (!d.erfolg) throw new Error(d.fehler || 'Fehler'); return d; });

    // ── Hochladen ────────────────────────────────────────────────────────
    document.querySelectorAll('.ji-datei').forEach(function (inp) {
        const knopf = document.querySelector('.ji-hochladen[data-art="' + inp.dataset.art + '"]');
        inp.addEventListener('change', () => { knopf.disabled = !inp.files.length; });
        knopf.addEventListener('click', async function () {
            const datei = inp.files[0];
            const info = document.querySelector('.ji-upload-info[data-art="' + inp.dataset.art + '"]');
            if (!datei) return;
            knopf.disabled = inp.disabled = true;
            let id = 0;
            try {
                id = (await post({ aktion: 'neu', art: inp.dataset.art, dateiname: datei.name })).id;
                for (let von = 0; von < datei.size; von += STUECK) {
                    info.textContent = 'Hochladen … ' + Math.round(von / datei.size * 100) + ' %';
                    const r = await fetch(URL_SEITE + '?aktion=teil&id=' + id, { method: 'POST', body: datei.slice(von, von + STUECK),
                        headers: { 'Content-Type': 'application/octet-stream' } }).then(r => r.json());
                    if (!r.erfolg) throw new Error(r.fehler || 'Fehler beim Hochladen');
                }
                info.textContent = 'Prüfe die Datei …';
                await post({ aktion: 'hochgeladen', id: id });
                location.reload();
            } catch (e) {
                info.innerHTML = '<span style="color:#9B2C2C">✗ ' + esc(e.message) + '</span>';
                knopf.disabled = inp.disabled = false;
            }
        });
    });

    // ── Probelauf / Import in Abschnitten ────────────────────────────────
    document.querySelectorAll('.ji-lauf').forEach(function (knopf) {
        knopf.addEventListener('click', async function () {
            const tr = knopf.closest('tr');
            const probelauf = knopf.dataset.probelauf === '1';
            if (!probelauf && !confirm('Import jetzt starten? Vorher eine Datenbank-Sicherung machen!')) return;
            tr.querySelectorAll('button').forEach(b => b.disabled = true);
            const box = tr.querySelector('.ji-fortschritt'), balken = tr.querySelector('.ji-balken'), text = tr.querySelector('.ji-text');
            box.style.display = 'block';
            let neu = knopf.dataset.neu === '1';
            try {
                while (true) {
                    const d = await post({ aktion: 'abschnitt', id: tr.dataset.id, probelauf: probelauf, neu_start: neu });
                    neu = false;
                    const pz = Math.round(d.position / Math.max(d.groesse, 1) * 100);
                    balken.style.width = pz + '%';
                    const z = d.zahlen;
                    text.textContent = (probelauf ? 'Probelauf' : 'Import') + ' ' + pz + ' % · ' + (z.neu || 0) + ' neu, ' + (z.uebersprungen || 0) + ' schon da'
                        + (z.verknuepft ? ', ' + z.verknuepft + ' verknüpft' : '') + (z.fehler ? ', ' + z.fehler + ' Fehler' : '');
                    if (d.fertig) break;
                }
                location.reload();
            } catch (e) {
                text.innerHTML = '<span style="color:#9B2C2C">✗ ' + esc(e.message) + ' – Seite neu laden und „fortsetzen“, Vorhandenes wird nicht doppelt angelegt.</span>';
            }
        });
    });

    document.querySelectorAll('.ji-verwerfen').forEach(function (knopf) {
        knopf.addEventListener('click', function () {
            if (!confirm('Diesen Lauf samt hochgeladener Datei verwerfen?')) return;
            post({ aktion: 'verwerfen', id: knopf.closest('tr').dataset.id }).then(() => location.reload()).catch(e => alert(e.message));
        });
    });

    // ── Prüfliste offener Aufträge ───────────────────────────────────────
    const liste = document.getElementById('ji-offene');
    if (!liste) return;
    const cbs = () => [...liste.querySelectorAll('.ji-offen-cb')];
    const zaehlen = function () {
        const offen = cbs().filter(c => !c.checked).length;
        document.getElementById('ji-offen-zaehler').textContent = (cbs().length - offen) + ' erledigt · ' + offen + ' bleiben offen';
    };
    liste.addEventListener('change', zaehlen);
    document.getElementById('ji-alle').addEventListener('click', () => { cbs().forEach(c => c.checked = true); zaehlen(); });
    document.getElementById('ji-keine').addEventListener('click', () => { cbs().forEach(c => c.checked = false); zaehlen(); });
    document.getElementById('ji-offene-speichern').addEventListener('click', function () {
        const offen = cbs().filter(c => !c.checked).length;
        if (!confirm((cbs().length - offen) + ' Aufträge auf bezahlt setzen, ' + offen + ' bleiben offen. Speichern?')) return;
        this.disabled = true;
        post({ aktion: 'offene', alle: cbs().map(c => c.value), erledigt: cbs().filter(c => c.checked).map(c => c.value) })
            .then(() => location.reload()).catch(e => { alert(e.message); this.disabled = false; });
    });
    zaehlen();
})();
