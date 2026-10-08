function mrNeuBerechnen(input) {
    const row = input.closest('tr');
    const mengeRaus = parseFloat(row.dataset.mengeRaus) || 0;
    const rueck   = parseFloat(row.querySelector('.mr-rueck').value) || 0;
    const schwund = parseFloat(row.querySelector('.mr-schwund').value) || 0;
    const verkauft = mengeRaus - rueck - schwund;
    row.querySelector('.mr-verkauft-zelle').textContent = verkauft;
    row.querySelector('.mr-verkauft-zelle').style.color = verkauft < 0 ? '#dc2626' : '';
}

function mrAbschliessen(syncId, vonLagerId, nachLagerId) {
    const rueckgabe = [];
    const schwund   = [];

    document.querySelectorAll('#mr-tabelle tr').forEach(row => {
        const artikelId = parseInt(row.dataset.artikelId, 10);
        const charge    = row.dataset.charge || null;
        const rueck     = parseFloat(row.querySelector('.mr-rueck').value) || 0;
        const schw      = parseFloat(row.querySelector('.mr-schwund').value) || 0;
        if (rueck > 0)  rueckgabe.push({ artikel_id: artikelId, charge: charge, menge_rueck: rueck });
        if (schw > 0)   schwund.push({ artikel_id: artikelId, charge: charge, menge: schw });
    });

    const fb = document.getElementById('mr-feedback');
    fb.innerHTML = '<div class="ks-feedback info">Wird verarbeitet…</div>';

    const fd = new FormData();
    fd.append('aktion', 'rueckkehr');
    fd.append('sync_id', syncId);
    fd.append('von_lager_id', vonLagerId);
    fd.append('nach_lager_id', nachLagerId);
    fd.append('rueckgabe', JSON.stringify(rueckgabe));
    fd.append('schwund', JSON.stringify(schwund));

    fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) {
                fb.innerHTML = '<div class="ks-feedback fehler">Fehler: ' + (d.fehler || 'unbekannt') + '</div>';
                return;
            }
            fb.innerHTML = '<div class="ks-feedback ok">Rückkehr verbucht — Restbestand zurückgebucht.</div>';
            setTimeout(() => { window.location = 'messe_rueckkehr.php'; }, 1200);
        })
        .catch(() => {
            fb.innerHTML = '<div class="ks-feedback fehler">Netzwerkfehler.</div>';
        });
}

// ── Papier-Messe ──────────────────────────────────────────────────────────────
// Schwund = mit − verkauft (Stricherl) − zurück. Nur Anzeige — der Server rechnet
// selbst nach und lehnt negative Werte (Zählfehler) ab.
function mrPapierBerechnen(input) {
    const row = input.closest('tr');
    const mit     = parseFloat(row.dataset.mengeRaus) || 0;
    const strich  = parseFloat(row.querySelector('.mr-strich').value) || 0;
    const rueck   = parseFloat(row.querySelector('.mr-rueck').value) || 0;
    const schwund = Math.round((mit - strich - rueck) * 1000) / 1000;
    const zelle = row.querySelector('.mr-schwund-zelle');
    zelle.textContent = schwund;
    zelle.style.color = schwund < 0 ? '#dc2626' : (schwund > 0 ? '#d97706' : '');
}

function mrFreitextZeile() {
    const tr = document.createElement('tr');
    const optionen = '<option value="">—</option>' + (window.MR_GRUPPEN || []).map(g =>
        '<option value="' + g.id + '">' + escHtmlMr(g.name) + '</option>').join('');
    tr.innerHTML =
        '<td><input type="text" class="ks-input ft-bez" maxlength="300" style="width:100%;padding:4px 6px"></td>' +
        '<td><select class="ks-select ft-gruppe" style="padding:4px 6px">' + optionen + '</select></td>' +
        '<td style="text-align:right"><input type="number" min="1" step="1" value="1" class="ks-input ft-menge" style="width:70px;text-align:right;padding:4px 6px"></td>' +
        '<td style="text-align:right"><input type="text" inputmode="decimal" class="ks-input ft-preis" placeholder="0,00" style="width:90px;text-align:right;padding:4px 6px"></td>' +
        '<td style="text-align:center"><input type="checkbox" class="ft-zugabe" onchange="mrZugabeGeaendert(this)"></td>' +
        '<td><button type="button" class="ks-btn ks-btn-secondary" style="padding:3px 8px" onclick="this.closest(\'tr\').remove()">✕</button></td>';
    document.getElementById('mr-freitext').appendChild(tr);
    tr.querySelector('.ft-bez').focus();
}

// Zugabe = Werbung, immer 0 €
function mrZugabeGeaendert(cb) {
    const preis = cb.closest('tr').querySelector('.ft-preis');
    preis.disabled = cb.checked;
    if (cb.checked) preis.value = '0';
}

function mrPapierAbschliessen(syncId, vonLagerId, nachLagerId) {
    const rueckgabe = [], strich = [], freitext = [];
    let zaehlfehler = false;

    document.querySelectorAll('#mr-tabelle tr').forEach(row => {
        const artikelId = parseInt(row.dataset.artikelId, 10);
        const charge    = row.dataset.charge || null;
        const mit       = parseFloat(row.dataset.mengeRaus) || 0;
        const s         = parseFloat(row.querySelector('.mr-strich').value) || 0;
        const r         = parseFloat(row.querySelector('.mr-rueck').value) || 0;
        if (s + r > mit + 0.0005) zaehlfehler = true;
        if (r > 0) rueckgabe.push({ artikel_id: artikelId, charge: charge, menge_rueck: r });
        if (s > 0) strich.push({ artikel_id: artikelId, charge: charge, menge: s });
    });

    document.querySelectorAll('#mr-freitext tr').forEach(row => {
        const bez = row.querySelector('.ft-bez').value.trim();
        if (!bez) return;
        freitext.push({
            bezeichnung:       bez,
            artikel_gruppe_id: row.querySelector('.ft-gruppe').value || null,
            menge:             parseInt(row.querySelector('.ft-menge').value, 10) || 1,
            einzelpreis:       parseFloat(row.querySelector('.ft-preis').value.replace(',', '.')) || 0,
            zugabe:            row.querySelector('.ft-zugabe').checked,
        });
    });

    const fb = document.getElementById('mr-feedback');
    if (zaehlfehler) {
        fb.innerHTML = '<div class="ks-feedback fehler">Zählfehler: bei den rot markierten Zeilen ist verkauft + zurück mehr als mitgenommen.</div>';
        return;
    }
    if (!confirm('Lager jetzt zurückbuchen? Das kann nicht wiederholt werden.')) return;

    const btn = document.getElementById('mr-papier-btn');
    btn.disabled = true;
    fb.innerHTML = '<div class="ks-feedback info">Wird verarbeitet…</div>';

    const fd = new FormData();
    fd.append('aktion', 'rueckkehr');
    fd.append('sync_id', syncId);
    fd.append('von_lager_id', vonLagerId);
    fd.append('nach_lager_id', nachLagerId);
    fd.append('rueckgabe', JSON.stringify(rueckgabe));
    fd.append('strich', JSON.stringify(strich));
    fd.append('freitext', JSON.stringify(freitext));

    fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) {
                fb.innerHTML = '<div class="ks-feedback fehler">Fehler: ' + escHtmlMr(d.fehler || 'unbekannt') + '</div>';
                btn.disabled = false;
                return;
            }
            fb.innerHTML = '<div class="ks-feedback ok">Lager zurückgebucht — weiter zu den Belegen …</div>';
            setTimeout(() => { window.location = 'messe_belege.php?sync_id=' + syncId; }, 1000);
        })
        .catch(() => {
            fb.innerHTML = '<div class="ks-feedback fehler">Netzwerkfehler.</div>';
            btn.disabled = false;
        });
}

function escHtmlMr(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}
