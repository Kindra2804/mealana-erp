// Aktionspreis-Modal
function aktpreisModalSchliessen() {
    document.getElementById('aktpreis-backdrop').style.display = 'none';
}

async function aktpreisSpeichern() {
    const inputs    = document.querySelectorAll('.aktpreis-input');
    const byAktionKg = {};
    inputs.forEach(inp => {
        const key = inp.dataset.aktionId + ':' + inp.dataset.kgId;
        if (!byAktionKg[key]) {
            byAktionKg[key] = { aktion_id: +inp.dataset.aktionId, kg_id: +inp.dataset.kgId, preise: [] };
        }
        const brutto = parseFloat(inp.value.replace(',', '.')) || 0;
        if (brutto > 0) {
            byAktionKg[key].preise.push({
                artikel_id:   +inp.dataset.artikelId,
                sub_achse_id: inp.dataset.subAchseId || '',
                brutto_vk:    brutto,
                netto_vk:     +(brutto / (1 + +inp.dataset.mwst / 100)).toFixed(4),
                mwst_satz:    +inp.dataset.mwst
            });
        }
    });
    const info = document.getElementById('aktpreis-info');
    info.textContent = 'Speichern…';
    let gesamt = 0;
    for (const entry of Object.values(byAktionKg)) {
        if (entry.preise.length === 0) continue;
        const r = await fetch('/mealana/aktionen/aktion_preise_speichern.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(entry)
        });
        const d = await r.json();
        if (d.erfolg) gesamt += d.gespeichert;
    }
    info.textContent = gesamt + ' Preis(e) gespeichert';
    setTimeout(() => aktpreisModalSchliessen(), 1200);
}

// Flash-Banner
function showFlash(text, typ) {
    const el = document.getElementById('ajax-flash');
    el.className  = typ === 'fehler' ? 'error-banner' : 'success-banner';
    el.textContent = (typ === 'fehler' ? '✗ ' : '✓ ') + text;
    el.style.display = 'block';
    clearTimeout(el._t);
    el._t = setTimeout(function () { el.style.display = 'none'; }, 4000);
}

// Tabs
const TAB_KEY = 'artikel_tab_' + window.MEALANA_ARTIKEL_ID;

function zeigeTab(name, el) {
    document.querySelectorAll('[id^="tab-"]').forEach(d => d.classList.add('versteckt'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.remove('versteckt');
    el.classList.add('active');
    localStorage.setItem(TAB_KEY, name);
}

(function () {
    const urlParams = new URLSearchParams(location.search);
    const urlTab    = urlParams.get('tab');
    const saved     = urlTab || localStorage.getItem(TAB_KEY);
    if (saved) {
        const el = document.querySelector(`.tab[onclick*="'${saved}'"]`);
        if (el) zeigeTab(saved, el);
    }
    if (urlTab) {
        urlParams.delete('tab');
        const clean = location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
        history.replaceState(null, '', clean);
    }
})();

// WE-Modal
function weModalOeffnen()    { document.getElementById('we-backdrop').style.display = 'flex'; }
function weModalSchliessen() { document.getElementById('we-backdrop').style.display = 'none'; }

// UVP
function uvpBearbeiten() {
    document.getElementById('uvp-anzeige').style.display = 'none';
    document.querySelector('[onclick="uvpBearbeiten()"]').style.display = 'none';
    document.getElementById('uvp-edit').style.display = 'flex';
    document.getElementById('uvp-input').focus();
}
function uvpAbbrechen() {
    document.getElementById('uvp-edit').style.display = 'none';
    document.getElementById('uvp-anzeige').style.display = '';
    document.querySelector('[onclick="uvpBearbeiten()"]').style.display = '';
}
function uvpSpeichern() {
    const wert = document.getElementById('uvp-input').value;
    const data = new FormData();
    data.append('artikel_id', window.MEALANA_ARTIKEL_ID);
    data.append('uvp', wert);
    fetch('uvp_speichern.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else {
                showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
            }
        });
}

function togglePreisSektion(bodyId, header) {
    const body   = document.getElementById(bodyId);
    const toggle = header.querySelector('span');
    const offen  = body.style.display !== 'none';
    body.style.display  = offen ? 'none' : '';
    toggle.textContent  = offen ? '▼' : '▲';
}

// Preis-Modal
function preisModalOeffnen(kgId) {
    const row = document.querySelector(`tr[data-kg-id="${kgId}"]`);
    document.getElementById('preis-kg-id').value       = kgId;
    document.getElementById('preis-kg-name').textContent = row.querySelector('td').textContent.trim();
    document.getElementById('preis-brutto').value       = row.dataset.brutto || '';
    document.getElementById('preis-netto').value        = row.dataset.netto  || '';
    document.getElementById('preis-ab').value           = row.dataset.ab  ? row.dataset.ab.substring(0, 10)  : '';
    document.getElementById('preis-bis').value          = row.dataset.bis ? row.dataset.bis.substring(0, 10) : '';
    document.getElementById('preis-backdrop').style.display = 'flex';
}
function preisModalSchliessen() { document.getElementById('preis-backdrop').style.display = 'none'; }

function preisNettoBerechnen() {
    const brutto = parseFloat(document.getElementById('preis-brutto').value);
    if (!isNaN(brutto) && brutto > 0) {
        document.getElementById('preis-netto').value = (brutto / (1 + window.MEALANA_MWST_SATZ / 100)).toFixed(4);
    }
}

function preisLoeschen(kgId) {
    if (!confirm('Preis für diese Kundengruppe wirklich löschen?')) return;
    const data = new FormData();
    data.append('artikel_id', window.MEALANA_ARTIKEL_ID);
    data.append('kundengruppen_id', kgId);
    fetch('preis_loeschen.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else {
                showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
            }
        });
}

function preisSpeichern() {
    const form = document.getElementById('preis-form');
    const data = new FormData(form);
    fetch('preis_speichern.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                preisModalSchliessen();
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else {
                showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler');
            }
        });
}

function toggleChargen(btn, lagerId) {
    const row  = document.getElementById('chargen-' + lagerId);
    const offen = btn.textContent.includes('▲');
    row.style.display = offen ? 'none' : '';
    btn.textContent   = (offen ? '▼' : '▲') + ' Chargen (' + btn.dataset.count + ')';
}

// Kategorie-Modal (im Artikel-Detail, nicht Shell)
function katModalSchliessen() { document.getElementById('kat-backdrop').style.display = 'none'; }

function katModalOeffnen() {
    const kategorienArray = [...document.querySelectorAll('input[name="kategorien[]"]')].map(i => i.value);
    document.querySelectorAll('#kat-checkboxen input[type="checkbox"]').forEach(cb => {
        cb.checked = kategorienArray.includes(cb.value);
    });
    document.getElementById('kat-backdrop').style.display = 'flex';
}

function katUebernehmen() {
    const angehakt = [...document.querySelectorAll('#kat-checkboxen input[type="checkbox"]:checked')];
    document.querySelectorAll('input[name="kategorien[]"]').forEach(el => el.remove());
    const chips = document.getElementById('kat-chips');
    chips.innerHTML = '';
    angehakt.forEach(cb => {
        const input   = document.createElement('input');
        input.type    = 'hidden';
        input.name    = 'kategorien[]';
        input.value   = cb.value;
        chips.appendChild(input);
        const span    = document.createElement('span');
        span.className = 'chip chip-aktiv';
        span.textContent = cb.dataset.name;
        chips.appendChild(span);
    });
    document.getElementById('unsaved-banner').style.display = 'inline-flex';
    katModalSchliessen();
}

async function katAnlegen() {
    const katName  = document.getElementById('neue-kat-name').value?.trim();
    const parentId = document.getElementById('neue-kat-parent').value || '';
    if (!katName) return;
    const body     = 'name=' + encodeURIComponent(katName) + (parentId ? '&parent_id=' + encodeURIComponent(parentId) : '');
    const response = await fetch('kategorie_neu.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
    });
    const data = await response.json();
    if (!data.erfolg) { showFlash(data.fehler || 'Fehler', 'fehler'); return; }
    const tiefe = parentId ? 1 : 0;
    const pl    = tiefe * 20;
    const linie = tiefe > 0 ? '<span class="kat-linie">└─</span>' : '';
    const label = document.createElement('label');
    label.className      = 'kat-zeile';
    label.dataset.tiefe  = tiefe;
    label.style.paddingLeft = pl + 'px';
    label.innerHTML = linie +
        '<input type="checkbox" value="' + data.id + '"' +
        ' data-name="' + data.name.replace(/"/g, '&quot;') + '"' +
        ' data-parent-id="' + (parentId || 0) + '" checked>' +
        '<span class="kat-label' + (tiefe === 0 ? ' kat-wurzel' : '') + '">' + data.name + '</span>';
    document.getElementById('kat-checkboxen').appendChild(label);
    const opt = document.createElement('option');
    opt.value       = data.id;
    opt.textContent = data.name;
    document.getElementById('neue-kat-parent').appendChild(opt);
    document.getElementById('neue-kat-name').value = '';
}

// Lieferant-Modal
function liefModalOeffnen(alId) {
    document.querySelector('#lief-modal div').textContent = alId ? 'Lieferant bearbeiten:' : 'Lieferant hinzufügen:';
    document.getElementById('lief-al-id').value = alId ?? '';
    if (alId) {
        const tr = document.querySelector(`tr[data-al-id="${alId}"]`);
        document.getElementById('lief-lieferant-id').value = tr.dataset.lieferantId;
        document.getElementById('lief-artnr').value        = tr.dataset.artnr;
        document.getElementById('lief-ek').value           = tr.dataset.ek;
        document.getElementById('lief-brutto-ek').value    = tr.dataset.bruttoEk;
        document.getElementById('lief-waehrung').value     = tr.dataset.waehrung;
        document.getElementById('lief-vpe').value          = tr.dataset.vpe;
        document.getElementById('lief-vpe-ean').value      = tr.dataset.vpeEan;
        document.getElementById('lief-lz').value           = tr.dataset.lz;
        document.getElementById('lief-mba').value          = tr.dataset.mba;
        document.getElementById('lief-standard').checked   = tr.dataset.standard;
    } else {
        ['lief-lieferant-id','lief-artnr','lief-ek','lief-brutto-ek','lief-waehrung',
         'lief-vpe','lief-vpe-ean','lief-lz','lief-mba'].forEach(id => {
            document.getElementById(id).value = '';
        });
        document.getElementById('lief-standard').checked = false;
    }
    document.getElementById('lief-backdrop').style.display = 'flex';
}
function liefModalSchliessen() { document.getElementById('lief-backdrop').style.display = 'none'; }

function liefCalcBrutto() {
    var netto = parseFloat(document.getElementById('lief-ek').value);
    document.getElementById('lief-brutto-ek').value = (!isNaN(netto) && netto > 0)
        ? (netto * (1 + window.MEALANA_MWST_SATZ / 100)).toFixed(4) : '';
}
function liefCalcNetto() {
    var brutto = parseFloat(document.getElementById('lief-brutto-ek').value);
    document.getElementById('lief-ek').value = (!isNaN(brutto) && brutto > 0)
        ? (brutto / (1 + window.MEALANA_MWST_SATZ / 100)).toFixed(4) : '';
}

async function liefSpeichern() {
    const form = document.querySelector('#lief-modal form');
    const body = new URLSearchParams(new FormData(form)).toString();
    const response = await fetch('artikel_lieferant_speichern.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
    });
    const data = await response.json();
    if (!data.erfolg) { showFlash(data.fehler || 'Fehler', 'fehler'); return; }
    liefModalSchliessen();
    location.reload();
}

// Varianten-Generator Checkbox-Count
(function () {
    var genForm = document.getElementById('generator-form');
    if (!genForm) return;
    genForm.querySelectorAll('input[type=checkbox][name*="selected"]').forEach(cb => {
        cb.addEventListener('change', () => {
            const checked = genForm.querySelectorAll('input[type=checkbox][name*="selected"]:checked').length;
            const btn = document.getElementById('gen-submit-btn');
            if (btn) btn.textContent = '▶ Ausgewählte generieren (' + checked + ')';
        });
    });
})();

// Staffel-Modal
function staffelModalOeffnen(spId) {
    document.getElementById('staffel-id').value = spId ?? '';
    if (spId) {
        const row = document.querySelector(`tr[data-sp-id="${spId}"]`);
        document.getElementById('staffel-titel').textContent = 'Staffelpreis bearbeiten';
        document.getElementById('staffel-kg').value          = row.dataset.kgId;
        document.getElementById('staffel-menge').value       = row.dataset.menge;
        document.getElementById('staffel-brutto').value      = row.dataset.brutto;
        document.getElementById('staffel-netto').value       = row.dataset.netto;
    } else {
        document.getElementById('staffel-titel').textContent = 'Staffelpreis hinzufügen';
        ['staffel-kg','staffel-menge','staffel-brutto','staffel-netto'].forEach(id => {
            document.getElementById(id).value = '';
        });
    }
    document.getElementById('staffel-backdrop').style.display = 'flex';
}
function staffelModalSchliessen() { document.getElementById('staffel-backdrop').style.display = 'none'; }

function staffelNettoBerechnen() {
    const brutto = parseFloat(document.getElementById('staffel-brutto').value);
    if (!isNaN(brutto) && brutto > 0) {
        document.getElementById('staffel-netto').value = (brutto / (1 + window.MEALANA_MWST_SATZ / 100)).toFixed(4);
    }
}
function staffelSpeichern() {
    const data = new FormData(document.getElementById('staffel-form'));
    fetch('staffelpreis_speichern.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                staffelModalSchliessen();
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else { showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler'); }
        });
}
function staffelLoeschen(spId) {
    if (!confirm('Staffelpreis wirklich löschen?')) return;
    const data = new FormData();
    data.append('id', spId);
    data.append('artikel_id', window.MEALANA_ARTIKEL_ID);
    fetch('staffelpreis_loeschen.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else { showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler'); }
        });
}

// SALE-Override Modal
function saleModalOeffnen(sale) {
    document.getElementById('sale-id').value          = sale ? sale.id : '';
    document.getElementById('sale-kg').value          = sale ? (sale.kundengruppen_id ?? '') : '';
    document.getElementById('sale-brutto').value      = sale ? sale.brutto_vk : '';
    document.getElementById('sale-netto').value       = sale ? sale.netto_vk  : '';
    document.getElementById('sale-vorher').value      = sale ? (sale.preis_vorher_brutto ?? '') : '';
    document.getElementById('sale-ab').value          = sale && sale.gueltig_ab  ? sale.gueltig_ab.substring(0, 16)  : '';
    document.getElementById('sale-bis').value         = sale && sale.gueltig_bis ? sale.gueltig_bis.substring(0, 16) : '';
    document.getElementById('sale-lagerstand').checked = sale ? !!parseInt(sale.bis_lagerstand_null) : false;
    document.getElementById('sale-backdrop').style.display = 'flex';
}
function saleModalSchliessen() { document.getElementById('sale-backdrop').style.display = 'none'; }

function saleNettoBerechnen() {
    const brutto = parseFloat(document.getElementById('sale-brutto').value);
    if (!isNaN(brutto) && brutto > 0) {
        document.getElementById('sale-netto').value = (brutto / (1 + window.MEALANA_MWST_SATZ / 100)).toFixed(4);
    }
}
function saleSpeichern() {
    const data = new FormData(document.getElementById('sale-form'));
    if (!document.getElementById('sale-lagerstand').checked) data.delete('bis_lagerstand_null');
    fetch('sale_override_speichern.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                saleModalSchliessen();
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else { showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler'); }
        });
}
function saleLoeschen(saleId) {
    if (!confirm('SALE-Override wirklich löschen?')) return;
    const data = new FormData();
    data.append('id', saleId);
    data.append('artikel_id', window.MEALANA_ARTIKEL_ID);
    fetch('sale_override_loeschen.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(json => {
            if (json.erfolg) {
                location.href = 'detail.php?id=' + window.MEALANA_ARTIKEL_ID + '&tab=preise';
            } else { showFlash(json.fehler ?? 'Unbekannter Fehler', 'fehler'); }
        });
}

function varPanel(name) {
    document.getElementById('var-panel-gen').classList.toggle('versteckt',    name !== 'gen');
    document.getElementById('var-panel-kinder').classList.toggle('versteckt', name !== 'kinder');
    document.getElementById('var-btn-gen').className    = 'btn btn-sm ' + (name === 'gen'    ? 'btn-primary' : 'btn-secondary');
    document.getElementById('var-btn-kinder').className = 'btn btn-sm ' + (name === 'kinder' ? 'btn-primary' : 'btn-secondary');
}

// Achsen-Modal
function achsenModalOeffnen()    { document.getElementById('achsen-backdrop').style.display = 'flex'; }
function achsenModalSchliessen() { document.getElementById('achsen-backdrop').style.display = 'none'; }

function achseToggle(cb) {
    if (!cb.checked && cb.dataset.hasLocked) {
        cb.checked = true;
        showFlash('Diese Achse hat Kind-Artikel — sie kann nicht entfernt werden solange Kind-Artikel existieren.', 'fehler');
        return;
    }
    var id = cb.dataset.achseId;
    document.getElementById('achse-werte-' + id).style.display = cb.checked ? '' : 'none';
}
function achseZeileEntfernen(btn) { btn.closest('.achse-wert-zeile').remove(); }
function achseWertHoch(btn) {
    var zeile = btn.closest('.achse-wert-zeile');
    var prev  = zeile.previousElementSibling;
    if (prev && prev.classList.contains('achse-wert-zeile')) zeile.parentNode.insertBefore(zeile, prev);
}
function achseWertRunter(btn) {
    var zeile = btn.closest('.achse-wert-zeile');
    var next  = zeile.nextElementSibling;
    if (next && next.classList.contains('achse-wert-zeile')) zeile.parentNode.insertBefore(next, zeile);
}
function achseWertHinzufuegen(achseId) {
    var input     = document.querySelector('.achse-wert-input[data-achse-id="' + achseId + '"]');
    var wert      = input.value.trim();
    if (!wert) return;
    var container = document.getElementById('achse-chips-' + achseId);
    var zeile     = document.createElement('div');
    zeile.className = 'achse-wert-zeile';
    zeile.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:4px 6px;background:#F7FAFC;border:1px solid #E2E8F0;border-radius:4px;margin-bottom:3px';
    zeile.innerHTML = '<span class="achse-chip-text" style="font-size:13px">' + wert.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</span>' +
        '<div style="display:flex;gap:2px;flex-shrink:0">' +
        '<button type="button" onclick="achseWertHoch(this)" class="btn btn-secondary btn-xs" title="Nach oben">▲</button>' +
        '<button type="button" onclick="achseWertRunter(this)" class="btn btn-secondary btn-xs" title="Nach unten">▼</button>' +
        '<button type="button" onclick="achseZeileEntfernen(this)" class="btn btn-danger btn-xs" title="Entfernen">✕</button>' +
        '</div>';
    container.appendChild(zeile);
    input.value = '';
    input.focus();
}

function achsenSpeichern() {
    var btn = document.getElementById('achsen-speichern-btn');
    btn.disabled = true;
    var achsenDaten = [];
    document.querySelectorAll('.achse-checkbox').forEach(function (cb) {
        if (!cb.checked) return;
        var achseId = parseInt(cb.dataset.achseId);
        var werte   = [];
        document.querySelectorAll('#achse-chips-' + achseId + ' .achse-chip-text').forEach(function (t) {
            var txt = t.textContent.trim();
            if (txt) werte.push(txt);
        });
        achsenDaten.push({ id: achseId, werte: werte });
    });
    fetch('/mealana/artikel/achsen_zuweisen_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ artikel_id: window.MEALANA_ARTIKEL_ID, achsen: achsenDaten })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) { window.location.reload(); }
        else { showFlash(d.fehler || 'Fehler beim Speichern', 'fehler'); btn.disabled = false; }
    })
    .catch(function () { showFlash('Verbindungsfehler', 'fehler'); btn.disabled = false; });
}

// Stammdaten-Formular: Unsaved-Banner
(function () {
    var form = document.getElementById('stammdaten-form');
    if (form) form.addEventListener('change', function () {
        document.getElementById('unsaved-banner').style.display = 'inline-flex';
    });
    var banner = document.querySelector('.success-banner');
    if (banner) setTimeout(function () { banner.style.display = 'none'; }, 3000);
})();

// Merkmale-Modal
var _merkmalAktuell = null;

function merkmalWaehlen(merkmalId, mehrfach, werte, gesetzteIds) {
    _merkmalAktuell = { merkmalId, mehrfach, werte };
    document.getElementById('merk-modal-titel').textContent = 'Merkmal wählen';
    const liste = document.getElementById('merk-modal-liste');
    liste.innerHTML = '';
    werte.forEach(function (w) {
        const label = document.createElement('label');
        label.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 4px;cursor:pointer;font-size:13px';
        const input = document.createElement('input');
        input.type    = mehrfach ? 'checkbox' : 'radio';
        input.name    = 'merk-auswahl';
        input.value   = w.id;
        input.checked = gesetzteIds.includes(w.id);
        label.appendChild(input);
        label.appendChild(document.createTextNode(w.wert));
        liste.appendChild(label);
    });
    document.getElementById('merk-backdrop').style.display = 'flex';
}
function merkmalModalSchliessen() { document.getElementById('merk-backdrop').style.display = 'none'; }

function merkmalUebernehmen() {
    if (!_merkmalAktuell) return;
    const mid       = _merkmalAktuell.merkmalId;
    const gewaehlte = [...document.querySelectorAll('input[name="merk-auswahl"]:checked')].map(i => parseInt(i.value));
    const wertMap   = {};
    _merkmalAktuell.werte.forEach(function (w) { wertMap[w.id] = w.wert; });
    const chipsDiv  = document.getElementById('merk-chips-' + mid);
    chipsDiv.innerHTML = '';
    gewaehlte.forEach(function (wid) {
        const span = document.createElement('span');
        span.className   = 'chip chip-aktiv';
        span.style.fontSize = '12px';
        span.textContent = wertMap[wid] || wid;
        chipsDiv.appendChild(span);
        const inp  = document.createElement('input');
        inp.type   = 'hidden';
        inp.name   = 'merk[' + mid + '][]';
        inp.value  = wid;
        chipsDiv.appendChild(inp);
    });
    if (!gewaehlte.length) {
        chipsDiv.innerHTML = '<span style="font-size:12px;color:var(--color-text-muted)">–</span>';
    }
    merkmalModalSchliessen();
}

function merkmaleSpeichern(artikelId) {
    const daten = {};
    document.querySelectorAll('[name^="merk["]').forEach(function (inp) {
        const m = inp.name.match(/merk\[(\d+)\]/);
        if (!m) return;
        const mid = m[1];
        if (!daten[mid]) daten[mid] = [];
        daten[mid].push(parseInt(inp.value));
    });
    fetch('merkmale_speichern.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ artikel_id: artikelId, merkmale: daten })
    })
    .then(r => r.json())
    .then(function (d) {
        if (d.erfolg) { showFlash('Merkmale gespeichert', 'erfolg'); }
        else { showFlash(d.fehler || 'Fehler beim Speichern', 'fehler'); }
    });
}
