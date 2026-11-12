/* Grundpreise prüfen (artikel/grundpreise.php): Vorschau wie im Shop, Massenbearbeitung, Speichern. */
const gpZeilen = () => [...document.querySelectorAll('.gp-tab tbody tr')];
const gpZahl = v => { const n = parseFloat(String(v ?? '').replace(',', '.')); return isNaN(n) ? null : n; };
const gpEuro = n => n.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
const gpMenge = n => n.toLocaleString('de-AT', { maximumFractionDigits: 3 });
const gpFeld = (tr, name) => tr.querySelector(`[name="${name}"]`);

/** Rechnet wie ShopSyncService::ermittleGrundpreisBasis(): Garn + g → je 1 kg */
function gpVorschau(tr) {
    const zelle = tr.querySelector('.gp-vorschau');
    const an = gpFeld(tr, 'grundpreis_anzeigen').checked;
    let menge = gpZahl(gpFeld(tr, 'inhalt_menge').value);
    let einheit = gpFeld(tr, 'inhalt_einheit').value.toLowerCase();
    let bezug = gpZahl(gpFeld(tr, 'grundpreis_bezugsmenge').value);
    const typSel = gpFeld(tr, 'artikeltyp_id');
    const garn = typSel.options[typSel.selectedIndex].dataset.code === 'GARN';
    const preis = gpZahl(tr.dataset.preis);
    zelle.classList.remove('ist-leer');
    if (!an) { zelle.textContent = '– kein Grundpreis –'; zelle.classList.add('ist-leer'); return; }
    if (!menge || menge <= 0 || !einheit) { zelle.textContent = '! Inhalt fehlt'; return; }
    if (garn && einheit === 'g') { menge = menge / 1000; einheit = 'kg'; bezug = 1; }
    if (!bezug || bezug <= 0) bezug = garn ? 100 : null;
    if (!bezug) { zelle.textContent = '! Bezugsmenge fehlt'; return; }
    const je = (bezug === 1 ? '' : gpMenge(bezug) + ' ') + einheit;
    const gp = preis ? gpEuro(preis / menge * bezug) : '? €';
    zelle.textContent = `${gp} / ${je} · enthält ${gpMenge(menge)} ${einheit}`;
}

function gpGeaendert(tr) {
    tr.classList.add('ist-geaendert');
    gpVorschau(tr);
    const n = document.querySelectorAll('.gp-tab tr.ist-geaendert').length;
    const b = document.getElementById('gp-speichern');
    b.disabled = n === 0;
    b.textContent = n ? `Änderungen speichern (${n})` : 'Änderungen speichern';
}

function gpMarkierte() {
    const m = gpZeilen().filter(tr => tr.querySelector('.gp-wahl').checked);
    if (!m.length) gpMeldung('Bitte zuerst Zeilen markieren (Kästchen links).', true);
    return m;
}

function gpSetze(tr, werte) {
    if (werte.typCode) {
        const sel = gpFeld(tr, 'artikeltyp_id');
        const opt = [...sel.options].find(o => o.dataset.code === werte.typCode); if (opt) sel.value = opt.value;
    }
    if (werte.typ) gpFeld(tr, 'artikeltyp_id').value = werte.typ;
    if (werte.menge !== undefined && werte.menge !== '') gpFeld(tr, 'inhalt_menge').value = werte.menge;
    if (werte.einheit) gpFeld(tr, 'inhalt_einheit').value = werte.einheit;
    if (werte.bezug !== undefined && werte.bezug !== '') gpFeld(tr, 'grundpreis_bezugsmenge').value = werte.bezug;
    if (werte.an !== undefined && werte.an !== '') gpFeld(tr, 'grundpreis_anzeigen').checked = werte.an === '1' || werte.an === true;
    gpGeaendert(tr);
}

function gpMasse() {
    const w = {
        typ: document.getElementById('gp-m-typ').value, menge: document.getElementById('gp-m-menge').value.trim(),
        einheit: document.getElementById('gp-m-einheit').value, bezug: document.getElementById('gp-m-bezug').value.trim(),
        an: document.getElementById('gp-m-an').value,
    };
    gpMarkierte().forEach(tr => gpSetze(tr, w));
}
function gpSchnell(gramm) { gpMarkierte().forEach(tr => gpSetze(tr, { typCode: 'GARN', menge: String(gramm), einheit: 'g', bezug: '100', an: true })); }
function gpKeiner() { gpMarkierte().forEach(tr => gpSetze(tr, { an: false })); }
function gpAlle(an) { gpZeilen().forEach(tr => { tr.querySelector('.gp-wahl').checked = an; }); }

function gpMeldung(text, fehler) {
    const el = document.getElementById('gp-meldung');
    el.innerHTML = text; el.style.color = fehler ? 'var(--color-danger)' : 'var(--color-success, #15803d)';
}

async function gpSenden(daten) {
    const r = await fetch(`${GP.basis}/artikel/grundpreise_speichern.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) });
    try { return await r.json(); } catch { return { erfolg: false, fehler: ['Antwort des Servers nicht lesbar (' + r.status + ')'] }; }
}

async function gpSpeichern() {
    const zeilen = gpZeilen().filter(tr => tr.classList.contains('ist-geaendert')).map(tr => ({
        id: +tr.dataset.id,
        artikeltyp_id: gpFeld(tr, 'artikeltyp_id').value,
        inhalt_menge: gpFeld(tr, 'inhalt_menge').value,
        inhalt_einheit: gpFeld(tr, 'inhalt_einheit').value,
        grundpreis_bezugsmenge: gpFeld(tr, 'grundpreis_bezugsmenge').value,
        grundpreis_anzeigen: gpFeld(tr, 'grundpreis_anzeigen').checked ? 1 : 0,
    }));
    if (!zeilen.length) return;
    const b = document.getElementById('gp-speichern'); b.disabled = true;
    const d = await gpSenden({ aktion: 'speichern', zeilen });
    if (!d.erfolg) { b.disabled = false; gpMeldung((d.fehler || ['Fehler']).map(f => f.replace(/</g, '&lt;')).join('<br>'), true); return; }
    gpMeldung(`✓ ${d.gespeichert} Artikel gespeichert und an die Kinder vererbt – Shop-Abgleich vorgemerkt. Liste wird neu geladen …`);
    setTimeout(() => location.reload(), 1500);
}

async function gpVererben() {
    const m = gpMarkierte(); if (!m.length) return;
    if (document.querySelector('.gp-tab tr.ist-geaendert')) { gpMeldung('Bitte zuerst die Änderungen speichern (Speichern vererbt ohnehin).', true); return; }
    const d = await gpSenden({ aktion: 'vererben', ids: m.map(tr => +tr.dataset.id) });
    if (!d.erfolg) { gpMeldung((d.fehler || ['Fehler']).join('<br>'), true); return; }
    gpMeldung(`✓ ${d.gespeichert} Väter an ihre Kinder vererbt – Shop-Abgleich vorgemerkt. Liste wird neu geladen …`);
    setTimeout(() => location.reload(), 1500);
}

gpZeilen().forEach(tr => {
    gpVorschau(tr);
    tr.querySelectorAll('.gp-f').forEach(el => el.addEventListener(el.type === 'checkbox' || el.tagName === 'SELECT' ? 'change' : 'input', () => gpGeaendert(tr)));
});
window.addEventListener('beforeunload', e => { if (document.querySelector('.gp-tab tr.ist-geaendert') && !document.getElementById('gp-speichern').disabled) { e.preventDefault(); e.returnValue = ''; } });
