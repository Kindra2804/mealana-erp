</div><!-- .pp-main -->

<!-- ── Page-Loader Overlay ─────────────────────────────────────────────────── -->
<div id="pp-loader" style="
    display:none;position:fixed;inset:0;z-index:99999;
    background:rgba(26,26,46,.88);backdrop-filter:blur(2px);
    align-items:center;justify-content:center;flex-direction:column;gap:14px">
    <div style="
        width:50px;height:50px;border-radius:50%;
        border:4px solid #1e3a5f;
        border-top-color:#e94560;
        animation:pp-spin .75s linear infinite"></div>
    <div style="font-size:14px;color:#aaa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        Wird geladen …
    </div>
</div>

<style>
@keyframes pp-spin { to { transform:rotate(360deg); } }
</style>

<script>
(function () {
    var loader = document.getElementById('pp-loader');
    function show() { loader.style.display = 'flex'; }
    function hide() { loader.style.display = 'none'; }

    window.addEventListener('pageshow', hide);

    var t = null;
    function showSafe() {
        show();
        clearTimeout(t);
        t = setTimeout(hide, 15000);
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented) return;
        var a = e.target.closest('a[href]');
        if (!a) return;
        var href = a.getAttribute('href') || '';
        if (a.target === '_blank') return;
        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
        if (a.dataset.noLoader !== undefined) return;
        showSafe();
    });

    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        if (e.target.dataset.noLoader !== undefined) return;
        if (e.target.dataset.ajax !== undefined) return;
        showSafe();
    });
})();
</script>

</body>
</html>
