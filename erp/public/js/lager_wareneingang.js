var inaktiverArtikel = null;

document.getElementById('scan_suche').addEventListener('keypress', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); sucheVariante(); }
});

function sucheVariante() {
    var q = document.getElementById('scan_suche').value.trim();
    if (q.length < 2) return;
    fetch('variante_suche.php?q=' + encodeURIComponent(q))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var div = document.getElementById('variante_ergebnis');
            if (!data.length) {
                div.innerHTML = '<p style="color:var(--color-danger);margin:0">Keine Variante gefunden.</p>';
                return;
            }
            if (data.length === 1) {
                if (data[0].aktiv == 0) zeigeInaktivDialog(data[0]);
                else waehleVariante(data[0]);
                return;
            }
            div.innerHTML = data.map(function (v) {
                var label = v.kind_name
                    ? v.artikel_name + ' <span style="color:var(--color-text-muted)">— ' + v.kind_name + '</span>'
                    : v.artikel_name;
                return '<div style="border:1px solid var(--color-border);padding:8px 12px;margin-bottom:4px;border-radius:4px;cursor:pointer" onclick="waehleVariante(' + JSON.stringify(v).replace(/"/g, '&quot;') + ')">'
                    + '<strong>' + v.varianten_artikelnummer || v.artikelnummer + '</strong> – ' + label + '</div>';
            }).join('');
        });
}

function waehleVariante(v) {
    document.getElementById('artikel_id').value = v.id;
    var label = v.kind_name
        ? v.artikel_name + ' — ' + v.kind_name
        : v.artikel_name;
    var nr = v.varianten_artikelnummer || v.artikelnummer;
    document.getElementById('variante_ergebnis').innerHTML =
        '<div style="background:var(--color-success-light,#d4edda);padding:10px 12px;border-radius:4px;display:flex;justify-content:space-between;align-items:center">'
        + '<span>✅ <strong>' + nr + '</strong> – ' + label + '</span>'
        + '<button type="button" class="btn btn-secondary btn-sm" onclick="varianteZuruecksetzen()">✖</button></div>';
}

function varianteZuruecksetzen() {
    document.getElementById('artikel_id').value      = '';
    document.getElementById('variante_ergebnis').innerHTML = '';
    document.getElementById('scan_suche').value      = '';
    document.getElementById('scan_suche').focus();
    document.getElementById('reaktivieren').value    = 0;
}

function zeigeInaktivDialog(v) {
    inaktiverArtikel = v;
    document.getElementById('artikelname').textContent     = v.artikel_name;
    document.getElementById('aenderungsdatum').textContent = new Date(v.geaendert_am).toLocaleDateString('de-AT');
    document.getElementById('deaktivierterArtikelDialog').style.display = 'flex';
}

function reaktiviereUndBuche() {
    document.getElementById('reaktivieren').value = 1;
    waehleVariante(inaktiverArtikel);
    document.getElementById('deaktivierterArtikelDialog').style.display = 'none';
}

function abbruch() {
    document.getElementById('deaktivierterArtikelDialog').style.display = 'none';
    varianteZuruecksetzen();
}
