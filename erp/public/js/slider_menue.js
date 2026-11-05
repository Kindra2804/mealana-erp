/**
 * Shop-Menü (slider/menue.php): Bearbeiten-Fenster, Sortieren per Ziehen (innerhalb einer Ebene),
 * Ein/Aus, Löschen, Vorschlag aus dem Kategoriebaum, Übertragen. Server: slider/menue_aktion.php
 */
const mnForm = document.getElementById('mn-form');
const mnFeld = name => mnForm.elements[name];

function menueTyp() {
    const typ = mnFeld('typ').value;
    mnForm.querySelectorAll('.mn-nur').forEach(el => {
        el.style.display = el.dataset.typ.split(' ').includes(typ) ? '' : 'none';
    });
    document.getElementById('mn-bez-label').textContent = ['url', 'hersteller_alle'].includes(typ)
        ? 'Bezeichnung im Menü' : 'Bezeichnung im Menü (leer = Name der Kategorie / des Herstellers / Artikels)';
}

function menueFuellen(p) {
    mnForm.reset();
    mnFeld('id').value = p.id || '';
    mnFeld('typ').value = p.typ || 'kategorie';
    mnFeld('ziel_kategorie').value = p.typ === 'kategorie' ? (p.ziel_id || '') : '';
    mnFeld('ziel_hersteller').value = p.typ === 'hersteller' ? (p.ziel_id || '') : '';
    mnFeld('ziel_artikel').value = p.typ === 'artikel' ? (p.ziel_id || '') : '';
    document.getElementById('mn-artikel-suche').value = p.typ === 'artikel' ? (p.ziel_name || '') : '';
    mnFeld('url').value = p.url || '';
    mnFeld('bezeichnung').value = p.bezeichnung || '';
    mnFeld('parent_id').value = p.parent_id || '';
    mnFeld('unterkategorien_auto').checked = !!Number(p.unterkategorien_auto);
    mnFeld('nur_mit_artikeln').checked = !!Number(p.nur_mit_artikeln);
    mnFeld('spalten').checked = p.spalten === undefined ? true : !!Number(p.spalten);
    mnFeld('ist_aktiv').checked = p.ist_aktiv === undefined ? true : !!Number(p.ist_aktiv);
    // Ein Punkt kann nicht unter sich selbst hängen
    [...mnFeld('parent_id').options].forEach(o => { o.disabled = p.id && Number(o.value) === Number(p.id); });
    document.getElementById('mn-fehler').textContent = '';
    menueTyp();
    document.getElementById('mn-modal').style.display = 'flex';
}

function menueNeu(parentId) {
    menueFuellen({ typ: 'kategorie', parent_id: parentId || '', unterkategorien_auto: 0, spalten: 1, ist_aktiv: 1 });
    document.getElementById('mn-modal-titel').textContent = parentId ? 'Neuer Unterpunkt' : 'Neuer Menüpunkt';
}

function menueBearbeiten(id) {
    menueFuellen(MENUE.punkte[id]);
    document.getElementById('mn-modal-titel').textContent = 'Menüpunkt bearbeiten';
}

async function menueSpeichern(e) {
    e.preventDefault();
    const fd = new FormData(mnForm);
    const typ = fd.get('typ');
    fd.set('ziel_id', ['kategorie', 'hersteller', 'artikel'].includes(typ) ? (fd.get('ziel_' + typ) || '') : '');
    if (!mnFeld('ist_aktiv').checked) fd.set('ist_aktiv', '0');
    fd.set('aktion', 'speichern');
    const d = await (await fetch(`${MENUE.basis}/slider/menue_aktion.php`, { method: 'POST', body: fd })).json();
    if (d.erfolg) { location.reload(); return; }
    document.getElementById('mn-fehler').innerHTML = [].concat(d.fehler).map(f => '• ' + String(f).replace(/</g, '&lt;')).join('<br>');
}

async function menueAktion(aktion, daten = {}) {
    const fd = new FormData();
    fd.set('aktion', aktion);
    fd.set('shop_id', MENUE.shopId);
    Object.entries(daten).forEach(([k, v]) => [].concat(v).forEach(w => fd.append(Array.isArray(v) ? `${k}[]` : k, w)));
    const d = await (await fetch(`${MENUE.basis}/slider/menue_aktion.php`, { method: 'POST', body: fd })).json();
    if (!d.erfolg) alert(d.fehler || 'Fehler');
    else if (aktion !== 'uebertragen') location.reload();
    return d;
}

function menueLoeschen(id) {
    const p = MENUE.punkte[id];
    if (confirm(`Menüpunkt „${p.bezeichnung || p.ziel_name || ''}“ löschen?\n\nUnterpunkte werden mitgelöscht. Im Shop verschwindet er beim nächsten Übertragen.`)) {
        menueAktion('loeschen', { id });
    }
}

async function menueUebertragen() {
    const k = document.getElementById('mn-uebertragen');
    k.disabled = true;
    k.textContent = 'Überträgt …';
    const d = await menueAktion('uebertragen');
    if (d.erfolg) {
        let m = `${d.uebertragen} Menüpunkt(e) an den Shop übertragen.`;
        if (d.hinweise && d.hinweise.length) m += '\n\nHinweise:\n' + d.hinweise.join('\n');
        alert(m);
    }
    location.reload();
}

// ── Artikel-Suche (gleiche Suche wie beim Slider) ─────────────────────────
let mnTimer = null;
function menueArtikelSuche(q) {
    mnFeld('ziel_artikel').value = '';
    const box = document.getElementById('mn-treffer');
    clearTimeout(mnTimer);
    if (q.trim().length < 2) { box.style.display = 'none'; return; }
    mnTimer = setTimeout(async () => {
        const treffer = await (await fetch(`${MENUE.basis}/slider/artikel_suche.php?shop=${MENUE.shopId}&q=${encodeURIComponent(q)}`)).json();
        box.innerHTML = treffer.length ? '' : '<div style="color:#999;cursor:default">Kein Artikel im Shop gefunden</div>';
        treffer.forEach(a => {
            const d = document.createElement('div');
            d.textContent = `${a.artikelnummer} – ${a.name}`;
            d.onclick = () => { mnFeld('ziel_artikel').value = a.id; document.getElementById('mn-artikel-suche').value = d.textContent; box.style.display = 'none'; };
            box.appendChild(d);
        });
        box.style.display = '';
    }, 250);
}

// ── Sortieren per Ziehen: nur zwischen Punkten derselben Ebene + desselben Elternpunkts ──
let mnZieh = null;
document.querySelectorAll('.mn-zeile').forEach(z => {
    z.querySelector('.mn-griff').addEventListener('mousedown', () => { z.draggable = true; });
    z.addEventListener('dragstart', e => { mnZieh = z; z.classList.add('mn-drag'); e.dataTransfer.effectAllowed = 'move'; });
    z.addEventListener('dragend', () => { z.classList.remove('mn-drag'); z.draggable = false; document.querySelectorAll('.mn-ueber').forEach(x => x.classList.remove('mn-ueber')); });
    z.addEventListener('dragover', e => {
        if (!mnZieh || mnZieh === z || mnZieh.dataset.gruppe !== z.dataset.gruppe) return;
        e.preventDefault();
        z.classList.add('mn-ueber');
    });
    z.addEventListener('dragleave', () => z.classList.remove('mn-ueber'));
    z.addEventListener('drop', e => {
        e.preventDefault();
        z.classList.remove('mn-ueber');
        if (!mnZieh || mnZieh.dataset.gruppe !== z.dataset.gruppe) return;
        // Reihenfolge der Geschwister neu berechnen (Unterpunkte wandern beim Neuladen mit)
        const geschwister = [...document.querySelectorAll(`.mn-zeile[data-gruppe="${z.dataset.gruppe}"]`)].map(x => x.dataset.id).filter(id => id !== mnZieh.dataset.id);
        geschwister.splice(geschwister.indexOf(z.dataset.id), 0, mnZieh.dataset.id);
        menueAktion('sortieren', { ids: geschwister });
    });
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') document.getElementById('mn-modal').style.display = 'none'; });
