/* lager/chargen_nachverfolgung.php — Artikelsuche → Chargen-Dropdown → Bewegungshistorie */

let cnSuchTimer  = null;
let cnArtikelId  = null;

const cnSucheInput = document.getElementById('cn-suche');
cnSucheInput.addEventListener('input', () => {
    clearTimeout(cnSuchTimer);
    const val = cnSucheInput.value.trim();
    if (val.length < 2) { cnDropdownVerstecken(); return; }
    cnSuchTimer = setTimeout(() => cnSucheAusfuehren(val), 280);
});
cnSucheInput.addEventListener('blur', () => setTimeout(cnDropdownVerstecken, 200));

function cnDropdownVerstecken() {
    document.getElementById('cn-dropdown').style.display = 'none';
}

async function cnSucheAusfuehren(suche) {
    const res  = await fetch(window.BASE_PATH + '/lager/artikel_suche_ajax.php?q=' + encodeURIComponent(suche));
    const list = await res.json();
    const drop = document.getElementById('cn-dropdown');
    drop.innerHTML = '';
    if (!list.length) {
        drop.innerHTML = '<div style="padding:10px 12px;font-size:13px;color:var(--color-text-muted)">Keine Treffer.</div>';
        drop.style.display = 'block';
        return;
    }
    list.forEach(a => {
        const item = document.createElement('div');
        item.style.cssText = 'padding:8px 12px;cursor:pointer;font-size:13px;border-bottom:1px solid var(--color-border)';
        item.innerHTML = '<strong>' + cnEsc(a.name) + '</strong>'
            + (a.variante_name ? ' <span style="color:var(--color-text-muted)">— ' + cnEsc(a.variante_name) + '</span>' : '')
            + '<br><small style="color:var(--color-text-muted)">' + cnEsc(a.artikelnummer || '') + '</small>';
        item.addEventListener('mousedown', () => cnArtikelWaehlen(a));
        drop.appendChild(item);
    });
    drop.style.display = 'block';
}

async function cnArtikelWaehlen(a) {
    cnArtikelId = a.id;
    cnDropdownVerstecken();
    cnSucheInput.value = a.variante_name ? (a.name + ' — ' + a.variante_name) : a.name;

    document.getElementById('cn-ergebnis').style.display = 'block';
    document.getElementById('cn-artikel-name').textContent = cnSucheInput.value;
    document.getElementById('cn-artikel-nr').textContent   = 'Art.-Nr. ' + (a.artikelnummer || '—');

    const chargenRes = await fetch(window.BASE_PATH + '/lager/chargen_fuer_artikel_ajax.php?artikel_id=' + a.id);
    const chargen     = await chargenRes.json();
    const select = document.getElementById('cn-charge-filter');
    select.innerHTML = '<option value="">Letzte 10 (alle Chargen)</option>'
        + chargen.map(ch => '<option value="' + cnEsc(ch) + '">' + cnEsc(ch) + ' — vollständiger Verlauf</option>').join('');
    select.value = '';
    select.onchange = () => cnBewegungenLaden(select.value);

    cnBewegungenLaden('');
}

function cnBewegungenLaden(charge) {
    const titel  = document.getElementById('cn-bewegungslog-titel');
    const inhalt = document.getElementById('cn-bewegungslog-inhalt');
    titel.textContent = charge ? 'Verlauf Charge ' + charge : 'Letzte Lagerbewegungen';
    inhalt.style.opacity = '0.5';

    const url = window.BASE_PATH + '/lager/bewegungen_ajax.php?artikel_id=' + cnArtikelId
        + (charge ? '&charge=' + encodeURIComponent(charge) : '');

    fetch(url)
        .then(r => r.text())
        .then(html => { inhalt.innerHTML = html; inhalt.style.opacity = '1'; })
        .catch(() => {
            inhalt.innerHTML = '<p style="color:var(--color-danger);font-size:13px">Fehler beim Laden der Bewegungen.</p>';
            inhalt.style.opacity = '1';
        });
}

function cnEsc(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
