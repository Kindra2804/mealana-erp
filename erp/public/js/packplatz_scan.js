// Packplatz Scan-Interface

const gescannt = new Array(POSITIONEN.length).fill(0);

const scanField    = document.getElementById('scan-field');
const vorwahlField = document.getElementById('vorwahl');
const btnVerpacken = document.getElementById('btn-verpacken');
const bildBox      = document.getElementById('scan-bild-box');

// Scan-Feld: Enter / Barcode-Scanner
scanField.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        verarbeiteEan(scanField.value.trim());
        scanField.value = '';
        scanField.focus();
    }
});

// Automatischer Submit wenn Barcode-Scanner ohne Enter (manche Scanner senden kein Enter)
// → nach 80ms ohne weiteren Input auswerten
let scanTimer = null;
scanField.addEventListener('input', () => {
    clearTimeout(scanTimer);
    scanTimer = setTimeout(() => {
        const val = scanField.value.trim();
        if (val.length >= 8) {
            verarbeiteEan(val);
            scanField.value = '';
            scanField.focus();
        }
    }, 120);
});

function verarbeiteEan(ean) {
    if (!ean) return;
    const menge = parseInt(vorwahlField.value) || 1;

    // Suche Position anhand EAN oder ArtNr
    const pos = POSITIONEN.find(p =>
        (p.ean && p.ean === ean) || p.artnr === ean
    );

    if (!pos) {
        zeigeUnbekannt(ean);
        return;
    }

    bucheMenge(pos.idx, menge);
}

function bucheMenge(idx, menge) {
    const pos = POSITIONEN[idx];
    const neuerWert = gescannt[idx] + menge;

    gescannt[idx] = neuerWert;

    const row      = document.getElementById('pos-row-' + idx);
    const zelle    = document.getElementById('gescannt-' + idx);
    zelle.textContent = neuerWert;

    if (neuerWert >= pos.gesamt) {
        row.className = 'pp-ok';
        if (neuerWert > pos.gesamt) {
            row.className = 'pp-zuviel';
        }
    } else {
        row.className = 'pp-aktiv';
    }

    // Artikelbild anzeigen
    if (pos.bild) {
        bildBox.innerHTML = `<img src="/mealana/${pos.bild}" class="pp-scan-bild" alt="">`;
    } else {
        bildBox.innerHTML = `<div class="pp-scan-bild-placeholder">📷</div>`;
    }

    pruefeFertig();
}

function manuellesPlus(idx) {
    bucheMenge(idx, 1);
    scanField.focus();
}

function zeigeUnbekannt(ean) {
    bildBox.innerHTML = `<div class="pp-scan-bild-placeholder" style="color:#e94560;font-size:12px">Unbekannt</div>`;
    scanField.style.borderColor = '#e94560';
    setTimeout(() => { scanField.style.borderColor = ''; }, 800);
}

function pruefeFertig() {
    const alleFertig = POSITIONEN.every((p, i) => gescannt[i] >= p.gesamt);
    const keineZuviel = POSITIONEN.every((p, i) => gescannt[i] <= p.gesamt);
    btnVerpacken.disabled = !(alleFertig && keineZuviel);
}

// ─── Verpacken-Overlay ──────────────────────────────────────────────────────

function verpackenStarten() {
    document.getElementById('overlay-verpacken').classList.add('aktiv');
    setTimeout(() => document.getElementById('overlay-tracking').focus(), 100);
}

const trackingInput = document.getElementById('overlay-tracking');
const btnTrackingOk = document.getElementById('btn-tracking-ok');

trackingInput.addEventListener('input', () => {
    btnTrackingOk.disabled = trackingInput.value.trim().length < 3;
});
trackingInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !btnTrackingOk.disabled) verpackenAbschliessen(false);
});

// Teillieferung-Overlay
const tlTrackingInput = document.getElementById('overlay-tl-tracking');
const btnTlOk         = document.getElementById('btn-tl-ok');
tlTrackingInput.addEventListener('input', () => {
    btnTlOk.disabled = tlTrackingInput.value.trim().length < 3;
});
tlTrackingInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !btnTlOk.disabled) verpackenAbschliessen(true);
});

function teillieferung() {
    document.getElementById('overlay-teillieferung').classList.add('aktiv');
    setTimeout(() => document.getElementById('overlay-tl-tracking').focus(), 100);
}

function overlaySchliessen() {
    document.getElementById('overlay-verpacken').classList.remove('aktiv');
    document.getElementById('overlay-teillieferung').classList.remove('aktiv');
    scanField.focus();
}

function verpackenAbschliessen(istTeillieferung) {
    const tracking = istTeillieferung
        ? document.getElementById('overlay-tl-tracking').value.trim()
        : document.getElementById('overlay-tracking').value.trim();
    const gewicht = istTeillieferung
        ? document.getElementById('overlay-tl-gewicht').value
        : document.getElementById('overlay-gewicht').value;
    const dienstleister = istTeillieferung
        ? (document.getElementById('overlay-tl-dl')?.value || 'post_at')
        : (document.getElementById('overlay-dl')?.value || 'post_at');

    if (!tracking) return;

    // Gescannte Mengen übermitteln
    const posData = POSITIONEN.map((p, i) => ({
        idx: i, gesamt: p.gesamt, gescannt: gescannt[i]
    }));

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'abschliessen.php';

    const felder = {
        auftrag_id:          AUFTRAG_ID,
        pickliste_id:        PICKLISTE_ID ?? '',
        tracking:            tracking,
        versanddienstleister: dienstleister,
        gewicht:             gewicht,
        teillieferung:       istTeillieferung ? '1' : '0',
        positionen_json:     JSON.stringify(posData),
    };

    for (const [k, v] of Object.entries(felder)) {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = k;
        inp.value = v;
        form.appendChild(inp);
    }

    document.body.appendChild(form);
    form.submit();
}

// Autofokus auf Scan-Feld wenn Overlay geschlossen wird
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') overlaySchliessen();
    // Jede Eingabe die nicht in einem Input landet → zum Scan-Feld
    if (e.target === document.body && !e.ctrlKey && !e.altKey) {
        scanField.focus();
    }
});
