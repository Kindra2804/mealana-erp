// Newsletter-Editor (newsletter/editor.php): Bausteine bearbeiten, speichern, Vorschau, Testmail.
// Alle Bausteine liegen in NLE.bausteine; jede Eingabe ändert das Objekt direkt, gespeichert wird per Knopf.
const NLE_TITEL = { kopfbild: '🖼 Kopfbild', text: '📝 Text', bild: '🏞 Bild', artikel: '🧶 Artikel', knopf: '🔘 Knopf', hinweis: '🎁 Aktion/Gutschein', trenner: '➖ Trenner' };
const NLE_LEER  = {
    kopfbild: { bild: '', link: '', alt: '' }, text: { ueberschrift: '', text: '' }, bild: { bild: '', link: '', alt: '', text: '' },
    artikel: { ueberschrift: '', artikel_ids: [], spalten: 2 }, knopf: { text: 'Zum Shop', link: '' },
    hinweis: { ueberschrift: '', text: '', code: '' }, trenner: {},
};
let nleArtikelCache = {};
let nleGeaendert = false;

function nleEsc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
function nleBasis() { return window.BASE_PATH + '/newsletter/editor_aktion.php'; }
function nleStatus(t, farbe) { const s = document.getElementById('nle-status'); s.textContent = t; s.style.color = farbe || ''; }
function nleMarkieren() { nleGeaendert = true; nleStatus('nicht gespeichert', '#d97706'); }

function nleFeld(i, feld, label, art) {
    const b = NLE.bausteine[i];
    const dis = NLE.gesperrt ? ' disabled' : '';
    if (art === 'textarea') {
        return '<label>' + label + '<textarea rows="5" class="erp-input"' + dis + ' oninput="nleSetze(' + i + ',\'' + feld + '\',this.value)">' + nleEsc(b[feld]) + '</textarea></label>';
    }
    return '<label>' + label + '<input type="text" class="erp-input"' + dis + ' value="' + nleEsc(b[feld]) + '" oninput="nleSetze(' + i + ',\'' + feld + '\',this.value)"></label>';
}
function nleSetze(i, feld, wert) { NLE.bausteine[i][feld] = wert; nleMarkieren(); }

function nleBildFeld(i) {
    const b = NLE.bausteine[i];
    let h = '<label>Bild</label>';
    if (b.bild) h += '<img src="' + window.BASE_PATH + '/' + nleEsc(b.bild) + '" style="max-width:100%;max-height:120px;display:block;margin:4px 0;border-radius:4px">';
    if (!NLE.gesperrt) h += '<input type="file" accept="image/*" onchange="nleBildHoch(' + i + ', this)">';
    return h;
}

function nleRendern() {
    const box = document.getElementById('nle-bausteine');
    box.innerHTML = '';
    NLE.bausteine.forEach((b, i) => {
        let h = '<div class="nle-kopf"><span>' + (NLE_TITEL[b.typ] || b.typ) + '</span><span>';
        if (!NLE.gesperrt) {
            h += '<button class="btn btn-sm" onclick="nleSchieben(' + i + ',-1)" title="nach oben">↑</button>'
               + '<button class="btn btn-sm" onclick="nleSchieben(' + i + ',1)" title="nach unten">↓</button>'
               + '<button class="btn btn-sm" onclick="nleLoeschen(' + i + ')" title="entfernen">✕</button>';
        }
        h += '</span></div>';
        switch (b.typ) {
            case 'kopfbild': h += nleBildFeld(i) + nleFeld(i, 'link', 'Link (optional)') + nleFeld(i, 'alt', 'Bildbeschreibung'); break;
            case 'text':     h += nleFeld(i, 'ueberschrift', 'Überschrift') + nleFeld(i, 'text', 'Text', 'textarea'); break;
            case 'bild':     h += nleBildFeld(i) + nleFeld(i, 'link', 'Link (optional)') + nleFeld(i, 'alt', 'Bildbeschreibung') + nleFeld(i, 'text', 'Bildunterschrift'); break;
            case 'knopf':    h += nleFeld(i, 'text', 'Beschriftung') + nleFeld(i, 'link', 'Link (leer = Shop-Startseite)'); break;
            case 'hinweis':  h += nleFeld(i, 'ueberschrift', 'Überschrift') + nleFeld(i, 'text', 'Text', 'textarea') + nleFeld(i, 'code', 'Code (z.B. Gutschein-/Aktionscode, optional)'); break;
            case 'artikel':  h += nleArtikelHtml(i); break;
            case 'trenner':  h += '<div style="font-size:12px;color:var(--color-text-muted)">Trennlinie</div>'; break;
        }
        const div = document.createElement('div');
        div.className = 'nle-block';
        div.innerHTML = h;
        box.appendChild(div);
    });
    nleArtikelNamenLaden();
}

function nleArtikelHtml(i) {
    const b = NLE.bausteine[i];
    let h = nleFeld(i, 'ueberschrift', 'Überschrift');
    h += '<label>Spalten</label><select class="erp-select"' + (NLE.gesperrt ? ' disabled' : '') + ' onchange="nleSetze(' + i + ',\'spalten\',parseInt(this.value))">'
       + [1, 2, 3].map(s => '<option' + (b.spalten == s ? ' selected' : '') + '>' + s + '</option>').join('') + '</select>';
    h += '<div style="margin-top:6px">';
    (b.artikel_ids || []).forEach((id, k) => {
        const a = nleArtikelCache[id];
        h += '<div class="nle-art"><span>' + (a ? nleEsc(a.name) + ' <span style="color:#94a3b8">' + nleEsc(a.artikelnummer) + '</span>' : 'Artikel #' + id)
           + (a && a.preis != null ? ' · € ' + Number(a.preis).toFixed(2) : '') + '</span>'
           + (NLE.gesperrt ? '' : '<span><button class="btn btn-sm" onclick="nleArtSchieben(' + i + ',' + k + ',-1)">↑</button><button class="btn btn-sm" onclick="nleArtWeg(' + i + ',' + k + ')">✕</button></span>')
           + '</div>';
    });
    h += '</div>';
    if (!NLE.gesperrt) {
        h += '<label>Artikel suchen (Name oder Artikelnummer)</label><input type="text" class="erp-input" oninput="nleSuche(' + i + ', this)">'
           + '<div class="nle-treffer" id="nle-treffer-' + i + '"></div>';
    }
    return h;
}

let nleSucheTimer = null;
function nleSuche(i, feld) {
    clearTimeout(nleSucheTimer);
    nleSucheTimer = setTimeout(async () => {
        const box = document.getElementById('nle-treffer-' + i);
        if (feld.value.trim().length < 2) { box.style.display = 'none'; return; }
        const d = await (await fetch(nleBasis() + '?aktion=artikel_suche&q=' + encodeURIComponent(feld.value.trim()))).json();
        box.innerHTML = (d.artikel || []).map(a => {
            nleArtikelCache[a.id] = a;
            return '<div onclick="nleArtDazu(' + i + ',' + a.id + ')">' + nleEsc(a.name) + ' <span style="color:#94a3b8">' + nleEsc(a.artikelnummer) + '</span>'
                 + (a.preis != null ? ' · € ' + Number(a.preis).toFixed(2) : '') + '</div>';
        }).join('') || '<div style="color:#94a3b8">Keine Treffer</div>';
        box.style.display = 'block';
    }, 250);
}
function nleArtDazu(i, id) {
    const ids = NLE.bausteine[i].artikel_ids = NLE.bausteine[i].artikel_ids || [];
    if (!ids.includes(id)) ids.push(id);
    nleMarkieren(); nleRendern();
}
function nleArtWeg(i, k) { NLE.bausteine[i].artikel_ids.splice(k, 1); nleMarkieren(); nleRendern(); }
function nleArtSchieben(i, k, d) {
    const ids = NLE.bausteine[i].artikel_ids; const z = k + d;
    if (z < 0 || z >= ids.length) return;
    [ids[k], ids[z]] = [ids[z], ids[k]]; nleMarkieren(); nleRendern();
}
async function nleArtikelNamenLaden() {
    const fehlend = [];
    NLE.bausteine.forEach(b => (b.artikel_ids || []).forEach(id => { if (!nleArtikelCache[id]) fehlend.push(id); }));
    if (!fehlend.length) return;
    const d = await (await fetch(nleBasis() + '?aktion=artikel_info&ids=' + fehlend.join(','))).json();
    Object.assign(nleArtikelCache, d.artikel || {});
    if (Object.keys(d.artikel || {}).length) nleRendern();
}

function nleNeu(typ) { NLE.bausteine.push(Object.assign({ typ: typ }, JSON.parse(JSON.stringify(NLE_LEER[typ])))); nleMarkieren(); nleRendern(); }
function nleLoeschen(i) { if (!confirm('Baustein entfernen?')) return; NLE.bausteine.splice(i, 1); nleMarkieren(); nleRendern(); }
function nleSchieben(i, d) {
    const z = i + d; if (z < 0 || z >= NLE.bausteine.length) return;
    [NLE.bausteine[i], NLE.bausteine[z]] = [NLE.bausteine[z], NLE.bausteine[i]]; nleMarkieren(); nleRendern();
}

async function nleBildHoch(i, input) {
    if (!input.files[0]) return;
    const fd = new FormData(); fd.append('aktion', 'bild'); fd.append('id', NLE.id); fd.append('bild', input.files[0]);
    nleStatus('Bild wird hochgeladen …');
    const d = await (await fetch(nleBasis(), { method: 'POST', body: fd })).json();
    if (!d.erfolg) { alert(d.fehler); nleStatus(''); return; }
    NLE.bausteine[i].bild = d.pfad; nleMarkieren(); nleRendern(); nleSpeichern();
}

async function nleSpeichern() {
    const daten = { id: NLE.id, titel: document.getElementById('nle-titel').value, betreff: document.getElementById('nle-betreff').value,
                    vorschautext: document.getElementById('nle-vorschau').value, bausteine: NLE.bausteine };
    nleStatus('speichere …');
    const d = await (await fetch(nleBasis() + '?aktion=speichern', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) })).json();
    if (!d.erfolg) { nleStatus(d.fehler, '#dc2626'); return false; }
    nleGeaendert = false;
    nleStatus('gespeichert ✓', '#16a34a');
    document.getElementById('nle-vorschau-frame').src = 'vorschau.php?id=' + NLE.id + '&t=' + Date.now();
    return true;
}

async function nleTestmail() {
    const an = document.getElementById('nle-test-an').value.trim();
    if (!an) { alert('Bitte eine E-Mail-Adresse eintragen.'); return; }
    if (nleGeaendert && !NLE.gesperrt && !(await nleSpeichern())) return;
    nleStatus('Testmail wird verschickt …');
    const fd = new FormData(); fd.append('aktion', 'testmail'); fd.append('id', NLE.id); fd.append('an', an);
    const d = await (await fetch(nleBasis(), { method: 'POST', body: fd })).json();
    nleStatus(d.erfolg ? 'Testmail an ' + an + ' verschickt ✓' : d.fehler, d.erfolg ? '#16a34a' : '#dc2626');
}

['nle-titel', 'nle-betreff', 'nle-vorschau'].forEach(id => document.getElementById(id).addEventListener('input', nleMarkieren));
window.addEventListener('beforeunload', e => { if (nleGeaendert) { e.preventDefault(); e.returnValue = ''; } });
nleRendern();
