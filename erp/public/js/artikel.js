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
