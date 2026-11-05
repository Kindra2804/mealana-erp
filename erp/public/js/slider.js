/**
 * Shop-Slider (slider/liste.php): Bearbeiten-Fenster mit Live-Vorschau, Sortieren per Ziehen,
 * Ein/Aus, Kopieren, Löschen, Übertragen. Server: slider/slide_speichern.php + slider/slide_aktion.php
 */

const slForm = document.getElementById('sl-form');
const $ = (sel, el = slForm) => el.querySelector(sel);
const feld = name => slForm.elements[name];
const radioWert = name => (slForm.querySelector(`input[name="${name}"]:checked`) || {}).value || '';
const setzeRadio = (name, wert) => slForm.querySelectorAll(`input[name="${name}"]`).forEach(r => { r.checked = r.value === wert; });

let slBildUrl = { desktop: '', mobil: '' };

// ── Fenster öffnen ─────────────────────────────────────────────────────────

function sliderNeu() {
    sliderFuellen({
        id: '', position: 'startseite', kategorie_id: '', art: 'gestaltet', bezeichnung: '', kicker: '', titel: '', text: '',
        text_position: 'links', schrift: 'dunkel', sichtbar: 'immer', ist_aktiv: 1, alt_text: '',
    });
    document.getElementById('sl-modal-titel').textContent = 'Neue Slide';
}

function sliderBearbeiten(id) {
    sliderFuellen(SLIDER.slides[id]);
    document.getElementById('sl-modal-titel').textContent = 'Slide bearbeiten';
}

function sliderFuellen(s) {
    slForm.reset();
    feld('id').value = s.id || '';
    setzeRadio('position', s.position);
    feld('kategorie_id').value = s.kategorie_id || '';
    setzeRadio('art', s.art);
    ['bezeichnung', 'kicker', 'titel', 'text', 'alt_text'].forEach(k => { feld(k).value = s[k] || ''; });
    setzeRadio('text_position', s.text_position);
    setzeRadio('schrift', s.schrift);
    setzeRadio('sichtbar', s.sichtbar);
    feld('ist_aktiv').checked = !!Number(s.ist_aktiv);
    feld('gueltig_von').value = (s.gueltig_von || '').replace(' ', 'T').slice(0, 16);
    feld('gueltig_bis').value = (s.gueltig_bis || '').replace(' ', 'T').slice(0, 16);
    feld('aktion_id').value = s.aktion_id || '';

    [1, 2].forEach(n => {
        feld(`button${n}_text`).value = s[`button${n}_text`] || '';
        const typ = s[`button${n}_ziel_typ`] || '';
        feld(`button${n}_ziel_typ`).value = typ;
        feld(`button${n}_kategorie`).value = typ === 'kategorie' ? s[`button${n}_ziel_id`] : '';
        feld(`button${n}_hersteller`).value = typ === 'hersteller' ? s[`button${n}_ziel_id`] : '';
        feld(`button${n}_artikel`).value = typ === 'artikel' ? s[`button${n}_ziel_id`] : '';
        $(`.sl-ziel-artikel`, feld(`button${n}_ziel_typ`).parentElement).value = s[`button${n}_artikel_name`] || '';
        feld(`button${n}_url`).value = s[`button${n}_url`] || '';
        sliderZielTyp(n);
    });

    const pfad = datei => datei ? `${SLIDER.basis}/uploads/slider/${s.id}/${datei}` : '';
    slBildUrl = { desktop: pfad(s.bild_desktop), mobil: pfad(s.bild_mobil) };
    zeigeBild('desktop', slBildUrl.desktop);
    zeigeBild('mobil', slBildUrl.mobil);
    document.getElementById('sl-mobil-weg').style.display = s.bild_mobil ? '' : 'none';

    document.getElementById('sl-fehler').textContent = '';
    sliderSichtbarkeit();
    document.getElementById('sl-modal').style.display = 'flex';
}

function sliderSchliessen() {
    document.getElementById('sl-modal').style.display = 'none';
}

function zeigeBild(variante, url) {
    const img = document.getElementById(`sl-bild-${variante}-vorschau`);
    img.src = url || '';
    img.style.visibility = url ? 'visible' : 'hidden';
}

// ── Felder je nach Auswahl ein-/ausblenden ─────────────────────────────────

function sliderSichtbarkeit() {
    const fertig = radioWert('art') === 'fertiges_bild';
    const sichtbar = radioWert('sichtbar');
    slForm.querySelectorAll('.nur-gestaltet').forEach(el => { el.style.display = fertig ? 'none' : ''; });
    slForm.querySelectorAll('.nur-fertig').forEach(el => { el.style.display = fertig ? '' : 'none'; });
    slForm.querySelectorAll('.nur-zeitraum').forEach(el => { el.style.display = sichtbar === 'zeitraum' ? '' : 'none'; });
    slForm.querySelectorAll('.nur-aktion').forEach(el => { el.style.display = sichtbar === 'aktion' ? '' : 'none'; });
    document.getElementById('sl-kat-feld').style.visibility = radioWert('position') === 'kategorie' ? 'visible' : 'hidden';
    document.getElementById('sl-titel-label').textContent = fertig ? 'Titel (nur intern, optional)' : 'Titel';
    document.getElementById('sl-buttons-titel').textContent = fertig ? 'Link (Klick auf das Bild führt zu …)' : 'Buttons';
    $('.sl-btn-label-1').textContent = fertig ? 'Ziel' : 'Button 1 (Hauptbutton)';
    sliderVorschau();
}

function sliderZielTyp(n) {
    const typ = feld(`button${n}_ziel_typ`).value;
    const box = feld(`button${n}_ziel_typ`).parentElement;
    box.querySelectorAll('.sl-ziel').forEach(el => {
        el.style.display = el.classList.contains(`sl-ziel-${typ}`) ? '' : 'none';
    });
}

// ── Artikel-Suche für das Button-Ziel ─────────────────────────────────────

let slSucheTimer = null;
function sliderArtikelSuche(n, q) {
    feld(`button${n}_artikel`).value = '';
    const box = document.getElementById(`sl-treffer-${n}`);
    clearTimeout(slSucheTimer);
    if (q.trim().length < 2) { box.style.display = 'none'; return; }
    slSucheTimer = setTimeout(async () => {
        const r = await fetch(`${SLIDER.basis}/slider/artikel_suche.php?shop=${SLIDER.shopId}&q=${encodeURIComponent(q)}`);
        const treffer = await r.json();
        box.innerHTML = '';
        if (!treffer.length) box.innerHTML = '<div style="color:#999;cursor:default">Kein Artikel im Shop gefunden</div>';
        treffer.forEach(a => {
            const d = document.createElement('div');
            d.textContent = `${a.artikelnummer} – ${a.name}`;
            d.onclick = () => {
                feld(`button${n}_artikel`).value = a.id;
                $('.sl-ziel-artikel', feld(`button${n}_ziel_typ`).parentElement).value = d.textContent;
                box.style.display = 'none';
            };
            box.appendChild(d);
        });
        box.style.display = '';
    }, 250);
}

// ── Live-Vorschau ─────────────────────────────────────────────────────────

function sliderVorschau() {
    const v = document.getElementById('sl-vorschau');
    const fertig = radioWert('art') === 'fertiges_bild';
    v.className = `sl-vorschau ${radioWert('text_position') || 'links'} ${radioWert('schrift') || 'dunkel'} ${fertig ? 'fertig' : ''}`;
    v.style.backgroundImage = slBildUrl.desktop ? `url("${slBildUrl.desktop}")` : '';
    const t = $('.sl-vtext', v);
    t.style.display = fertig ? 'none' : '';
    $('.sl-vkicker', v).textContent = feld('kicker').value;
    $('.sl-vtitel', v).textContent = feld('titel').value || 'Titel der Slide';
    $('.sl-vbeschr', v).textContent = feld('text').value;
    const btns = $('.sl-vbtns', v);
    btns.innerHTML = '';
    [1, 2].forEach(n => {
        const text = feld(`button${n}_text`).value.trim();
        if (!text) return;
        const b = document.createElement('span');
        b.className = 'sl-vbtn' + (n === 2 ? ' zwei' : '');
        b.textContent = text;
        btns.appendChild(b);
    });
}

slForm.addEventListener('input', e => {
    if (e.target.type === 'file') return;
    sliderVorschau();
});
slForm.addEventListener('change', e => {
    if (e.target.type === 'radio') sliderSichtbarkeit();
    if (e.target.type === 'file') {
        const variante = e.target.name === 'bild_mobil' ? 'mobil' : 'desktop';
        const datei = e.target.files[0];
        if (datei) {
            slBildUrl[variante] = URL.createObjectURL(datei);
            zeigeBild(variante, slBildUrl[variante]);
            sliderVorschau();
        }
    }
});

// ── Speichern ─────────────────────────────────────────────────────────────

async function sliderSpeichern(e) {
    e.preventDefault();
    const fd = new FormData(slForm);
    // Ziel-ID aus dem passenden Auswahlfeld übernehmen
    [1, 2].forEach(n => {
        const typ = fd.get(`button${n}_ziel_typ`);
        fd.set(`button${n}_ziel_id`, ['kategorie', 'hersteller', 'artikel'].includes(typ) ? (fd.get(`button${n}_${typ}`) || '') : '');
    });
    if (!feld('ist_aktiv').checked) fd.set('ist_aktiv', '0');

    const knopf = document.getElementById('sl-speichern');
    knopf.disabled = true;
    knopf.textContent = 'Speichert …';
    try {
        const r = await fetch(`${SLIDER.basis}/slider/slide_speichern.php`, { method: 'POST', body: fd });
        const d = await r.json();
        if (d.erfolg) { location.reload(); return; }
        document.getElementById('sl-fehler').innerHTML = [].concat(d.fehler).map(f => `• ${escapeHtml(f)}`).join('<br>');
    } catch (err) {
        document.getElementById('sl-fehler').textContent = 'Speichern fehlgeschlagen: ' + err.message;
    }
    knopf.disabled = false;
    knopf.textContent = 'Speichern';
}

function escapeHtml(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Aktionen in der Liste ─────────────────────────────────────────────────

async function sliderAktion(aktion, daten = {}) {
    const fd = new FormData();
    fd.set('aktion', aktion);
    fd.set('shop_id', SLIDER.shopId);
    Object.entries(daten).forEach(([k, v]) => [].concat(v).forEach(w => fd.append(Array.isArray(v) ? `${k}[]` : k, w)));
    const r = await fetch(`${SLIDER.basis}/slider/slide_aktion.php`, { method: 'POST', body: fd });
    return r.json().then(d => {
        if (!d.erfolg) { alert(d.fehler || 'Fehler'); return d; }
        if (!['uebertragen', 'kopieren'].includes(aktion)) location.reload();
        return d;
    });
}

function sliderLoeschen(id) {
    if (confirm(`Slide „${SLIDER.slides[id].bezeichnung}“ löschen?\n\nIm Shop verschwindet sie beim nächsten Übertragen.`)) {
        sliderAktion('loeschen', { id });
    }
}

let slKopieId = null;
function sliderKopieren(id) {
    slKopieId = id;
    document.getElementById('sl-kopie-modal').style.display = 'flex';
}
function sliderKopierenOk() {
    const ziel = document.getElementById('sl-kopie-shop').value;
    sliderAktion('kopieren', { id: slKopieId, ziel_shop_id: ziel }).then(d => {
        if (d.erfolg) location.href = `?shop=${ziel}`;
    });
}

function sliderIntervall(sek) {
    sliderAktion('intervall', { sekunden: sek });
}

async function sliderUebertragen() {
    const knopf = document.getElementById('sl-uebertragen');
    knopf.disabled = true;
    knopf.textContent = 'Überträgt …';
    const d = await sliderAktion('uebertragen');
    let meldung = d.erfolg ? `${d.uebertragen} Slide(s) an den Shop übertragen.` : `Übertragen fehlgeschlagen:\n${d.fehler}`;
    if (d.hinweise && d.hinweise.length) meldung += '\n\nHinweise:\n' + d.hinweise.join('\n');
    if (d.erfolg) alert(meldung);
    location.reload();
}

// ── Sortieren per Ziehen (nur innerhalb derselben Gruppe) ─────────────────

let slZiehZeile = null;
document.querySelectorAll('.sl-sortierbar tr').forEach(tr => {
    const griff = tr.querySelector('.sl-griff');
    griff.addEventListener('mousedown', () => { tr.draggable = true; });
    tr.addEventListener('dragstart', e => { slZiehZeile = tr; tr.classList.add('sl-drag'); e.dataTransfer.effectAllowed = 'move'; });
    tr.addEventListener('dragend', () => {
        tr.classList.remove('sl-drag');
        tr.draggable = false;
        document.querySelectorAll('.sl-ueber').forEach(z => z.classList.remove('sl-ueber'));
    });
    tr.addEventListener('dragover', e => {
        if (!slZiehZeile || slZiehZeile === tr || slZiehZeile.dataset.gruppe !== tr.dataset.gruppe) return;
        e.preventDefault();
        tr.classList.add('sl-ueber');
    });
    tr.addEventListener('dragleave', () => tr.classList.remove('sl-ueber'));
    tr.addEventListener('drop', e => {
        e.preventDefault();
        tr.classList.remove('sl-ueber');
        if (!slZiehZeile || slZiehZeile.dataset.gruppe !== tr.dataset.gruppe) return;
        tr.parentNode.insertBefore(slZiehZeile, tr);
        const ids = [...tr.parentNode.querySelectorAll(`tr[data-gruppe="${tr.dataset.gruppe}"]`)].map(z => z.dataset.id);
        sliderAktion('sortieren', { ids });
    });
});

// Klick neben das Fenster schließt es nicht (Eingaben sollen nicht verloren gehen) – nur Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        sliderSchliessen();
        document.getElementById('sl-kopie-modal').style.display = 'none';
    }
});
