/**
 * Bilder-Tab: Drag & Drop Upload, Reihenfolge, Hauptbild, Alt-Text, Löschen
 * Events via Delegation auf #bild-grid — kein per-Karte Binding nötig.
 */
(function () {
    const dropzone  = document.getElementById('bild-dropzone');
    const fileInput = document.getElementById('bild-datei-input');
    const grid      = document.getElementById('bild-grid');
    const status    = document.getElementById('bild-upload-status');
    const anzahl    = document.getElementById('bild-anzahl');

    if (!dropzone) return;

    const artikelId = grid.dataset.artikelId;

    // ── Event Delegation: alle Klicks auf Grid ───────────────────────────────

    grid.addEventListener('click', async (e) => {
        const karte  = e.target.closest('.bild-karte');
        if (!karte) return;
        const bildId = karte.dataset.bildId;

        if (e.target.closest('.btn-hauptbild')) {
            await ajax('hauptbild', { bild_id: bildId });
            grid.insertBefore(karte, grid.firstChild);
            aktualisiereAlleKarten();

        } else if (e.target.closest('.btn-pos-hoch')) {
            const vorherige = karte.previousElementSibling;
            if (vorherige && vorherige !== grid.firstChild) {
                await ajax('position', { bild_id: bildId, richtung: 'hoch' });
                grid.insertBefore(karte, vorherige);
                aktualisiereAlleKarten();
            }

        } else if (e.target.closest('.btn-pos-runter')) {
            const naechste = karte.nextElementSibling;
            if (naechste) {
                await ajax('position', { bild_id: bildId, richtung: 'runter' });
                grid.insertBefore(naechste, karte);
                aktualisiereAlleKarten();
            }

        } else if (e.target.closest('.btn-bild-loeschen')) {
            if (!confirm('Bild wirklich löschen?')) return;
            const form = new FormData();
            form.append('bild_id', bildId);
            form.append('artikel_id', artikelId);
            const res  = await fetch('/mealana/artikel/bild_loeschen.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data.erfolg) {
                karte.remove();
                aktualisiereAlleKarten();
            }
        }
    });

    // blur bubbled nicht — capture-Phase für Alt-Text Delegation
    grid.addEventListener('blur', async (e) => {
        if (!e.target.classList.contains('bild-alt-text')) return;
        const karte = e.target.closest('.bild-karte');
        if (!karte) return;
        await ajax('alt_text', { bild_id: karte.dataset.bildId, alt_text: e.target.value });
    }, true);

    // ── Alle Karten komplett neu rendern ─────────────────────────────────────

    function aktualisiereAlleKarten() {
        const karten = grid.querySelectorAll('.bild-karte');
        const gesamt = karten.length;

        karten.forEach((k, i) => {
            const istHaupt   = i === 0;
            const kannHoch   = i > 1;
            const kannRunter = i > 0 && i < gesamt - 1;

            // Overlay: Badge ↔ ☆-Button
            const overlay = k.querySelector('.bild-img-overlay');
            overlay.querySelector('span')?.remove();
            overlay.querySelector('.btn-hauptbild')?.remove();
            overlay.appendChild(istHaupt ? erstelleBadge() : erstelleHauptbildBtn());

            // Steuerbereich komplett neu aufbauen
            const steuer = k.querySelector('.bild-steuer');
            steuer.innerHTML = '';

            if (istHaupt) {
                const hinweis = document.createElement('span');
                hinweis.style.cssText = 'font-size:10px;color:#94a3b8;font-style:italic;flex:1';
                hinweis.textContent   = 'nicht verschiebbar';
                steuer.appendChild(hinweis);
            } else {
                if (kannHoch)   steuer.appendChild(erstellePfeilBtn('hoch'));
                if (kannRunter) steuer.appendChild(erstellePfeilBtn('runter'));
                const sp = document.createElement('span');
                sp.style.flex = '1';
                steuer.appendChild(sp);
            }
            steuer.appendChild(erstelleLoeschenBtn());
        });

        aktualisiereAnzahl();
    }

    // ── Upload ───────────────────────────────────────────────────────────────

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.background  = '#dbeafe';
        dropzone.style.borderColor = '#3b82f6';
    });

    dropzone.addEventListener('dragleave', () => resetDropzone());

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        resetDropzone();
        ladeHoch(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', () => ladeHoch(fileInput.files));

    function resetDropzone() {
        dropzone.style.background  = '#eff6ff';
        dropzone.style.borderColor = '#93c5fd';
    }

    async function ladeHoch(files) {
        if (!files || files.length === 0) return;
        const gesamt = files.length;
        let fertig = 0;

        for (const file of files) {
            status.textContent = `Lade hoch ${fertig + 1} von ${gesamt}…`;
            const form = new FormData();
            form.append('bild', file);
            form.append('artikel_id', artikelId);

            try {
                const res  = await fetch('/mealana/artikel/bild_upload.php', { method: 'POST', body: form });
                const data = await res.json();
                if (data.erfolg) {
                    fuegeKarteEin(data.bild_id, data.url);
                    fertig++;
                } else {
                    zeigeUploadFehler(file.name, data.fehler);
                }
            } catch {
                zeigeUploadFehler(file.name, 'Netzwerkfehler');
            }
        }

        status.textContent = fertig === gesamt
            ? `${fertig} Bild${fertig !== 1 ? 'er' : ''} hochgeladen`
            : `${fertig} von ${gesamt} erfolgreich`;

        fileInput.value = '';
        setTimeout(() => { status.textContent = ''; }, 3000);
    }

    function zeigeUploadFehler(dateiname, meldung) {
        const div = document.createElement('div');
        div.style.cssText = 'color:#ef4444;font-size:12px;margin-top:4px';
        div.textContent   = `${dateiname}: ${meldung}`;
        status.appendChild(div);
        setTimeout(() => div.remove(), 5000);
    }

    // ── Karte erstellen + einbauen ────────────────────────────────────────────

    function fuegeKarteEin(bildId, url) {
        document.getElementById('bild-leer-hinweis')?.remove();
        grid.appendChild(erstelleKarte(bildId, url));
        aktualisiereAlleKarten();
    }

    function erstelleKarte(bildId, url) {
        const div = document.createElement('div');
        div.className = 'bild-karte';
        div.dataset.bildId    = bildId;
        div.dataset.artikelId = artikelId;
        div.style.cssText = 'width:200px;border:1px solid #e2e8f0;border-radius:6px;' +
                            'background:white;box-shadow:0 1px 4px #0000000f;overflow:hidden;flex-shrink:0';
        div.innerHTML = `
            <div class="bild-img-overlay" style="position:relative">
                <img src="${url}" alt=""
                     style="width:100%;height:140px;object-fit:cover;display:block">
            </div>
            <div style="padding:8px 8px 0">
                <div class="bild-steuer"
                     style="display:flex;gap:6px;margin-bottom:8px;align-items:center">
                </div>
                <input type="text" class="erp-input bild-alt-text"
                       placeholder="Alt-Text (SEO)..."
                       style="width:100%;font-size:11px;margin-bottom:8px;box-sizing:border-box">
            </div>`;
        return div;
    }

    // ── Element-Helfer ────────────────────────────────────────────────────────

    function erstelleBadge() {
        const s = document.createElement('span');
        s.style.cssText = 'position:absolute;top:8px;left:8px;background:#f59e0b;color:white;' +
                          'font-size:10px;font-weight:600;padding:3px 8px;border-radius:3px;pointer-events:none';
        s.textContent = '★ Hauptbild';
        return s;
    }

    function erstelleHauptbildBtn() {
        const b = document.createElement('button');
        b.className   = 'btn-hauptbild';
        b.style.cssText = 'position:absolute;top:8px;left:8px;background:rgba(255,255,255,.9);' +
                          'border:1px solid #e2e8f0;color:#64748b;font-size:10px;' +
                          'padding:3px 7px;border-radius:3px;cursor:pointer;white-space:nowrap';
        b.title       = 'Als Hauptbild setzen';
        b.textContent = '☆ Hauptbild';
        return b;
    }

    function erstellePfeilBtn(richtung) {
        const b = document.createElement('button');
        b.className   = `btn-pos-${richtung}`;
        b.style.cssText = 'padding:3px 8px;border:1px solid #e2e8f0;border-radius:4px;' +
                          'background:#f8fafc;cursor:pointer;font-size:13px';
        b.title       = richtung === 'hoch' ? 'Weiter vorne' : 'Weiter hinten';
        b.textContent = richtung === 'hoch' ? '↑' : '↓';
        return b;
    }

    function erstelleLoeschenBtn() {
        const b = document.createElement('button');
        b.className   = 'btn-bild-loeschen';
        b.style.cssText = 'padding:3px 8px;border:1px solid #fca5a5;border-radius:4px;' +
                          'background:#fef2f2;color:#ef4444;cursor:pointer;font-size:13px';
        b.title       = 'Bild löschen';
        b.textContent = '✕';
        return b;
    }

    function aktualisiereAnzahl() {
        const n = grid.querySelectorAll('.bild-karte').length;
        if (anzahl) anzahl.textContent = `${n} ${n === 1 ? 'Bild' : 'Bilder'}`;
    }

    // ── AJAX-Helfer ───────────────────────────────────────────────────────────

    async function ajax(aktion, params) {
        const form = new FormData();
        form.append('aktion', aktion);
        form.append('artikel_id', artikelId);
        for (const [k, v] of Object.entries(params)) form.append(k, v);
        const res = await fetch('/mealana/artikel/bild_ajax.php', { method: 'POST', body: form });
        return res.json();
    }

})();
