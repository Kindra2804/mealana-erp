var katvLoeschId       = null;
var katvLoeschParentId = null;

function katNeuOeffnen(parentId, parentName) {
    var titel = parentName ? 'Neue Unterkategorie unter "' + parentName + '"' : 'Neue Hauptkategorie';
    document.getElementById('katv-modal-titel').textContent = titel;
    document.getElementById('katv-edit-id').value  = '';
    document.getElementById('katv-name').value     = '';
    document.getElementById('katv-beschreibung').value = '';
    document.getElementById('katv-parent').value   = parentId != null ? parentId : '';
    document.getElementById('katv-ist-aktions-kat').checked = false;
    document.getElementById('katv-fehler').textContent = '';
    katvAktualisierePositionsDropdown();
    // Bild-Upload UND Shop-Sichtbarkeit brauchen eine existierende Kategorie-ID
    // (uploads/kategorien/{id}/ bzw. kategorie_shops-Zeile) -- bei einer neuen
    // Kategorie also erst nach dem ersten Speichern verfügbar.
    document.getElementById('katv-bild-bereich').style.display = 'none';
    document.getElementById('katv-shop-bereich').style.display = 'none';
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('katv-name').focus(); }, 50);
}

function katBearbeiten(id, name, parentId) {
    var zeile = document.querySelector('.katv-zeile[data-id="' + id + '"]');
    var iak   = zeile ? parseInt(zeile.dataset.iak || '0') : 0;
    document.getElementById('katv-modal-titel').textContent = 'Kategorie bearbeiten';
    document.getElementById('katv-edit-id').value  = id;
    document.getElementById('katv-name').value     = name;
    document.getElementById('katv-beschreibung').value = zeile ? (zeile.dataset.beschreibung || '') : '';
    document.getElementById('katv-parent').value   = parentId != null ? parentId : '';
    document.getElementById('katv-ist-aktions-kat').checked = !!iak;
    document.getElementById('katv-fehler').textContent = '';
    document.querySelectorAll('#katv-parent option').forEach(function (o) { o.disabled = (parseInt(o.value) === id); });
    katvAktualisierePositionsDropdown();
    document.getElementById('katv-bild-bereich').style.display = '';
    katvBildAnzeigen(id, zeile ? (zeile.dataset.bild || '') : '');
    document.getElementById('katv-shop-bereich').style.display = '';
    katvShopCheckboxenRendern(id);
    document.getElementById('katv-modal').style.display = 'flex';
    setTimeout(function () { document.getElementById('katv-name').focus(); }, 50);
}

// Baut pro aktivem Shop eine Checkbox (angehakt = Kategorie erscheint in diesem
// Shop, abgewählt = ausgeschlossen). Toggle speichert sofort per AJAX, wie beim
// Kategoriebild-Upload -- kein Extra-Klick auf "Speichern" nötig.
function katvShopCheckboxenRendern(kategorieId) {
    var container = document.getElementById('katv-shop-checkboxen');
    var ausschluesse = window.KATEGORIE_AUSSCHLUESSE[kategorieId] || {};
    var html = '';
    (window.SHOPS || []).forEach(function (shop) {
        var ausgeschlossen = !!ausschluesse[shop.id];
        html += '<label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">'
            + '<input type="checkbox" ' + (ausgeschlossen ? '' : 'checked') + ' style="width:15px;height:15px" '
            + 'onchange="katvShopAusschlussUmschalten(' + kategorieId + ',' + shop.id + ',!this.checked)">'
            + '<span>' + katvEscapeHtml(shop.name) + '</span></label>';
    });
    container.innerHTML = html || '<span style="font-size:12px;color:var(--color-text-muted)">Keine aktiven Shops vorhanden</span>';
}

function katvShopAusschlussUmschalten(kategorieId, shopId, ausgeschlossen) {
    if (!window.KATEGORIE_AUSSCHLUESSE[kategorieId]) window.KATEGORIE_AUSSCHLUESSE[kategorieId] = {};
    window.KATEGORIE_AUSSCHLUESSE[kategorieId][shopId] = ausgeschlossen;
    fetch(window.BASE_PATH + '/artikel/kategorie_shop_ausschluss_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'kategorie_id=' + kategorieId + '&shop_id=' + shopId + '&ausgeschlossen=' + (ausgeschlossen ? '1' : '0')
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (!d.erfolg) alert(d.fehler || 'Fehler beim Speichern der Shop-Sichtbarkeit');
    });
}

// Zeigt das aktuelle Kategoriebild (falls vorhanden) im Modal an, oder blendet
// Vorschau/Entfernen-Button aus, wenn noch keins gesetzt ist.
function katvBildAnzeigen(kategorieId, dateiname) {
    var preview     = document.getElementById('katv-bild-preview');
    var entfernenBtn = document.getElementById('katv-bild-entfernen-btn');
    document.getElementById('katv-bild-datei').value = '';
    document.getElementById('katv-bild-fehler').textContent = '';
    if (dateiname) {
        preview.src = window.BASE_PATH + '/uploads/kategorien/' + kategorieId + '/' + dateiname + '?t=' + Date.now();
        preview.style.display = '';
        entfernenBtn.style.display = '';
    } else {
        preview.src = '';
        preview.style.display = 'none';
        entfernenBtn.style.display = 'none';
    }
}

function katvBildHochladen(input) {
    var kategorieId = document.getElementById('katv-edit-id').value;
    var fehler       = document.getElementById('katv-bild-fehler');
    if (!kategorieId || !input.files || !input.files[0]) return;
    fehler.textContent = '';

    var formData = new FormData();
    formData.append('kategorie_id', kategorieId);
    formData.append('bild', input.files[0]);

    fetch(window.BASE_PATH + '/artikel/kategorie_bild_upload.php', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) {
                katvBildAnzeigen(kategorieId, d.dateiname);
                var zeile = document.querySelector('.katv-zeile[data-id="' + kategorieId + '"]');
                if (zeile) zeile.dataset.bild = d.dateiname;
            } else {
                fehler.textContent = d.fehler || 'Fehler beim Hochladen';
            }
        });
}

function katvBildEntfernen() {
    var kategorieId = document.getElementById('katv-edit-id').value;
    if (!kategorieId) return;
    fetch(window.BASE_PATH + '/artikel/kategorie_bild_loeschen.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'kategorie_id=' + encodeURIComponent(kategorieId)
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) {
            katvBildAnzeigen(kategorieId, '');
            var zeile = document.querySelector('.katv-zeile[data-id="' + kategorieId + '"]');
            if (zeile) zeile.dataset.bild = '';
        } else {
            document.getElementById('katv-bild-fehler').textContent = d.fehler || 'Fehler beim Entfernen';
        }
    });
}

// Befüllt die "Einordnen nach"-Dropdown mit den Geschwistern der aktuell
// gewählten Oberkategorie (window.KATEGORIEN_FLACH, siehe kategorien_verwalten.php).
// Vorbelegung: beim Bearbeiten die bisherige Position (kein ungewolltes
// Verschieben, wenn man das Feld gar nicht anfasst); sonst ans Ende der Liste
// (statt an den Anfang zu springen wie bisher bei neuen Kategorien).
function katvAktualisierePositionsDropdown() {
    var editIdRaw = document.getElementById('katv-edit-id').value;
    var editId    = editIdRaw ? parseInt(editIdRaw, 10) : null;
    var parentVal = document.getElementById('katv-parent').value;
    var parentId  = parentVal === '' ? null : parseInt(parentVal, 10);

    var alleImParent = window.KATEGORIEN_FLACH.filter(function (k) {
        var kParent = (k.parent_id === null || k.parent_id === undefined) ? null : k.parent_id;
        return kParent === parentId;
    });

    // -1 = nicht gefunden (neue Kategorie, oder Oberkategorie wurde gerade
    // gewechselt) -- bewusst getrennt von "0 = steht schon an erster Stelle",
    // sonst lässt sich "kein Vorgänger, weil ganz vorne" nicht von "kein
    // Vorgänger, weil unbekannt" unterscheiden.
    var eigenerIndex = editId !== null
        ? alleImParent.findIndex(function (k) { return k.id === editId; })
        : -1;

    var optionen = alleImParent.filter(function (k) { return k.id !== editId; });

    var select = document.getElementById('katv-nach');
    var html   = '<option value="">– Am Anfang –</option>';
    optionen.forEach(function (g) {
        html += '<option value="' + g.id + '">nach "' + katvEscapeHtml(g.name) + '"</option>';
    });
    select.innerHTML = html;

    if (eigenerIndex > 0) {
        select.value = String(alleImParent[eigenerIndex - 1].id); // bisherige Position exakt erhalten
    } else if (eigenerIndex === 0) {
        select.value = ''; // stand schon an erster Stelle -- bleibt dort
    } else if (optionen.length > 0) {
        select.value = String(optionen[optionen.length - 1].id); // unbekannt -> ans Ende
    }
}

function katvEscapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function katvModalSchliessen() {
    document.getElementById('katv-modal').style.display = 'none';
    document.querySelectorAll('#katv-parent option').forEach(function (o) { o.disabled = false; });
}

function katvSpeichern() {
    var editId   = document.getElementById('katv-edit-id').value;
    var name     = document.getElementById('katv-name').value.trim();
    var parentId = document.getElementById('katv-parent').value;
    var fehler   = document.getElementById('katv-fehler');
    if (!name) { fehler.textContent = 'Name ist Pflichtfeld'; return; }
    fehler.textContent = '';
    var iak          = document.getElementById('katv-ist-aktions-kat').checked ? '1' : '0';
    var beschreibung = document.getElementById('katv-beschreibung').value.trim();
    var nachId       = document.getElementById('katv-nach').value;
    var url  = editId ? window.BASE_PATH + '/artikel/kategorie_bearbeiten_ajax.php' : window.BASE_PATH + '/artikel/kategorie_erstellen.php';
    var body = 'name=' + encodeURIComponent(name) + '&parent_id=' + encodeURIComponent(parentId) + '&ist_aktions_kategorie=' + iak
        + '&beschreibung=' + encodeURIComponent(beschreibung) + '&nach_id=' + encodeURIComponent(nachId);
    if (editId) body += '&id=' + encodeURIComponent(editId);
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else fehler.textContent = d.fehler || 'Fehler beim Speichern';
        });
}

function katLoeschenVorschau(id, name) {
    katvLoeschId       = id;
    katvLoeschParentId = null;
    document.getElementById('katv-del-info').textContent = 'Kategorie "' + name + '" wird gelöscht. Bitte warten…';
    document.getElementById('katv-del-warnungen').innerHTML = '';
    document.getElementById('katv-del-optionen').style.display = 'none';
    document.getElementById('katv-del-btn').disabled = true;
    document.getElementById('katv-del-modal').style.display = 'flex';
    fetch(window.BASE_PATH + '/artikel/kategorie_loeschen_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + id + '&aktion=vorschau'
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        var warnHTML = '';
        if (d.kinder_anzahl > 0) {
            warnHTML += '<div style="background:#fff8e1;border:1px solid #f0c000;border-radius:4px;padding:8px 10px;margin-bottom:8px;font-size:12.5px">'
                + '⚠ Diese Kategorie hat <strong>' + d.kinder_anzahl + ' Unterkategorie(n)</strong> — diese werden ebenfalls gelöscht.</div>';
        }
        if (d.artikel_ohne_kat && d.artikel_ohne_kat.length > 0) {
            warnHTML += '<div style="background:#fff0f0;border:1px solid #f5b8b8;border-radius:4px;padding:8px 10px;font-size:12.5px">'
                + '<strong style="color:var(--color-danger)">⚠ ' + d.artikel_ohne_kat.length + ' Artikel würden dadurch kategorielos:</strong>'
                + '<div style="margin-top:6px;max-height:100px;overflow-y:auto">';
            d.artikel_ohne_kat.forEach(function (a) {
                warnHTML += '<div style="font-size:12px;padding:2px 0;border-bottom:1px solid #f5b8b8"><strong>' + a.artikelnummer + '</strong> – ' + a.name + '</div>';
            });
            warnHTML += '</div></div>';
        }
        document.getElementById('katv-del-info').textContent = warnHTML
            ? 'Kategorie "' + name + '" löschen – bitte Warnungen beachten:'
            : 'Kategorie "' + name + '" löschen?';
        document.getElementById('katv-del-warnungen').innerHTML = warnHTML;
        if (d.parent) {
            katvLoeschParentId = d.parent.id;
            document.getElementById('katv-del-parent-name').textContent = '"' + d.parent.name + '"';
            document.getElementById('katv-del-optionen').style.display = '';
            var radVers = document.querySelector('input[name="katv-del-modus"][value="verschieben"]');
            if (radVers) radVers.checked = true;
        }
        document.getElementById('katv-del-btn').disabled = false;
    });
}

function katvDelSchliessen() {
    document.getElementById('katv-del-modal').style.display = 'none';
    katvLoeschId = null; katvLoeschParentId = null;
}

function katvLoeschenBestaetigt() {
    if (!katvLoeschId) return;
    document.getElementById('katv-del-btn').disabled = true;
    var modus       = document.querySelector('input[name="katv-del-modus"]:checked');
    var verschiebeId = '';
    if (modus && modus.value === 'verschieben' && katvLoeschParentId) verschiebeId = katvLoeschParentId;
    fetch(window.BASE_PATH + '/artikel/kategorie_loeschen_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + katvLoeschId + '&aktion=loeschen&verschiebe_zu_parent_id=' + verschiebeId
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else { alert(d.fehler || 'Fehler beim Löschen'); katvDelSchliessen(); }
    });
}

function katSortieren(id, richtung) {
    fetch(window.BASE_PATH + '/artikel/kategorie_sort_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: id, richtung: richtung })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else alert(d.fehler || 'Fehler beim Sortieren');
    });
}
