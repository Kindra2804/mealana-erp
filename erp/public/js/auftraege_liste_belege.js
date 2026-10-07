/* auftraege/liste.php — Belege-Spalte: ein Beleg -> PDF direkt öffnen, mehrere -> Liste aufklappen */
(function () {
    var offen = null;

    function schliessen() {
        if (offen) { offen.remove(); offen = null; }
    }

    function esc(s) {
        return String(s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    document.addEventListener('click', function (e) {
        var chip = e.target.closest('.beleg-chip.beleg-da');
        if (!chip) {
            if (!e.target.closest('.beleg-popup')) schliessen();
            return;
        }
        e.preventDefault();
        var docs = JSON.parse(chip.dataset.docs || '[]');
        if (docs.length === 1) {
            schliessen();
            window.open(docs[0].url, '_blank');
            return;
        }
        if (offen && offen.dataset.fuer === chip.dataset.titel) { schliessen(); return; }
        schliessen();

        var box = document.createElement('div');
        box.className = 'beleg-popup';
        box.dataset.fuer = chip.dataset.titel;
        box.style.borderColor = getComputedStyle(chip).borderColor;
        box.innerHTML = '<div class="titel">' + esc(chip.dataset.titel) + '</div>'
            + docs.map(function (d) {
                return '<a href="' + esc(d.url) + '" target="_blank">📄 ' + esc(d.nr) + ' · ' + esc(d.datum) + '</a>';
            }).join('')
            + '<div class="hint">Klick öffnet das PDF</div>';
        document.body.appendChild(box);
        var r = chip.getBoundingClientRect();
        box.style.left = (window.scrollX + r.left) + 'px';
        box.style.top  = (window.scrollY + r.bottom + 4) + 'px';
        offen = box;
    });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') schliessen(); });
})();
