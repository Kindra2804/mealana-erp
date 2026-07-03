(function () {
    var LS_KEY = 'mealana_kat_offen';

    function getState() {
        try { return JSON.parse(localStorage.getItem(LS_KEY) || '{}'); }
        catch (e) { return {}; }
    }

    function setState(s) {
        localStorage.setItem(LS_KEY, JSON.stringify(s));
    }

    var state = getState();
    document.querySelectorAll('.kat-kinder').forEach(function (el) {
        var id      = el.id;
        var toggleEl = document.getElementById('kattog-' + id.replace('kat-', ''));
        var istOffen = state[id] !== false;
        el.classList.toggle('versteckt', !istOffen);
        if (toggleEl) toggleEl.textContent = istOffen ? '▼' : '▶';
    });

    if (window.MEALANA_AKTIV_KAT) {
        (function aufklappen(nodeId) {
            var el = document.getElementById(nodeId);
            if (!el) return;
            el.classList.remove('versteckt');
            var toggleEl = document.getElementById('kattog-' + nodeId.replace('kat-', ''));
            if (toggleEl) toggleEl.textContent = '▼';
            var parent = el.parentElement;
            while (parent) {
                if (parent.classList.contains('kat-kinder')) {
                    parent.classList.remove('versteckt');
                    var pid = parent.id;
                    var pt  = document.getElementById('kattog-' + pid.replace('kat-', ''));
                    if (pt) pt.textContent = '▼';
                }
                parent = parent.parentElement;
            }
        })('kat-' + window.MEALANA_AKTIV_KAT);

        var aktiveZeile = document.querySelector('.kat-zeile.aktiv');
        if (aktiveZeile) aktiveZeile.scrollIntoView({ block: 'center', behavior: 'instant' });
    }

    window.katToggle = function (nodeId, toggleId) {
        var el = document.getElementById(nodeId);
        var t  = document.getElementById(toggleId);
        if (!el) return;
        var wirdGeoeffnet = el.classList.contains('versteckt');
        el.classList.toggle('versteckt', !wirdGeoeffnet);
        if (t) t.textContent = wirdGeoeffnet ? '▼' : '▶';
        var s = getState();
        s[nodeId] = wirdGeoeffnet;
        setState(s);
    };
})();

window.erpNavMoreToggle = function () {
    var menu = document.getElementById('erp-nav-more-menu');
    if (!menu) return;
    menu.classList.toggle('open');
};

document.addEventListener('click', function (e) {
    var wrap = document.getElementById('erp-nav-more-wrap');
    if (wrap && !wrap.contains(e.target)) {
        var menu = document.getElementById('erp-nav-more-menu');
        if (menu) menu.classList.remove('open');
    }
});

window.katNeuOeffnen = function () {
    document.getElementById('kat-neu-modal').style.display = 'flex';
    document.getElementById('kat-neu-name').focus();
};
window.katNeuSchliessen = function () {
    document.getElementById('kat-neu-modal').style.display = 'none';
    document.getElementById('kat-neu-name').value          = '';
    document.getElementById('kat-neu-parent').value        = '';
    document.getElementById('kat-neu-fehler').textContent  = '';
};
window.katNeuSpeichern = function () {
    var name   = document.getElementById('kat-neu-name').value.trim();
    var parent = document.getElementById('kat-neu-parent').value;
    var fehler = document.getElementById('kat-neu-fehler');
    if (!name) { fehler.textContent = 'Name ist Pflichtfeld'; return; }
    fehler.textContent = '';
    fetch(window.BASE_PATH + '/artikel/kategorie_erstellen.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'name=' + encodeURIComponent(name) + '&parent_id=' + encodeURIComponent(parent)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) { katNeuSchliessen(); window.location.reload(); }
        else { fehler.textContent = d.fehler || 'Fehler beim Speichern'; }
    });
};
