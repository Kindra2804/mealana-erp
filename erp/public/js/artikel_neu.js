var _zustandSuffixMap = window.NEU_ZUSTAND_SUFFIX || {};
var vaterArtikelNummer = '';

function zustandGeaendert(wert) {
    var bereich   = document.getElementById('vater_suche_bereich');
    var artnrInput = document.getElementById('artikelnummer');
    if (wert === 'neu') {
        bereich.classList.add('versteckt');
        artnrInput.readOnly = false;
        artnrInput.style.background = '';
        artnrInput.value = '';
        vaterArtikelNummer = '';
    } else {
        bereich.classList.remove('versteckt');
        artnrInput.readOnly = true;
        artnrInput.style.background = 'var(--color-bg)';
        aktualisiereArtnr(wert);
    }
}

function aktualisiereArtnr(zustand) {
    var suffix     = _zustandSuffixMap[zustand] || '';
    var artnrInput = document.getElementById('artikelnummer');
    artnrInput.value = (vaterArtikelNummer && suffix) ? vaterArtikelNummer + '-' + suffix : '';
}

var vaterSuchTimer = null;

function vaterSuchen(q) {
    clearTimeout(vaterSuchTimer);
    var ergebnisDiv = document.getElementById('vater_suche_ergebnis');
    if (q.length < 2) { ergebnisDiv.style.display = 'none'; return; }
    vaterSuchTimer = setTimeout(function () {
        fetch('artikel_vater_suche.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.length) { ergebnisDiv.style.display = 'none'; return; }
                ergebnisDiv.innerHTML = data.map(function (a) {
                    return '<div style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:13px"'
                        + ' onmousedown="vaterAuswaehlen(' + a.id + ',\'' + a.artikelnummer.replace(/'/g, "\\'") + '\',\'' + a.name.replace(/'/g, "\\'") + '\')">'
                        + '<strong>' + a.artikelnummer + '</strong> – ' + a.name + '</div>';
                }).join('');
                ergebnisDiv.style.display = 'block';
            });
    }, 250);
}

function vaterAuswaehlen(id, artnr, name) {
    document.getElementById('zustand_vater_id').value    = id;
    document.getElementById('vater_suche_input').value   = artnr + ' – ' + name;
    document.getElementById('vater_info').innerHTML      = 'Vater: <strong>' + artnr + '</strong> – ' + name;
    document.getElementById('vater_suche_ergebnis').style.display = 'none';
    vaterArtikelNummer = artnr;
    aktualisiereArtnr(document.getElementById('zustand_select').value);
}

function toggleLieferantSektion() {
    var body = document.getElementById('lieferant-bereich');
    var icon = document.getElementById('lf-toggle-icon');
    var oeffnen = body.classList.contains('versteckt');
    body.classList.toggle('versteckt', !oeffnen);
    icon.textContent = oeffnen ? '▼' : '▶';
}

function berechneEkBrutto() {
    var netto     = parseFloat(document.getElementById('lf_ek_netto').value) || 0;
    var steuerSel = document.getElementById('steuerklasse_id');
    var satz      = parseFloat(steuerSel && steuerSel.selectedOptions[0] ? steuerSel.selectedOptions[0].dataset.satz : 0) || 0;
    var brutto    = netto * (1 + satz / 100);
    document.getElementById('lf_ek_brutto').value = brutto > 0 ? brutto.toFixed(2) : '';
}

// Init (uses window.* vars set inline by PHP)
(function () {
    var initZustand    = window.NEU_INIT_ZUSTAND || 'neu';
    var gespeicherterTyp = window.NEU_INIT_TYP || '';

    if (initZustand !== 'neu') zustandGeaendert(initZustand);
    if (gespeicherterTyp) zeigeFelder(gespeicherterTyp);

    document.getElementById('artikeltyp').addEventListener('change', function (e) { zeigeFelder(e.target.value); });
    document.getElementById('brutto_vk').addEventListener('input', function () { berechneNetto(); berechneGrundpreis(); });
    document.getElementById('steuerklasse_id').addEventListener('change', function () { berechneNetto(); berechneEkBrutto(); });
    var gpBezug = document.querySelector('[name="grundpreis_bezugsmenge"]');
    if (gpBezug) gpBezug.addEventListener('input', berechneGrundpreis);
    var iMenge = document.querySelector('[name="inhalt_menge"]');
    if (iMenge) iMenge.addEventListener('input', berechneGrundpreis);
    var iEinh = document.querySelector('[name="inhalt_einheit"]');
    if (iEinh) iEinh.addEventListener('input', berechneGrundpreis);

    berechneNetto();
    berechneGrundpreis();

    var eanInput = document.getElementById('ean_gtin13');
    if (eanInput) {
        eanInput.addEventListener('blur', function () {
            var ean   = this.value.trim();
            var badge = document.getElementById('ean-warn');
            badge.style.display = 'none';
            if (ean.length < 8) return;
            fetch('ean_check.php?ean=' + encodeURIComponent(ean))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.gefunden) {
                        badge.title = 'EAN bereits in Verwendung: ' + data.artikelnummer + ' – ' + data.name;
                        badge.style.display = 'inline-flex';
                    }
                });
        });
    }
})();
