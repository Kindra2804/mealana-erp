/* auftraege/neu.php — Positionen-Verwaltung + Artikel-Typeahead + Kunden-Suche */

let positionenIndex = 0;

function positionHinzufuegen(artikel) {
    const body = document.getElementById('positionen-body');
    const idx = positionenIndex++;
    const a = artikel || {};

    const isBrutto = window.PREISANZEIGE !== 'netto';
    const stPrzA   = parseFloat(a.steuer_prozent) || 20;
    let preisWert;
    if (a.einzelpreis_netto != null) {
        preisWert = isBrutto
            ? (parseFloat(a.einzelpreis_netto) * (1 + stPrzA / 100)).toFixed(2)
            : a.einzelpreis_netto;
    } else if (a.vk_brutto) {
        preisWert = isBrutto
            ? parseFloat(a.vk_brutto).toFixed(2)
            : (parseFloat(a.vk_brutto) / (1 + stPrzA / 100)).toFixed(4);
    } else {
        preisWert = '';
    }

    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td style="position:relative">
            <input type="hidden" name="positionen[${idx}][artikel_id]" class="pos-artikel-id" value="${a.artikel_id || a.id || ''}">
            <input type="hidden" name="positionen[${idx}][ean]"        value="${escH(a.ean || '')}">
            <div style="display:flex;gap:4px">
                <input type="text" name="positionen[${idx}][bezeichnung]" class="erp-input pos-bezeichnung"
                       value="${escH(a.bezeichnung || a.name || (a.variante_name ? a.variante_name : ''))}"
                       placeholder="Artikel suchen…" autocomplete="off"
                       data-idx="${idx}" style="flex:1;min-width:0">
                <button type="button" title="Artikel auswählen"
                        onclick="openArtikelBrowser(${idx})"
                        style="flex-shrink:0;padding:4px 8px;border:1px solid var(--color-border);border-radius:4px;background:var(--color-bg-secondary);cursor:pointer;font-size:14px">📋</button>
            </div>
            <div class="pos-dropdown" data-idx="${idx}" style="position:absolute;z-index:200;display:none;background:#fff;border:1px solid var(--color-border);border-radius:4px;min-width:320px"></div>
            <input type="hidden" name="positionen[${idx}][steuer_prozent]" class="pos-steuer" value="${a.steuer_prozent || 20}">
        </td>
        <td><input type="number" name="positionen[${idx}][menge]" class="erp-input pos-menge" min="1" value="${a.menge || 1}" style="width:60px" oninput="aktualisiereZeile(${idx})"></td>
        <td>
            <input type="number" name="positionen[${idx}][einzelpreis_netto]" class="erp-input pos-preis" step="0.0001" value="${escH(preisWert)}" style="width:90px" oninput="aktualisiereZeile(${idx})">
            ${window.PREISANZEIGE === 'beides' ? '<div class="pos-netto-hint" style="font-size:11px;color:var(--color-text-muted);margin-top:2px">Netto: —</div>' : ''}
        </td>
        <td><input type="number" name="positionen[${idx}][steuer_prozent_anzeige]" class="erp-input" step="0.01" value="${a.steuer_prozent || 20}" style="width:60px" oninput="aktualisiereZeile(${idx})" readonly></td>
        <td><input type="number" name="positionen[${idx}][rabatt_prozent]" class="erp-input pos-rabatt" step="0.01" min="0" max="100" value="${a.rabatt_prozent || 0}" style="width:60px" oninput="aktualisiereZeile(${idx})"></td>
        <td class="pos-gesamt" style="text-align:right;font-weight:600">0,00 €</td>
        <td><button type="button" onclick="positionEntfernen(this)" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:16px">✕</button></td>
    `;
    body.appendChild(tr);

    const bezeichnungInput = tr.querySelector('.pos-bezeichnung');
    bezeichnungInput.addEventListener('input', () => startArtikelSuche(idx, bezeichnungInput));
    bezeichnungInput.addEventListener('blur', () => setTimeout(() => versteckeDropdown(idx), 200));

    // Enter im Artikel-Feld darf NIE den ganzen Auftrag absenden (Formular hat einen
    // Submit-Button) — bei genau einem Treffer oder exakter Artikelnummer/EAN-Übereinstimmung
    // wird der stattdessen direkt übernommen, sonst passiert einfach nichts.
    bezeichnungInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const drop = document.querySelector(`.pos-dropdown[data-idx="${idx}"]`);
        const ergebnisse = (drop && drop.style.display === 'block' && drop._ergebnisse) || [];
        if (ergebnisse.length === 1) { artikelWaehlen(idx, ergebnisse[0]); return; }
        const wert = bezeichnungInput.value.trim().toLowerCase();
        const exakt = ergebnisse.find(a =>
            (a.artikelnummer || '').toLowerCase() === wert || (a.ean || '').toLowerCase() === wert
        );
        if (exakt) artikelWaehlen(idx, exakt);
    });

    // Telefon-Bestellungen: nach Eingabe der Menge direkt mit Enter zur nächsten Zeile
    // springen, ohne Maus/Scrollen zurück zum "+ Position"-Button oben.
    const mengeInput = tr.querySelector('.pos-menge');
    mengeInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        springeZurNaechstenZeile(idx);
    });

    if (!a.artikel_id && !a.id) bezeichnungInput.focus();

    aktualisiereZeile(idx);
    aktualisiereAnzeige();
}

// Springt von einer Zeile (nach Menge-Eingabe) zur nächsten — legt automatisch eine
// neue leere Zeile an, falls es noch keine gibt (z.B. wenn die aktuelle die letzte war).
function springeZurNaechstenZeile(idx) {
    const body = document.getElementById('positionen-body');
    const tr = document.querySelector(`tr[data-idx="${idx}"]`);
    if (!tr) return;
    if (tr === body.lastElementChild) {
        positionHinzufuegen();
        return;
    }
    const naechste = tr.nextElementSibling;
    const naechstesFeld = naechste ? naechste.querySelector('.pos-bezeichnung') : null;
    if (naechstesFeld) naechstesFeld.focus();
}

function positionEntfernen(btn) {
    btn.closest('tr').remove();
    aktualisiereAnzeige();
}

function aktualisiereZeile(idx) {
    const tr = document.querySelector(`tr[data-idx="${idx}"]`);
    if (!tr) return;
    const menge   = parseFloat(tr.querySelector('.pos-menge').value) || 0;
    const preis   = parseFloat(tr.querySelector('.pos-preis').value) || 0;
    const rabatt  = parseFloat(tr.querySelector('.pos-rabatt').value) || 0;
    const stProz  = parseFloat(tr.querySelector('.pos-steuer').value) || 20;
    const netto   = window.PREISANZEIGE !== 'netto' ? preis / (1 + stProz / 100) : preis;
    const gesamt  = menge * netto * (1 - rabatt / 100);
    const anzeige = window.PREISANZEIGE !== 'netto' ? gesamt * (1 + stProz / 100) : gesamt;
    tr.querySelector('.pos-gesamt').textContent = fmtEur(anzeige);
    const hint = tr.querySelector('.pos-netto-hint');
    if (hint) hint.textContent = 'Netto: ' + fmtEur(netto * (1 - rabatt / 100));
    aktualisiereAnzeige();
}

function aktualisiereAnzeige() {
    const keineEl = document.getElementById('keine-positionen');
    const rows = document.querySelectorAll('#positionen-body tr');
    keineEl.style.display = rows.length === 0 ? '' : 'none';

    let netto = 0;
    let steuer = 0;
    rows.forEach(tr => {
        const menge      = parseFloat(tr.querySelector('.pos-menge')?.value) || 0;
        const preisInput = parseFloat(tr.querySelector('.pos-preis')?.value) || 0;
        const rabatt     = parseFloat(tr.querySelector('.pos-rabatt')?.value) || 0;
        const stProz     = parseFloat(tr.querySelector('.pos-steuer')?.value) || 20;
        const preisNetto = window.PREISANZEIGE !== 'netto' ? preisInput / (1 + stProz / 100) : preisInput;
        const n = menge * preisNetto * (1 - rabatt / 100);
        netto  += n;
        steuer += n * stProz / 100;
    });
    document.getElementById('summe-netto').textContent = fmtEur(netto);
    document.getElementById('summe-steuer').textContent = fmtEur(steuer);
    document.getElementById('summe-brutto').textContent = fmtEur(netto + steuer);
}

// Artikel-Typeahead

let artikelTimer = null;
function startArtikelSuche(idx, input) {
    clearTimeout(artikelTimer);
    const val = input.value.trim();
    if (val.length < 2) { versteckeDropdown(idx); return; }
    artikelTimer = setTimeout(() => sucheArtikel(idx, val), 280);
}

async function sucheArtikel(idx, suche) {
    const res = await fetch(`${window.ARTIKEL_AJAX_URL}?q=${encodeURIComponent(suche)}`);
    const list = await res.json();
    const drop = document.querySelector(`.pos-dropdown[data-idx="${idx}"]`);
    if (!drop) return;
    drop._ergebnisse = list; // für Enter-Auswahl im Bezeichnungsfeld gemerkt
    drop.innerHTML = '';
    if (!list.length) { drop.style.display = 'none'; return; }
    list.forEach(a => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-border)';
        item.innerHTML = `<strong>${escH(a.name)}</strong>${a.variante_name ? ' <span style="color:var(--color-text-muted)">— ' + escH(a.variante_name) + '</span>' : ''}<br><small style="color:var(--color-text-muted)">${escH(a.artikelnummer || '')} ${a.ean ? '| EAN: ' + escH(a.ean) : ''}</small>`;
        item.addEventListener('mousedown', () => artikelWaehlen(idx, a));
        drop.appendChild(item);
    });
    drop.style.display = 'block';
}

function artikelWaehlen(idx, a) {
    const tr = document.querySelector(`tr[data-idx="${idx}"]`);
    if (!tr) return;
    tr.querySelector('.pos-artikel-id').value = a.id;
    tr.querySelector('[name$="[ean]"]').value = a.ean || '';
    const bez = a.variante_name ? (a.name + ' — ' + a.variante_name) : a.name;
    tr.querySelector('.pos-bezeichnung').value = bez;
    tr.querySelector('.pos-steuer').value = a.steuer_prozent || 20;
    if (a.vk_brutto) {
        const stPrz  = a.steuer_prozent || 20;
        const preis  = window.PREISANZEIGE !== 'netto'
            ? parseFloat(a.vk_brutto).toFixed(2)
            : (parseFloat(a.vk_brutto) / (1 + stPrz / 100)).toFixed(4);
        tr.querySelector('.pos-preis').value = preis;
    }
    versteckeDropdown(idx);
    aktualisiereZeile(idx);

    // War das die letzte Zeile, gleich eine neue leere darunter anlegen (still, ohne
    // Fokus zu stehlen) — Menge dieser Zeile bekommt jetzt den Fokus zum Weitertippen,
    // Enter in der Menge springt dann in die bereits vorbereitete nächste Zeile.
    const body = document.getElementById('positionen-body');
    if (tr === body.lastElementChild) positionHinzufuegen();
    const mengeInput = tr.querySelector('.pos-menge');
    mengeInput.focus();
    mengeInput.select();
}

function versteckeDropdown(idx) {
    const drop = document.querySelector(`.pos-dropdown[data-idx="${idx}"]`);
    if (drop) drop.style.display = 'none';
}

// Kunden-Typeahead

let kundenTimer = null;
const kundenSuche = document.getElementById('kunden-suche');
if (kundenSuche) {
    kundenSuche.addEventListener('input', () => {
        clearTimeout(kundenTimer);
        const val = kundenSuche.value.trim();
        if (val.length === 0) {
            // Suchfeld geleert → Laufkunde, alle Adressfelder leeren
            document.getElementById('kunden-id').value = '';
            leereAdresse('rechnungsadresse');
            leereAdresse('lieferadresse');
            document.getElementById('kunden-dropdown').style.display = 'none';
            return;
        }
        if (val.length < 2) { document.getElementById('kunden-dropdown').style.display = 'none'; return; }
        kundenTimer = setTimeout(() => sucheKunden(val), 300);
    });
    kundenSuche.addEventListener('blur', () => setTimeout(() => {
        document.getElementById('kunden-dropdown').style.display = 'none';
    }, 200));
}

async function sucheKunden(suche) {
    const res = await fetch(`${window.KUNDEN_AJAX_URL}?q=${encodeURIComponent(suche)}`);
    const list = await res.json();
    const drop = document.getElementById('kunden-dropdown');
    drop.innerHTML = '';
    if (!list.length) {
        drop.innerHTML = '<div style="padding:10px 12px;font-size:13px;color:var(--color-text-muted)">Kein Kunde gefunden.</div>';
        drop.style.display = 'block';
        return;
    }
    list.forEach(k => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--color-border);transition:background .1s';
        item.onmouseenter = () => item.style.background = 'var(--color-bg-secondary)';
        item.onmouseleave = () => item.style.background = '';
        const ra = k.rechnungsadresse;
        const adrZeile = ra ? [ra.strasse + ' ' + ra.hausnummer, ra.plz + ' ' + ra.ort].filter(Boolean).join(', ') : '';
        item.innerHTML = `<div style="font-weight:600;font-size:13px">${escH(k.name)}</div>`
            + (k.email ? `<div style="font-size:11px;color:var(--color-text-muted)">${escH(k.email)}</div>` : '')
            + (adrZeile ? `<div style="font-size:11px;color:var(--color-text-muted)">${escH(adrZeile.trim())}</div>` : '');
        item.addEventListener('mousedown', () => {
            document.getElementById('kunden-id').value = k.id;
            kundenSuche.value = k.name;
            drop.style.display = 'none';
            if (k.rechnungsadresse) populiereAdresse('rechnungsadresse', k.rechnungsadresse);
            if (k.lieferadresse)    populiereAdresse('lieferadresse',    k.lieferadresse);
        });
        drop.appendChild(item);
    });
    drop.style.display = 'block';
}

// Adressen befüllen / leeren
function populiereAdresse(prefix, a) {
    ['vorname','nachname','firma','strasse','hausnummer','plz','ort','land','zusatz'].forEach(f => {
        const el = document.getElementById(prefix + '_' + f);
        if (el) el.value = a[f] || '';
    });
}
function leereAdresse(prefix) {
    ['vorname','nachname','firma','strasse','hausnummer','plz','ort','land','zusatz'].forEach(f => {
        const el = document.getElementById(prefix + '_' + f);
        if (el) el.value = '';
    });
}

// Hilfsfunktionen

// Artikel-Browser Modal
let browserIdx = null;
let browserTimer = null;

function openArtikelBrowser(idx) {
    browserIdx = idx;
    const modal = document.getElementById('artikel-browser-modal');
    modal.style.display = 'flex';
    const input = document.getElementById('browser-suche');
    input.value = '';
    document.getElementById('browser-ergebnisse').innerHTML = '<div style="color:var(--color-text-muted);padding:12px">Suchbegriff eingeben…</div>';
    input.focus();
}

function closeArtikelBrowser() {
    document.getElementById('artikel-browser-modal').style.display = 'none';
    browserIdx = null;
}

function browserSuche() {
    clearTimeout(browserTimer);
    const val = document.getElementById('browser-suche').value.trim();
    const box = document.getElementById('browser-ergebnisse');
    if (val.length < 2) { box.innerHTML = '<div style="color:var(--color-text-muted);padding:12px">Mindestens 2 Zeichen eingeben…</div>'; return; }
    box.innerHTML = '<div style="color:var(--color-text-muted);padding:12px">Suche…</div>';
    browserTimer = setTimeout(async () => {
        const res  = await fetch(`${window.ARTIKEL_AJAX_URL}?q=${encodeURIComponent(val)}`);
        const list = await res.json();
        if (!list.length) { box.innerHTML = '<div style="padding:12px;color:var(--color-text-muted)">Keine Treffer</div>'; return; }
        box.innerHTML = '';
        list.forEach(a => {
            const div = document.createElement('div');
            div.style.cssText = 'padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--color-border);transition:background .1s';
            div.onmouseenter = () => div.style.background = 'var(--color-bg-secondary)';
            div.onmouseleave = () => div.style.background = '';
            const bez = a.variante_name ? escH(a.name) + ' <span style="color:var(--color-text-muted)">— ' + escH(a.variante_name) + '</span>' : escH(a.name);
            div.innerHTML = `<div style="font-weight:600">${bez}</div><div style="font-size:12px;color:var(--color-text-muted)">${escH(a.artikelnummer || '')}${a.ean ? ' · EAN ' + escH(a.ean) : ''}${a.vk_brutto ? ' · ' + parseFloat(a.vk_brutto).toFixed(2).replace('.',',') + ' €' : ''}</div>`;
            div.addEventListener('click', () => { artikelWaehlen(browserIdx, a); closeArtikelBrowser(); });
            box.appendChild(div);
        });
    }, 250);
}

function fmtEur(val) {
    return val.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}
function escH(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Start: eine leere Position anzeigen
if (window.POSITIONEN && window.POSITIONEN.length > 0) {
    window.POSITIONEN.forEach(pos => positionHinzufuegen(pos));
} else {
    positionHinzufuegen();
}


document.getElementById('versandklasse').addEventListener('change', function () {
    var preis = this.value === '' ? '0.00' : this.options[this.selectedIndex].dataset.preis;
    document.getElementById('versandkosten-wert').value = preis;
})

document.getElementById('lieferart').addEventListener('change', function () {
    if (this.value === 'abholung') {
        document.getElementById('gruppe-versandart').style.display = 'none';
        document.getElementById('gruppe-versandkosten').style.display = 'none';
        document.getElementById('versandklasse').value = '';
        document.getElementById('versandkosten-wert').value = 0.00;
    }

    if (this.value === 'versand') {
        document.getElementById('gruppe-versandart').style.display = '';
        document.getElementById('gruppe-versandkosten').style.display = '';
    }

})

document.getElementById('lieferart').dispatchEvent(new Event('change'));

// Brutto→Netto Konvertierung vor dem Absenden
document.getElementById('auftrag-form').addEventListener('submit', function () {
    if (window.PREISANZEIGE !== 'netto') {
        document.querySelectorAll('.pos-preis').forEach(input => {
            const tr     = input.closest('tr');
            const stProz = parseFloat(tr.querySelector('.pos-steuer').value) || 20;
            input.value  = (parseFloat(input.value) / (1 + stProz / 100)).toFixed(4);
        });
    }
});

