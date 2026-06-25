document.addEventListener('DOMContentLoaded', function () {
    const vollstorno = document.getElementById('gs_vollstorno');
    const teilBlock  = document.getElementById('gs-positionen');
    const checkboxes = document.querySelectorAll('.gs-checkbox');
    const mengenFelder = document.querySelectorAll('.gs-menge');

    function toggleModus() {
        if (vollstorno.checked) {
            teilBlock.style.opacity = '0.5';
            teilBlock.style.pointerEvents = 'none';
        } else {
            teilBlock.style.opacity = '1';
            teilBlock.style.pointerEvents = '';
        }
        berechneGesamt();
    }

    function berechneBetrag(idx) {
        const mengeInput = document.querySelector('.gs-menge[data-idx="' + idx + '"]');
        const checkbox   = document.querySelector('.gs-checkbox[data-idx="' + idx + '"]');
        const zelle      = document.getElementById('gs-betrag-' + idx);
        if (!mengeInput || !checkbox || !zelle) return 0;

        if (!checkbox.checked) {
            zelle.textContent = '—';
            return 0;
        }
        const menge       = parseInt(mengeInput.value) || 0;
        const einzelBrutto = parseFloat(mengeInput.dataset.einzelbrutto) || 0;
        const rabatt       = parseFloat(mengeInput.dataset.rabatt) || 0;
        const betrag       = menge * einzelBrutto * (1 - rabatt / 100);
        zelle.textContent  = betrag.toFixed(2).replace('.', ',');
        return betrag;
    }

    function berechneGesamt() {
        if (vollstorno.checked) {
            // Gesamtbetrag aus Original-Rechnung
            const el = document.getElementById('gs-gesamt');
            const span = document.querySelector('[data-rechnung-brutto]');
            el.textContent = span ? span.dataset.rechnungBrutto : '—';
            return;
        }
        let gesamt = 0;
        document.querySelectorAll('.gs-menge').forEach(function (inp) {
            gesamt += berechneBetrag(inp.dataset.idx);
        });
        document.getElementById('gs-gesamt').textContent =
            gesamt.toFixed(2).replace('.', ',');
    }

    vollstorno.addEventListener('change', toggleModus);
    document.getElementById('gs_teil').addEventListener('change', toggleModus);
    checkboxes.forEach(cb => cb.addEventListener('change', berechneGesamt));
    mengenFelder.forEach(inp => inp.addEventListener('input', function () {
        berechneBetrag(this.dataset.idx);
        berechneGesamt();
    }));

    toggleModus();
    berechneGesamt();
});
