document.getElementById('ean-input').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') eanSuchen();
});

function eanSuchen() {
    var ean = document.getElementById('ean-input').value.trim();
    if (!ean) return;

    fetch('/mealana/wareneingang/artikel_suche.php?ean=' + encodeURIComponent(ean))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var box = document.getElementById('scan-ergebnis');
            if (!data.gefunden) {
                box.innerHTML = '<div style="color:var(--color-danger);font-size:13px">EAN nicht gefunden: ' + escHtml(ean) + '</div>' +
                    '<a href="/mealana/wareneingang/artikel_vorbereiten.php?ean=' + encodeURIComponent(ean) + '" class="btn btn-secondary btn-sm" style="margin-top:8px">+ Neuen Artikel anlegen</a>';
                box.style.display = 'block';
                document.getElementById('kacheln-bereich').style.display = '';
                return;
            }
            var a  = data.artikel;
            var bs = data.bestellungen;
            var html = '<div style="display:flex;gap:14px;align-items:flex-start;padding:10px;background:#f8f9fa;border-radius:6px">';
            if (a.hauptbild) {
                html += '<img src="/mealana/uploads/artikel/' + a.id + '/' + escHtml(a.hauptbild) + '" style="width:72px;height:72px;object-fit:cover;border-radius:4px;flex-shrink:0" onerror="this.style.display=\'none\'">';
            }
            html += '<div style="flex:1">';
            html += '<div style="font-weight:600;font-size:14px">' + escHtml(a.anzeige_name) + '</div>';
            html += '<div style="font-size:12px;color:var(--color-text-muted)">EAN: ' + escHtml(ean) + '</div>';
            if (!bs.length) {
                html += '<div style="margin-top:8px;font-size:13px;color:var(--color-text-muted)">Keine offene Bestellung enthält diesen Artikel.</div>';
                html += '<div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">';
                html += '<button type="button" class="btn btn-primary btn-sm" onclick="zurSammelliste(' + a.id + ', \'' + escHtml(a.anzeige_name).replace(/'/g, "\\'") + '\', \'' + encodeURIComponent(ean) + '\')">+ Zur Sammelliste</button>';
                html += '<a href="/mealana/lager/wareneingang.php?ean=' + encodeURIComponent(ean) + '" class="btn btn-secondary btn-sm">Freier Wareneingang</a>';
                html += '</div>';
            } else {
                html += '<div style="margin-top:8px;font-size:13px;font-weight:500">In welcher Bestellung buchen?</div>';
                bs.forEach(function (b) {
                    html += '<a href="/mealana/wareneingang/detail.php?bestellung_id=' + b.id + '&scan_artikel_id=' + a.id + '&scan_ean=' + encodeURIComponent(ean) + '" style="display:flex;justify-content:space-between;align-items:center;padding:7px 10px;margin-top:6px;background:#fff;border:1px solid var(--color-border);border-radius:4px;text-decoration:none;color:inherit">';
                    html += '<span style="font-size:13px">' + escHtml(b.lieferant_name) + ' &nbsp; ' + formatDatum(b.bestelldatum) + '</span>';
                    html += '<span style="font-size:12px;color:var(--color-text-muted)">offen: <strong>' + b.menge_offen + '</strong> Stk</span>';
                    html += '</a>';
                });
                html += '<a href="/mealana/lager/wareneingang.php?ean=' + encodeURIComponent(ean) + '" style="display:block;margin-top:8px;font-size:12px;color:var(--color-text-muted)">Freier Wareneingang ohne Bestellung</a>';
            }
            html += '</div></div>';
            box.innerHTML = html;
            box.style.display = 'block';
            document.getElementById('kacheln-bereich').style.display = 'none';
        });

    document.getElementById('ean-input').select();
}

function formatDatum(d) {
    var p = d.split('-');
    return p[2] + '.' + p[1] + '.' + p[0];
}

function zurSammelliste(artikelId, name, eanEncoded) {
    var ean = decodeURIComponent(eanEncoded);
    var fd  = new FormData();
    fd.append('artikel_id', artikelId);
    fd.append('menge', 1);
    fd.append('name', name);
    fd.append('ean', ean);
    fetch('/mealana/wareneingang/durchlauf_add.php', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d.erfolg) window.location.reload(); });
}

function durchlaufLeeren() {
    fetch('/mealana/wareneingang/durchlauf_clear.php', { method: 'POST' })
        .then(function (r) { return r.json(); })
        .then(function () { window.location.reload(); });
}

function escHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

addEventListener('DOMContentLoaded', function () {
    var ean = new URLSearchParams(window.location.search).get('ean');
    if (ean) { document.getElementById('ean-input').value = ean; eanSuchen(); }
});
