// Packplatz Scan-Interface

const gescannt       = new Array(POSITIONEN.length).fill(0);
const chargenAuswahl = POSITIONEN.map(() => ({})); // {chargeName: menge} pro Positions-Idx

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

    const pos = POSITIONEN.find(p =>
        (p.ean && p.ean === ean) || p.artnr === ean
    );

    if (!pos) {
        zeigeUnbekannt(ean);
        return;
    }

    pruefeUndBuche(pos.idx, menge);
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
    pruefeUndBuche(idx, 1);
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

function verpackenStarten(lieferart) {
    if (lieferart === 'abholung') {
        document.getElementById('overlay-abholung').classList.add('aktiv');
        return;
    }
    // Fortschritt sofort sichern — fire-and-forget
    fetch('/mealana/packplatz/warenausgang/ajax_status_setzen.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({auftrag_id: AUFTRAG_ID, status: 'kommissioniert'}),
    }).catch(() => {});
    document.getElementById('overlay-verpacken').classList.add('aktiv');
    setTimeout(() => document.getElementById('overlay-tracking').focus(), 100);
}

function bauePosData() {
    return POSITIONEN.map((p, i) => {
        const entry = { idx: i, gesamt: p.gesamt, gescannt: gescannt[i] };
        const chargen = chargenAuswahl[i];
        if (Object.keys(chargen).length > 0) {
            entry.chargen = Object.entries(chargen).map(([charge, info]) => ({
                charge:          info.charge,
                menge:           info.menge,
                lagerbestand_id: info.lagerbestand_id ?? null,
                nachtragen:      info.nachtragen ?? false,
            }));
        }
        return entry;
    });
}

function abholungAbschliessen() {
    const posData = bauePosData();
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'abschliessen.php';
    const felder = {
        auftrag_id:           AUFTRAG_ID,
        pickliste_id:         PICKLISTE_ID ?? '',
        tracking:             '',
        versanddienstleister: '',
        gewicht:              '0',
        teillieferung:        '0',
        positionen_json:      JSON.stringify(posData),
    };
    for (const [k, v] of Object.entries(felder)) {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild(inp);
    }
    document.body.appendChild(form);
    form.submit();
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
    const abholOv = document.getElementById('overlay-abholung');
    if (abholOv) abholOv.classList.remove('aktiv');
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

    const posData = bauePosData();

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
    if (e.key === 'Escape') { overlaySchliessen(); chargeAbbrechen(); }
    if (e.target === document.body && !e.ctrlKey && !e.altKey) {
        scanField.focus();
    }
});

// ─── Charge-Auswahl Popup ───────────────────────────────────────────────────

let chargePopupIdx   = -1;
let chargePopupDaten = []; // Chargen aus AJAX: [{id, charge, bestand, charge_status}]
let chargeEingaben   = {}; // charge-key → {charge, menge, lagerbestand_id, nachtragen}

async function pruefeUndBuche(idx, menge) {
    const pos = POSITIONEN[idx];
    if (!pos.charge_pflicht && !pos.hat_chargen) {
        bucheMenge(idx, menge);
        return;
    }
    try {
        const resp = await fetch(`/mealana/packplatz/warenausgang/chargen_ajax.php?artikel_id=${pos.artikel_id}&lager_id=${LAGER_ID}`);
        const chargen = await resp.json();
        if (chargen.length === 0 && !pos.charge_pflicht) {
            bucheMenge(idx, menge);
            return;
        }
        zeigeChargePopup(idx, chargen);
    } catch {
        bucheMenge(idx, menge);
    }
}

function zeigeChargePopup(idx, chargen) {
    chargePopupIdx   = idx;
    chargePopupDaten = chargen;
    chargeEingaben   = {};

    const pos = POSITIONEN[idx];
    document.getElementById('charge-popup-titel').textContent = 'Charge auswählen — ' + pos.name;
    document.getElementById('charge-popup-benoetigt').textContent = pos.gesamt - gescannt[idx];

    const body = document.getElementById('charge-popup-body');
    body.innerHTML = '';

    if (chargen.length === 0) {
        body.innerHTML = '<p style="color:#aaa;font-size:13px">Keine Chargen im Lager vorhanden.</p>';
    } else {
        const tbl = document.createElement('table');
        tbl.style.cssText = 'width:100%;border-collapse:collapse';
        tbl.innerHTML = `<thead><tr style="color:#aaa;font-size:12px">
            <th style="text-align:left;padding:6px 8px">Charge</th>
            <th style="text-align:right;padding:6px 8px">Bestand</th>
            <th style="text-align:center;padding:6px 8px;min-width:180px">Menge</th>
        </tr></thead>`;
        const tbody = document.createElement('tbody');

        chargen.forEach((c, rowIdx) => {
            const isNachtragen = c.charge_status === 'nachzutragen';
            const key = `row_${rowIdx}`;

            const tr = document.createElement('tr');
            tr.style.borderTop = '1px solid #0f3460';

            const chargeLabel = isNachtragen
                ? `<input type="text" id="charge-name-${rowIdx}" class="pp-overlay-input"
                     style="width:140px;padding:4px 8px;font-size:13px" placeholder="Chargennummer eingeben">`
                : `<span style="font-family:monospace;font-size:13px">${c.charge}</span>`;

            tr.innerHTML = `
                <td style="padding:8px">${chargeLabel}</td>
                <td style="text-align:right;padding:8px;color:#aaa">${parseFloat(c.bestand).toFixed(0)}</td>
                <td style="text-align:center;padding:8px">
                    <div style="display:flex;align-items:center;gap:6px;justify-content:center">
                        <button type="button" onclick="chargeAendern('${key}',${c.id},${isNachtragen},-1)"
                            style="width:32px;height:32px;background:#e94560;border:none;border-radius:6px;color:#fff;font-size:18px;cursor:pointer;line-height:1">−</button>
                        <input type="number" id="charge-menge-${rowIdx}" value="0" min="0" max="${parseFloat(c.bestand)}"
                            style="width:64px;text-align:center;background:#0f3460;border:1px solid #1a4a8a;color:#fff;padding:4px;border-radius:6px;font-size:14px"
                            oninput="chargeUpdate('${key}',${c.id},${isNachtragen},this.value,${rowIdx})">
                        <button type="button" onclick="chargeAendern('${key}',${c.id},${isNachtragen},1)"
                            style="width:32px;height:32px;background:#00b4d8;border:none;border-radius:6px;color:#fff;font-size:18px;cursor:pointer;line-height:1">+</button>
                    </div>
                </td>`;
            tbody.appendChild(tr);
        });
        tbl.appendChild(tbody);
        body.appendChild(tbl);
    }

    document.getElementById('overlay-charge').classList.add('aktiv');
    chargeUpdateGesamt();
    setTimeout(() => {
        const firstInput = body.querySelector('input[type="text"]') || body.querySelector('input[type="number"]');
        if (firstInput) firstInput.focus();
    }, 100);
}

function chargeKey(id) { return 'lb_' + id; }

function chargeAendern(key, lbId, isNachtragen, delta) {
    const rowIdx = parseInt(key.replace('row_', ''));
    const input  = document.getElementById('charge-menge-' + rowIdx);
    if (!input) return;
    const max = parseFloat(input.max) || 999;
    const neu = Math.max(0, Math.min(max, (parseFloat(input.value) || 0) + delta));
    input.value = neu;
    chargeUpdate(key, lbId, isNachtragen, neu, rowIdx);
}

function chargeUpdate(key, lbId, isNachtragen, menge, rowIdx) {
    menge = parseFloat(menge) || 0;
    if (menge <= 0) {
        delete chargeEingaben[key];
    } else {
        let chargeName;
        if (isNachtragen) {
            const nameInput = document.getElementById('charge-name-' + rowIdx);
            chargeName = nameInput ? nameInput.value.trim() : '';
        } else {
            const c = chargePopupDaten[rowIdx];
            chargeName = c ? c.charge : '';
        }
        chargeEingaben[key] = { charge: chargeName, menge, lagerbestand_id: lbId, nachtragen: isNachtragen };
    }
    chargeUpdateGesamt();
}

function chargeUpdateGesamt() {
    const total = Object.values(chargeEingaben).reduce((s, e) => s + e.menge, 0);
    document.getElementById('charge-popup-gewaehlt').textContent = total;
    document.getElementById('btn-charge-ok').disabled = total <= 0;
}

function chargeBestaetigen() {
    if (chargePopupIdx < 0) return;

    // Validierung: alle nachtragen-Einträge müssen Chargennamen haben
    for (const [, entry] of Object.entries(chargeEingaben)) {
        if (entry.nachtragen && !entry.charge) {
            alert('Bitte Chargennummer für alle "nachzutragen"-Zeilen eingeben.');
            return;
        }
    }

    // Chargen in die Auswahl übernehmen (aufaddieren wenn gleiche Charge mehrfach)
    for (const [, entry] of Object.entries(chargeEingaben)) {
        const ck = entry.charge;
        if (chargenAuswahl[chargePopupIdx][ck]) {
            chargenAuswahl[chargePopupIdx][ck].menge += entry.menge;
        } else {
            chargenAuswahl[chargePopupIdx][ck] = { ...entry };
        }
    }

    // gescannt[] aus chargenAuswahl berechnen
    const total = Object.values(chargenAuswahl[chargePopupIdx]).reduce((s, e) => s + e.menge, 0);
    bucheMenge(chargePopupIdx, total - gescannt[chargePopupIdx]);

    chargeAbbrechen();
}

function chargeAbbrechen() {
    document.getElementById('overlay-charge').classList.remove('aktiv');
    chargePopupIdx   = -1;
    chargePopupDaten = [];
    chargeEingaben   = {};
    scanField.focus();
}
