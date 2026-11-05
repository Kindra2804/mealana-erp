/* NAHTLOS Connector – Slider: automatisch wechseln, Pfeile, Punkte, Wischen, Pause bei Maus/Fokus. */
(function () {
    'use strict';
    var ruhig = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('.nahtlos-slider').forEach(function (slider) {
        // Zeitsteuerung im Browser: die Seite kann aus dem Cache kommen, darum hier nach der
        // Uhrzeit filtern (data-von/-bis = Unix-Zeit, 0 = offen). Unsichtbare Slides + Punkte entfernen.
        var jetzt = Date.now() / 1000;
        var allePunkte = slider.querySelectorAll('.nahtlos-slider__punkt');
        slider.querySelectorAll('.nahtlos-slide').forEach(function (s, i) {
            var von = +s.dataset.von || 0, bis = +s.dataset.bis || 0;
            if ((von && von > jetzt) || (bis && bis < jetzt)) {
                s.remove();
                if (allePunkte[i]) allePunkte[i].remove();
            } else {
                s.classList.remove('ist-geplant');
                if (allePunkte[i]) allePunkte[i].classList.remove('ist-geplant');
            }
        });
        var slides = slider.querySelectorAll('.nahtlos-slide');
        if (!slides.length) { slider.remove(); return; }
        if (!slider.querySelector('.nahtlos-slide.ist-aktiv')) slides[0].classList.add('ist-aktiv');
        if (slides.length < 2) {
            slides[0].setAttribute('aria-hidden', 'false');
            slides[0].querySelectorAll('a').forEach(function (a) { a.tabIndex = 0; });
            slider.querySelectorAll('.nahtlos-slider__pfeil, .nahtlos-slider__punkte').forEach(function (el) { el.remove(); });
            return;
        }
        var punkte = slider.querySelectorAll('.nahtlos-slider__punkt');
        var intervall = (parseInt(slider.dataset.intervall, 10) || 6) * 1000;
        var aktiv = 0, timer = null, pausiert = false;
        slides.forEach(function (s, i) { if (s.classList.contains('ist-aktiv')) aktiv = i; });
        zeige(aktiv); // Zustand nach dem Filtern vereinheitlichen (aria, Tabulator, Punkte)

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
