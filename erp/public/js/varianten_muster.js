/**
 * Massenaktion "Varianten-Muster übernehmen" (artikel/varianten_muster.php)
 * Master suchen → Vorschau je Ziel (neu / vorhanden / Konflikt, Bestand-Auswahl) → Übernehmen,
 * ein Ziel nach dem anderen (Fortschritt sichtbar, kein Timeout bei vielen Artikeln).
 */
(function () {
    'use strict';
    const URL_SEITE = window.BASE_PATH + '/artikel/varianten_muster.php';
    let masterId = window.VM_MASTER_ID || null;
    let vorschau = null;

    const suche   = document.getElementById('vm-master-suche');
    const treffer = document.getElementById('vm-master-treffer');
    const ergebnis = document.getElementById('vm-ergebnis');
    const aktionen = document.getElementById('vm-aktionen');
    if (!suche) return;

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function post(daten) {
        return fetch(URL_SEITE, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten)
        }).then(r => r.json());
    }

    // ── Master-Suche ──────────────────────────────────────────────
    let timer = null;
    suche.addEventListener('input', function () {
        masterId = null;
        clearTimeout(timer);
        const q = suche.value.trim();
        if (q.length < 2) { treffer.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(window.BASE_PATH + '/artikel/artikel_vater_suche.php?q=' + encodeURIComponent(q))
                .then(r => r.json()).then(function (liste) {
                    treffer.innerHTML = liste.map(a =>
                        `<div class="vm-treffer" data-id="${a.id}" data-text="${esc(a.artikelnummer + ' – ' + a.name)}"
                              style="padding:6px 8px;cursor:pointer;font-size:13px;border-bottom:1px solid #eee">
                            <strong>${esc(a.artikelnummer)}</strong> ${esc(a.name)}</div>`).join('')
                        || '<div style="padding:6px 8px;font-size:13px;color:#888">Nichts gefunden</div>';
                    treffer.style.display = 'block';
                });
        }, 250);
    });
    treffer.addEventListener('click', function (e) {
        const t = e.target.closest('.vm-treffer');
        if (!t) return;
        masterId = parseInt(t.dataset.id, 10);
        suche.value = t.dataset.text;
        treffer.style.display = 'none';
    });
    document.addEventListener('click', e => { if (!e.target.closest('#vm-master-treffer') && e.target !== suche) treffer.style.display = 'none'; });

    // ── Vorschau ──────────────────────────────────────────────────
    document.getElementById('vm-vorschau').addEventListener('click', function () {
        if (!masterId) { alert('Bitte zuerst einen Master-Artikel aus der Liste wählen.'); return; }
        ergebnis.innerHTML = '<div class="card" style="padding:var(--space-md)">Vorschau wird berechnet …</div>';
        aktionen.style.display = 'none';
        post({ aktion: 'vorschau', master_id: masterId, ids: window.VM_IDS }).then(function (d) {
            if (!d.erfolg) { ergebnis.innerHTML = `<div class="error-banner">✗ ${esc(d.fehler)}</div>`; return; }
            vorschau = d;
            zeichneVorschau(d);
        }).catch(() => { ergebnis.innerHTML = '<div class="error-banner">✗ Netzwerkfehler</div>'; });
    });

    const CHIP = {
        neu:       '<span class="chip" style="background:#BEE3F8;color:#2B6CB0">● neu</span>',
        vorhanden: '<span class="chip chip-aktiv">✓ schon da</span>',
        konflikt:  '<span class="chip" style="background:#FED7D7;color:#9B2C2C">Nummer belegt – übersprungen</span>'
    };

    function zeichneVorschau(d) {
        let neuGesamt = 0, zieleMitNeu = 0;
        let html = `<div style="font-size:13px;margin-bottom:8px">Master: <strong>${esc(d.master.artikelnummer)}</strong> ${esc(d.master.name)} · ${d.master.kinder} Varianten</div>`;
        if (!d.ziele.length) html += '<div class="card" style="padding:var(--space-md)">Keine Ziel-Artikel (der Master selbst wird übersprungen).</div>';

        d.ziele.forEach(function (z) {
            const neu = z.zeilen.filter(r => r.status === 'neu').length;
            if (!z.fehler && (neu || z.achsen_neu.length || z.werte_neu.length)) { neuGesamt += neu; zieleMitNeu++; }
            html += `<div class="card vm-ziel" data-id="${z.ziel.id}" style="padding:var(--space-md);margin-bottom:var(--space-sm)">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap">
                    <div><strong>${esc(z.ziel.artikelnummer)}</strong> ${esc(z.ziel.name)}</div>
                    <div class="vm-status" style="font-size:13px">${z.fehler ? '' : (neu ? `<strong>${neu} neu</strong>` : 'nichts zu tun')}</div>
                </div>`;
            if (z.fehler) {
                html += `<div style="color:#9B2C2C;font-size:13px;margin-top:6px">✗ ${esc(z.fehler)} — wird übersprungen</div></div>`;
                return;
            }
            if (z.achsen_neu.length || z.werte_neu.length) {
                html += `<div style="font-size:12px;color:var(--color-text-muted);margin-top:4px">Ergänzt: `
                     + [...z.achsen_neu.map(a => 'Achse ' + esc(a)), ...z.werte_neu.map(esc)].join(' · ') + '</div>';
            }
            html += `<table class="erp-table" style="margin-top:6px"><thead><tr><th>Variante</th><th>Artikelnummer</th><th>Bezeichnung</th><th>Status</th></tr></thead><tbody>`;
            z.zeilen.forEach(function (r) {
                html += `<tr${r.status === 'neu' ? ' style="background:#EBF8FF"' : ''}>
                    <td style="font-size:12px">${esc(r.werte)}</td>
                    <td>${esc(r.vorhanden_nr || r.nr)}</td>
                    <td style="font-size:12px">${esc(r.name)}</td>
                    <td>${CHIP[r.status]}</td></tr>`;
            });
            html += '</tbody></table>';
            if (z.bestand_text && neu) {
                html += `<div style="font-size:13px;padding:8px;margin-top:8px;border-left:4px solid #3182CE;background:#EBF8FF">
                    <strong style="color:#2B6CB0">!</strong> Hat eigenen Bestand (${esc(z.bestand_text)}). Welche Variante übernimmt ihn?
                    <select class="erp-select vm-bestand" style="margin-left:8px"><option value="">— bitte wählen —</option>`
                    + Object.entries(z.bestand_optionen).map(([k, t]) => `<option value="${esc(k)}">${esc(t)}</option>`).join('')
                    + '</select></div>';
            }
            html += '</div>';
        });
        ergebnis.innerHTML = html;
        document.getElementById('vm-zusammenfassung').textContent =
            zieleMitNeu ? `${neuGesamt} neue Varianten bei ${zieleMitNeu} Artikeln` : 'Nichts zu übernehmen';
        document.getElementById('vm-uebernehmen').disabled = !zieleMitNeu;
        aktionen.style.display = 'flex';
    }

    // ── Übernehmen (ein Ziel nach dem anderen) ────────────────────
    document.getElementById('vm-uebernehmen').addEventListener('click', async function () {
        if (!vorschau) return;
        const karten = [...document.querySelectorAll('.vm-ziel')];
        for (const k of karten) {
            const sel = k.querySelector('.vm-bestand');
            if (sel && !sel.value) { alert('Bitte bei allen Artikeln mit eigenem Bestand wählen, welche Variante ihn übernimmt.'); sel.focus(); return; }
        }
        if (!confirm('Varianten jetzt anlegen?')) return;
        this.disabled = true;
        let ok = 0, fehler = 0;
        for (const z of vorschau.ziele) {
            const neu = z.zeilen.filter(r => r.status === 'neu').length;
            if (z.fehler || !(neu || z.achsen_neu.length || z.werte_neu.length)) continue;
            const karte = document.querySelector(`.vm-ziel[data-id="${z.ziel.id}"]`);
            const status = karte.querySelector('.vm-status');
            const sel = karte.querySelector('.vm-bestand');
            status.textContent = '… läuft';
            try {
                const d = await post({ aktion: 'uebernehmen', master_id: masterId, ziel_id: z.ziel.id, bestand: sel ? sel.value : '' });
                if (d.erfolg) {
                    ok++;
                    status.innerHTML = `<span class="chip chip-aktiv">✓ ${d.neu} angelegt</span>`
                        + (d.bestand ? ` <span style="font-size:12px">Bestand → ${esc(d.bestand.kind_nr)}</span>` : '');
                } else {
                    fehler++;
                    status.innerHTML = `<span style="color:#9B2C2C">✗ ${esc(d.fehler)}</span>`;
                }
            } catch (e) {
                fehler++;
                status.innerHTML = '<span style="color:#9B2C2C">✗ Netzwerkfehler</span>';
            }
        }
        document.getElementById('vm-zusammenfassung').textContent =
            `Fertig: ${ok} Artikel ergänzt` + (fehler ? `, ${fehler} mit Fehler` : '');
    });
})();
