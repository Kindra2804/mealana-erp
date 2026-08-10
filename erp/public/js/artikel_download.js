/**
 * Download-Tab: Drag & Drop Upload (eine Datei pro Artikel, ersetzt eine
 * vorhandene) + Löschen. Bewusst einfacher als bilder.js, da es hier immer
 * nur genau einen Datei-Slot gibt (kein Grid/Reihenfolge/Hauptbild nötig).
 */
(function () {
    const dropzone  = document.getElementById('download-dropzone');
    const fileInput = document.getElementById('download-datei-input');
    const info      = document.getElementById('download-datei-info');
    const status    = document.getElementById('download-upload-status');

    if (!dropzone) return;

    const artikelId = window.MEALANA_ARTIKEL_ID;

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
        if (e.dataTransfer.files.length > 0) ladeHoch(e.dataTransfer.files[0]);
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) ladeHoch(fileInput.files[0]);
    });

    function resetDropzone() {
        dropzone.style.background  = '#eff6ff';
        dropzone.style.borderColor = '#93c5fd';
    }

    async function ladeHoch(file) {
        status.textContent = 'Lade hoch…';
        const form = new FormData();
        form.append('datei', file);
        form.append('artikel_id', artikelId);

        try {
            const res  = await fetch(window.BASE_PATH + '/artikel/download_upload.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data.erfolg) {
                zeigeDatei(data.dateiname, data.url);
                status.textContent = 'Hochgeladen';
            } else {
                status.textContent = 'Fehler: ' + data.fehler;
            }
        } catch {
            status.textContent = 'Netzwerkfehler';
        }

        fileInput.value = '';
        setTimeout(() => { status.textContent = ''; }, 3000);
    }

    function zeigeDatei(dateiname, url) {
        info.innerHTML = '';
        info.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 0';

        const icon = document.createElement('span');
        icon.style.fontSize = '20px';
        icon.textContent = '📄';

        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.textContent = dateiname;

        const loeschenBtn = document.createElement('button');
        loeschenBtn.type = 'button';
        loeschenBtn.className = 'btn btn-danger btn-sm';
        loeschenBtn.id = 'download-datei-loeschen';
        loeschenBtn.textContent = 'Löschen';

        info.append(icon, link, loeschenBtn);
    }

    // Event Delegation, da der Löschen-Button nach dem Upload neu erzeugt wird.
    info.addEventListener('click', async (e) => {
        if (!e.target.closest('#download-datei-loeschen')) return;
        if (!confirm('Download-Datei wirklich löschen?')) return;

        const form = new FormData();
        form.append('artikel_id', artikelId);
        const res  = await fetch(window.BASE_PATH + '/artikel/download_loeschen.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.erfolg) {
            info.style.cssText = 'color:#94a3b8;font-size:13px;padding:8px 0';
            info.textContent   = 'Noch keine Datei hochgeladen.';
        }
    });
})();
