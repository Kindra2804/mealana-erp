/* auftraege/neu.php — Positionen-Verwaltung + Artikel-Typeahead + Kunden-Suche */

let positionenIndex = 0;

function positionHinzufuegen(artikel) {
    const body = document.getElementById('positionen-body');
    const idx  = positionenIndex++;
    const a    = artikel || {};

    const vkNetto = a.vk_brutto
        ? (parseFloat(a.vk_brutto) / (1 + (a.steuer_prozent || 20) / 100)).toFixed(4)
        : '';

    const tr = document.createElement('tr');
    tr.dataset.idx = idx;
    tr.innerHTML = `
        <td>
            <input type="hidden" name="positionen[${idx}][artikel_id]" class="pos-artikel-id" value="${a.id || ''}">
            <input type="hidden" name="positionen[${idx}][ean]"        value="${escH(a.ean || '')}">
            <input type="text"   name="positionen[${idx}][bezeichnung]" class="erp-input pos-bezeichnung"
                   value="${escH(a.name || (a.variante_name ? a.variante_name : ''))}"
                   placeholder="Artikel suchen…" autocomplete="off"
                   data-idx="${idx}" style="width:100%">
            <div class="pos-dropdown" data-idx="${idx}" style="position:absolute;z-index:200;display:none;background:#fff;border:1px solid var(--color-border);border-radius:4px;min-width:320px"></div>
            <input type="hidden" name="positionen[${idx}][steuer_prozent]" class="pos-steuer" value="${a.steuer_prozent || 20}">
        </td>
        <td><input type="number" name="positionen[${idx}][menge]" class="erp-input pos-menge" min="1" value="1" style="width:60px" oninput="aktualisiereZeile(${idx})"></td>
        <td><input type="number" name="positionen[${idx}][einzelpreis_netto]" class="erp-input pos-preis" step="0.0001" value="${escH(vkNetto)}" style="width:90px" oninput="aktualisiereZeile(${idx})"></td>
        <td><input type="number" name="positionen[${idx}][steuer_prozent_anzeige]" class="erp-input" step="0.01" value="${a.steuer_prozent || 20}" style="width:60px" oninput="aktualisiereZeile(${idx})" readonly></td>
        <td><input type="number" name="positionen[${idx}][rabatt_prozent]" class="erp-input pos-rabatt" step="0.01" min="0" max="100" value="0" style="width:60px" oninput="aktualisiereZeile(${idx})"></td>
        <td class="pos-gesamt" style="text-align:right;font-weight:600">0,00 €</td>
        <td><button type="button" onclick="positionEntfernen(this)" style="background:none;border:none;color:var(--color-danger);cursor:pointer;font-size:16px">✕</button></td>
    `;
    body.appendChild(tr);

    const bezeichnungInput = tr.querySelector('.pos-bezeichnung');
    bezeichnungInput.addEventListener('input', () => startArtikelSuche(idx, bezeichnungInput));
    bezeichnungInput.addEventListener('blur', () => setTimeout(() => versteckeDropdown(idx), 200));

    if (!a.id) bezeichnungInput.focus();

    aktualisiereZeile(idx);
    aktualisiereAnzeige();
}

function positionEntfernen(btn) {
    btn.closest('tr').remove();
    aktualisiereAnzeige();
}

function aktualisiereZeile(idx) {
    const tr     = document.querySelector(`tr[data-idx="${idx}"]`);
    if (!tr) return;
    const menge  = parseFloat(tr.querySelector('.pos-menge').value) || 0;
    const preis  = parseFloat(tr.querySelector('.pos-preis').value) || 0;
    const rabatt = parseFloat(tr.querySelector('.pos-rabatt').value) || 0;
    const gesamt = menge * preis * (1 - rabatt / 100);
    tr.querySelector('.pos-gesamt').textContent = fmtEur(gesamt);
    aktualisiereAnzeige();
}

function aktualisiereAnzeige() {
    const keineEl = document.getElementById('keine-positionen');
    const rows    = document.querySelectorAll('#positionen-body tr');
    keineEl.style.display = rows.length === 0 ? '' : 'none';

    let netto  = 0;
    let steuer = 0;
    rows.forEach(tr => {
        const menge  = parseFloat(tr.querySelector('.pos-menge')?.value) || 0;
        const preis  = parseFloat(tr.querySelector('.pos-preis')?.value) || 0;
        const rabatt = parseFloat(tr.querySelector('.pos-rabatt')?.value) || 0;
        const stProz = parseFloat(tr.querySelector('.pos-steuer')?.value) || 20;
        const n      = menge * preis * (1 - rabatt / 100);
        netto  += n;
        steuer += n * stProz / 100;
    });
    document.getElementById('summe-netto').textContent  = fmtEur(netto);
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
    const res  = await fetch(`${window.ARTIKEL_AJAX_URL}?q=${encodeURIComponent(suche)}`);
    const list = await res.json();
    const drop = document.querySelector(`.pos-dropdown[data-idx="${idx}"]`);
    if (!drop) return;
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
    tr.querySelector('.pos-artikel-id').value  = a.id;
    tr.querySelector('[name$="[ean]"]').value   = a.ean || '';
    const bez = a.variante_name ? (a.name + ' — ' + a.variante_name) : a.name;
    tr.querySelector('.pos-bezeichnung').value  = bez;
    tr.querySelector('.pos-steuer').value       = a.steuer_prozent || 20;
    if (a.vk_brutto) {
        const stPrz = a.steuer_prozent || 20;
        const netto = (parseFloat(a.vk_brutto) / (1 + stPrz / 100)).toFixed(4);
        tr.querySelector('.pos-preis').value = netto;
    }
    versteckeDropdown(idx);
    aktualisiereZeile(idx);
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
        if (val.length < 2) { document.getElementById('kunden-dropdown').style.display = 'none'; return; }
        kundenTimer = setTimeout(() => sucheKunden(val), 300);
    });
    kundenSuche.addEventListener('blur', () => setTimeout(() => {
        document.getElementById('kunden-dropdown').style.display = 'none';
    }, 200));
}

async function sucheKunden(suche) {
    const res  = await fetch(`${window.KUNDEN_AJAX_URL}?q=${encodeURIComponent(suche)}`);
    const list = await res.json();
    const drop = document.getElementById('kunden-dropdown');
    drop.innerHTML = '';
    if (!list.length) { drop.style.display = 'none'; return; }
    list.forEach(k => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-border)';
        item.innerHTML = `<strong>${escH(k.name)}</strong> <small style="color:var(--color-text-muted)">${escH(k.email || '')}</small>`;
        item.addEventListener('mousedown', () => {
            document.getElementById('kunden-id').value = k.id;
            kundenSuche.value = k.name;
            drop.style.display = 'none';
        });
        drop.appendChild(item);
    });
    drop.style.display = 'block';
}

// Hilfsfunktionen

function fmtEur(val) {
    return val.toLocaleString('de-AT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}
function escH(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Start: eine leere Position anzeigen
positionHinzufuegen();
