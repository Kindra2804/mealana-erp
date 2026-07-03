</main>
<div class="erp-logbar">
    <span>ℹ️ 14:23:01 — System bereit</span>
</div>
<div class="erp-statusbar">
    <span>👤 Karl</span>
    <span>📦 Ladengeschäft</span>
    <span>🕐 Sync: 14:23</span>
    <span>v<?= htmlspecialchars(APP_VERSION) ?></span>
</div>
</div>

<!-- ── Page-Loader Overlay ───────────────────────────────────────────────────── -->
<div id="page-loader" style="
    display:none;position:fixed;inset:0;z-index:99999;
    background:rgba(255,255,255,.75);backdrop-filter:blur(2px);
    align-items:center;justify-content:center;flex-direction:column;gap:14px">
    <div id="page-loader-spinner" style="
        width:44px;height:44px;border-radius:50%;
        border:4px solid #e5e7eb;
        border-top-color:var(--color-nav,#2c3e50);
        animation:erp-spin .75s linear infinite"></div>
    <div style="font-size:13px;color:#555;font-family:Arial,sans-serif">Wird geladen …</div>
</div>

<style>
@keyframes erp-spin { to { transform:rotate(360deg); } }
</style>

<script>
(function () {
    var loader = document.getElementById('page-loader');

    function show() { loader.style.display = 'flex'; }
    function hide() { loader.style.display = 'none'; }

    // Verstecken sobald die neue Seite fertig ist (inkl. Zurück-Button / bfcache)
    window.addEventListener('pageshow', hide);

    // Sicherheits-Timeout: nach 15 s verstecken falls Navigation hängt
    var safetyTimer = null;
    function showWithTimeout() {
        show();
        clearTimeout(safetyTimer);
        safetyTimer = setTimeout(hide, 15000);
    }

    // Link-Klicks abfangen
    document.addEventListener('click', function (e) {
        // Kein Loader wenn onclick return false (Confirm abgebrochen, Validierung etc.)
        if (e.defaultPrevented) return;

        var a = e.target.closest('a[href]');
        if (!a) return;

        var href = a.getAttribute('href') || '';

        // Ausnahmen: neuer Tab, Anker, JS-Links, Mail-Links, opt-out
        if (a.target === '_blank') return;
        if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
        if (a.dataset.noLoader !== undefined) return;

        showWithTimeout();
    });

    // Form-Submits abfangen
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        // AJAX-Formulare und opt-out überspringen
        if (e.target.dataset.noLoader !== undefined) return;
        if (e.target.dataset.ajax !== undefined) return;
        showWithTimeout();
    });
})();
</script>

</body>
</html>
