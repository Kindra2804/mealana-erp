/* NAHTLOS Connector – Slider: automatisch wechseln, Pfeile, Punkte, Wischen, Pause bei Maus/Fokus. */
(function () {
    'use strict';
    var ruhig = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('.nahtlos-slider').forEach(function (slider) {
        var slides = slider.querySelectorAll('.nahtlos-slide');
        if (slides.length < 2) return;
        var punkte = slider.querySelectorAll('.nahtlos-slider__punkt');
        var intervall = (parseInt(slider.dataset.intervall, 10) || 6) * 1000;
        var aktiv = 0, timer = null, pausiert = false;

        function zeige(nr) {
            nr = (nr + slides.length) % slides.length;
            slides.forEach(function (s, i) {
                var an = i === nr;
                s.classList.toggle('ist-aktiv', an);
                s.setAttribute('aria-hidden', an ? 'false' : 'true');
                s.querySelectorAll('a').forEach(function (a) { a.tabIndex = an ? 0 : -1; });
            });
            punkte.forEach(function (p, i) { p.classList.toggle('ist-aktiv', i === nr); });
            aktiv = nr;
        }
        function starte() {
            stoppe();
            if (!ruhig && !pausiert) timer = setInterval(function () { zeige(aktiv + 1); }, intervall);
        }
        function stoppe() { if (timer) clearInterval(timer); timer = null; }

        slider.querySelector('.nahtlos-slider__pfeil--weiter').addEventListener('click', function () { zeige(aktiv + 1); starte(); });
        slider.querySelector('.nahtlos-slider__pfeil--zurueck').addEventListener('click', function () { zeige(aktiv - 1); starte(); });
        punkte.forEach(function (p, i) { p.addEventListener('click', function () { zeige(i); starte(); }); });

        slider.addEventListener('mouseenter', function () { pausiert = true; stoppe(); });
        slider.addEventListener('mouseleave', function () { pausiert = false; starte(); });
        slider.addEventListener('focusin', function () { pausiert = true; stoppe(); });
        slider.addEventListener('focusout', function () { pausiert = false; starte(); });
        document.addEventListener('visibilitychange', function () { document.hidden ? stoppe() : starte(); });

        // Wischen am Handy
        var startX = null;
        slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
        slider.addEventListener('touchend', function (e) {
            if (startX === null) return;
            var dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 40) { zeige(aktiv + (dx < 0 ? 1 : -1)); starte(); }
            startX = null;
        });

        starte();
    });
})();
