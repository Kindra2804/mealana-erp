// Zustand-Artikel Bearbeiten (nur aktiv wenn BEARB_ZUSTAND_SUFFIX gesetzt)
function zustandBearbeitenGeaendert(wert) {
    var suffix = (window.BEARB_ZUSTAND_SUFFIX || {})[wert] || '';
    var artnrInput = document.getElementById('artikelnummer');
    if (suffix) artnrInput.value = (window.BEARB_VATER_ARTNR || '') + '-' + suffix;
}

// Init
(function () {
    var gespeicherterTyp = window.BEARB_INIT_TYP || '';
    if (gespeicherterTyp) zeigeFelder(gespeicherterTyp);

    document.getElementById('artikeltyp').addEventListener('change', function () { zeigeFelder(this.value); });

    var bruttoInput = document.getElementById('brutto_vk');
    if (bruttoInput) bruttoInput.addEventListener('input', function () { berechneNetto(); berechneGrundpreis(); });

    var steuerkl = document.querySelector('[name="steuerklasse_id"]');
    if (steuerkl) steuerkl.addEventListener('change', berechneNetto);

    var gpBezug = document.querySelector('[name="grundpreis_bezugsmenge"]');
    if (gpBezug) gpBezug.addEventListener('input', berechneGrundpreis);

    var iMenge = document.querySelector('[name="inhalt_menge"]');
    if (iMenge) iMenge.addEventListener('input', berechneGrundpreis);

    var iEinh = document.querySelector('[name="inhalt_einheit"]');
    if (iEinh) iEinh.addEventListener('input', berechneGrundpreis);

    berechneNetto();
    berechneGrundpreis();
})();

// TinyMCE init
tinymce.init({
    selector: '#beschreibung',
    menubar: false,
    plugins: 'lists link',
    toolbar: 'bold italic underline | bullist numlist | link | removeformat',
    height: 300,
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
});

tinymce.init({
    selector: '#kurzbeschreibung',
    menubar: false,
    plugins: '',
    toolbar: 'bold italic underline | removeformat',
    height: 120,
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }'
});

