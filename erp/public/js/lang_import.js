/**
 * Lang-Yarns-Import (artikel/lang_import.php): Import in Abschnitten zu je 20 Vätern,
 * damit auch tausende Artikel ohne Zeitüberschreitung durchlaufen. Fortschritt + Fehlerliste.
 */
(function () {
    'use strict';
    const start = document.getElementById('li-start');
    if (!start) return;
    const balken = document.getElementById('li-balken');
    const text   = document.getElementById('li-text');
    const box    = document.getElementById('li-fortschritt');
    const ergebnisBox = document.getElementById('li-ergebnis');
    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function abschnitt(von) {
        return fetch(window.BASE_PATH + '/artikel/lang_import.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ aktion: 'ausfuehren', datei: window.LI_DATEI, lieferant_id: window.LI_LIEFERANT, von: von })
        }).then(r => r.json());
    }

    start.addEventListener('click', async function () {
        if (!confirm('Import jetzt starten? Vorher eine Datenbank-Sicherung machen!')) return;
        start.disabled = true;
        box.style.display = 'block';
        const summe = {}; const fehler = [];
        let von = 0, gesamt = 1;
        try {
            while (von < gesamt) {
                const d = await abschnitt(von);
                if (!d.erfolg) throw new Error(d.fehler || 'Fehler');
                gesamt = d.gesamt;
                von = d.bis;
                Object.entries(d.ergebnis).forEach(([k, v]) => summe[k] = (summe[k] || 0) + v);
                fehler.push(...d.fehler);
                balken.style.width = Math.round(von / Math.max(gesamt, 1) * 100) + '%';
                text.textContent = `${von} von ${gesamt} Vätern verarbeitet …`;
                if (gesamt === 0) break;
            }
            text.textContent = 'Fertig.';
        } catch (e) {
            text.innerHTML = `<span style="color:#9B2C2C">✗ ${esc(e.message)} – abgebrochen nach ${von} von ${gesamt}. Erneut starten ist gefahrlos (Vorhandenes wird nicht doppelt angelegt).</span>`;
            start.disabled = false;
        }
        ergebnisBox.innerHTML = `<div class="card" style="padding:var(--space-md);margin-top:var(--space-sm);font-size:13px">
            <strong>Ergebnis:</strong> ${summe.vaeter_neu || 0} Väter neu, ${summe.kinder_neu || 0} Farben neu, ${summe.einzel_neu || 0} Bücher neu,
            ${summe.aktualisiert || 0} aktualisiert, ${summe.auslauf || 0} auf Auslauf, ${summe.uebersprungen || 0} übersprungen.
            ${fehler.length ? `<div style="color:#9B2C2C;margin-top:8px"><strong>${fehler.length} Fehler</strong> (dieser Vater wurde komplett ausgelassen):<br>${fehler.map(esc).join('<br>')}</div>` : ''}
            <div style="margin-top:8px"><a href="liste.php?q=LY-" class="btn btn-secondary btn-sm">Zur Artikelliste</a></div>
        </div>`;
    });
})();
