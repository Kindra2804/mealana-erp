function zeigeFelder(typ) {
    const physisch = document.getElementById('felder-physisch');
    const grundpreis = document.getElementById('grundpreis_container');

    physisch.classList.add('versteckt');
    grundpreis.classList.add('versteckt');

    if (['GARN', 'NADEL', 'METERWARE'].includes(typ)) {
        physisch.classList.remove('versteckt');
    }
    if (typ === 'GARN' || typ === 'METERWARE') {
        grundpreis.classList.remove('versteckt');
    }

    const label = document.getElementById('bezugsmenge_label');
    const bezugInput = document.querySelector('[name="grundpreis_bezugsmenge"]');
    if (typ === 'METERWARE') {
        label.textContent = 'Grundpreis Bezugsmenge (m)';
        if (!bezugInput.value) bezugInput.value = 1;
    } else if (typ === 'GARN') {
        label.textContent = 'Grundpreis Bezugsmenge (g)';
        if (!bezugInput.value) bezugInput.value = 100;
    }

    berechneGrundpreis();
}

function berechneNetto() {
    const brutto = parseFloat(document.getElementById('brutto_vk').value) || 0;
    const steuerSelect = document.querySelector('[name="steuerklasse_id"]');
    const satz = parseFloat(
        steuerSelect.options[steuerSelect.selectedIndex].dataset.satz
    ) || 20;

    if (brutto > 0) {
        document.getElementById('netto_vk').value =
            (brutto / (1 + satz / 100)).toFixed(4);
    }
}

function berechneGrundpreis() {
    const brutto = parseFloat(document.getElementById('brutto_vk').value) || 0;
    let menge = parseFloat(document.querySelector('[name="inhalt_menge"]')?.value) || 0;
    const einheit = document.querySelector('[name="inhalt_einheit"]')?.value.toLowerCase().trim();
    const bezug = parseFloat(document.querySelector('[name="grundpreis_bezugsmenge"]')?.value) || 100;

    if (einheit === 'kg') menge = menge * 1000;
    if (einheit === 'l') menge = menge * 1000;
    if (einheit === 'm') menge = menge * 100;

    if (brutto > 0 && menge > 0) {
        const grundpreis = (brutto / menge) * bezug;
        const einheitLabel = ['m', 'cm'].includes(einheit) ? 'm' : 'g';
        document.getElementById('grundpreis_anzeige').textContent =
            grundpreis.toFixed(2) + '€ / ' + bezug + einheitLabel;
    } else {
        document.getElementById('grundpreis_anzeige').textContent =
            '– wird berechnet –';
    }
}

function oeffnePreistabelle() {
    alert('Preistabelle kommt bald!');
}

// ── Hersteller Schnell-Dialog (neu.php + bearbeiten.php) ─────────────────────
function herstellerSchnellOeffnen() {
    document.getElementById('hs-modal').style.display = 'flex';
    document.getElementById('hs-name').focus();
}
function herstellerSchnellSchliessen() {
    document.getElementById('hs-modal').style.display = 'none';
    document.getElementById('hs-name').value          = '';
    document.getElementById('hs-land').value          = '';
    document.getElementById('hs-fehler').textContent  = '';
}
function herstellerSchnellSpeichern() {
    var name = document.getElementById('hs-name').value.trim();
    var land = document.getElementById('hs-land').value.trim().toUpperCase();
    if (!name) { document.getElementById('hs-fehler').textContent = 'Name ist Pflichtfeld'; return; }
    document.getElementById('hs-fehler').textContent = '';
    var fd = new FormData();
    fd.append('name', name);
    fd.append('land', land);
    fetch(window.BASE_PATH + '/hersteller/schnell_speichern.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) {
                var sel = document.getElementById('hersteller_id');
                sel.add(new Option(d.name, d.id, true, true));
                herstellerSchnellSchliessen();
            } else {
                document.getElementById('hs-fehler').textContent =
                    Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Fehler');
            }
        });
}

// ── Kategorie-Modal (Formular: bearbeiten.php / neu.php) ─────────────────────
function katModalSchliessen() {
    document.getElementById('kat-backdrop').style.display = 'none';
}
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
        span.className = 'chip';
        span.textContent = cb.dataset.name;
        chips.appendChild(span);
    });
    katModalSchliessen();
}
async function katAnlegen() {
    const katName  = document.getElementById('neue-kat-name').value?.trim();
    const parentId = document.getElementById('neue-kat-parent')?.value || '';
    if (!katName) return;
    const body     = 'name=' + encodeURIComponent(katName) + (parentId ? '&parent_id=' + encodeURIComponent(parentId) : '');
    const response = await fetch('kategorie_neu.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body
    });
    const data = await response.json();
    if (!data.erfolg) { alert(data.fehler); return; }
    const label = document.createElement('label');
    const cb    = document.createElement('input');
    cb.type = 'checkbox'; cb.value = data.id; cb.dataset.name = data.name; cb.checked = true;
    label.appendChild(cb);
    label.appendChild(document.createTextNode(' ' + data.name));
    document.getElementById('kat-checkboxen').appendChild(label);
    if (document.getElementById('neue-kat-name')) document.getElementById('neue-kat-name').value = '';
}
