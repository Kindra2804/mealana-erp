var ACHSEN_NAMEN = window.ACHSEN_NAMEN || {};
var chipCounter  = 9000;
var formDirty    = false;

function wertHinzufuegen(achseId) {
    var inp  = document.getElementById('inp-' + achseId);
    var text = inp.value.trim();
    if (!text) return;
    var cont = document.getElementById('chips-' + achseId);
    var idx  = ++chipCounter;
    cont.insertAdjacentHTML('beforeend', chipHtmlJs(achseId, idx, text));
    inp.value = '';
    formDirty = true;
    updateWerteCount(achseId);
    inp.focus();
}

function chipHtmlJs(achseId, idx, text) {
    var esc    = text.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    var name   = 'werte[' + achseId + '][' + idx + '][wert]';
    var idName = 'werte[' + achseId + '][' + idx + '][id]';
    return '<span class="wert-chip" data-achse-id="' + achseId + '" '
         + 'style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1e40af;'
         + 'border-radius:16px;padding:3px 10px 3px 8px;font-size:12px;line-height:1.5">'
         + '<button type="button" onclick="chipSortieren(this,\'links\')" title="Nach links" '
         + 'style="background:none;border:none;cursor:pointer;padding:0 1px;color:#93c5fd;font-size:10px;line-height:1">&#9664;</button>'
         + '<span class="chip-text">' + esc + '</span>'
         + '<input type="hidden" name="' + name + '" value="' + esc + '">'
         + '<input type="hidden" name="' + idName + '" value="0">'
         + '<button type="button" onclick="chipBearbeiten(this)" title="Text bearbeiten" '
         + 'style="background:none;border:none;cursor:pointer;padding:0 2px;color:#93c5fd;font-size:11px;line-height:1">&#x270E;</button>'
         + '<button type="button" onclick="chipSortieren(this,\'rechts\')" title="Nach rechts" '
         + 'style="background:none;border:none;cursor:pointer;padding:0 1px;color:#93c5fd;font-size:10px;line-height:1">&#9654;</button>'
         + '<button type="button" onclick="chipVerschieben(this)" title="Verschieben" '
         + 'style="background:none;border:none;cursor:pointer;padding:0 2px;color:#3b82f6;font-size:11px;line-height:1">&#8596;</button>'
         + '<button type="button" onclick="chipEntfernen(this)" '
         + 'style="background:none;border:none;cursor:pointer;padding:0 0 0 2px;color:#3b82f6;font-size:14px;line-height:1">&#x2715;</button>'
         + '<select class="chip-move-sel" style="display:none;font-size:11px;border:1px solid #93c5fd;border-radius:4px;margin-left:2px" '
         + 'onchange="moveAusfuehren(this)"><option value="">&#8594; verschieben nach...</option></select>'
         + '</span>';
}

function chipBearbeiten(btn) {
    var chip   = btn.closest('.wert-chip');
    var textEl = chip.querySelector('.chip-text');
    var hidden = chip.querySelector('input[type="hidden"][name$="[wert]"]');
    if (chip.querySelector('.chip-edit-inp')) return;

    var inp = document.createElement('input');
    inp.type      = 'text';
    inp.value     = textEl.textContent;
    inp.className = 'chip-edit-inp';
    inp.style.cssText = 'font-size:12px;border:1px solid #93c5fd;border-radius:4px;padding:1px 5px;'
                      + 'width:' + Math.max(60, textEl.textContent.length * 8) + 'px;color:#1e40af;background:#fff;outline:none';

    textEl.style.display = 'none';
    textEl.after(inp);
    inp.focus();
    inp.select();

    var bestaetigt = false;
    function bestaetigen() {
        if (bestaetigt) return;
        var neu = inp.value.trim();
        if (!neu) { inp.focus(); return; }
        bestaetigt = true;
        textEl.textContent   = neu;
        hidden.value         = neu;
        textEl.style.display = '';
        inp.remove();
        formDirty = true;
    }
    function abbrechen() {
        if (bestaetigt) return;
        bestaetigt = true;
        textEl.style.display = '';
        inp.remove();
    }

    inp.addEventListener('keydown', function(e) {
        if (e.key === 'Enter')  { e.preventDefault(); bestaetigen(); }
        if (e.key === 'Escape') { abbrechen(); }
    });
    // setTimeout verhindert dass blur sofort beim focus()-Aufruf feuert
    setTimeout(function() { inp.addEventListener('blur', bestaetigen); }, 0);
}

function chipEntfernen(btn) {
    var chip    = btn.closest('.wert-chip');
    var achseId = chip.dataset.achseId;
    chip.remove();
    formDirty = true;
    updateWerteCount(achseId);
}

function chipSortieren(btn, richtung) {
    var chip    = btn.closest('.wert-chip');
    var cont    = chip.parentNode;
    var achseId = chip.dataset.achseId;
    if (richtung === 'links') {
        var prev = chip.previousElementSibling;
        if (prev && prev.classList.contains('wert-chip')) cont.insertBefore(chip, prev);
    } else {
        var next = chip.nextElementSibling;
        if (next && next.classList.contains('wert-chip')) cont.insertBefore(next, chip);
    }
    renummerieren(achseId);
    formDirty = true;
}

function renummerieren(achseId) {
    var cont = document.getElementById('chips-' + achseId);
    if (!cont) return;
    cont.querySelectorAll('.wert-chip').forEach(function (chip, i) {
        chip.querySelectorAll('input[type="hidden"]').forEach(function (hidden) {
            var m = hidden.name.match(/\[(\w+)\]$/);
            var feld = m ? m[1] : 'wert';
            hidden.name = 'werte[' + achseId + '][' + i + '][' + feld + ']';
        });
    });
}

function chipVerschieben(btn) {
    var chip = btn.closest('.wert-chip');
    var sel  = chip.querySelector('.chip-move-sel');
    if (sel.style.display !== 'none') { sel.style.display = 'none'; return; }
    while (sel.options.length > 1) sel.remove(1);
    var curId = chip.dataset.achseId;
    document.querySelectorAll('input[name="achsen[]"]:checked').forEach(function (cb) {
        if (cb.value !== curId) {
            var opt = document.createElement('option');
            opt.value = cb.value;
            opt.textContent = ACHSEN_NAMEN[cb.value] || ('Achse ' + cb.value);
            sel.appendChild(opt);
        }
    });
    if (sel.options.length <= 1) { alert('Keine andere Achse ausgewählt. Bitte erst weitere Achsen anhaken.'); return; }
    sel.style.display = 'inline-block';
}

function moveAusfuehren(sel) {
    var newId = sel.value;
    if (!newId) return;
    var chip   = sel.closest('.wert-chip');
    var oldId  = chip.dataset.achseId;
    var idx    = ++chipCounter;
    chip.querySelectorAll('input[type="hidden"]').forEach(function (hidden) {
        var m = hidden.name.match(/\[(\w+)\]$/);
        var feld = m ? m[1] : 'wert';
        hidden.name = 'werte[' + newId + '][' + idx + '][' + feld + ']';
    });
    chip.dataset.achseId = newId;
    var newCont = document.getElementById('chips-' + newId);
    if (newCont) newCont.appendChild(chip);
    sel.value  = '';
    sel.style.display = 'none';
    formDirty  = true;
    updateWerteCount(oldId);
    updateWerteCount(newId);
}

function preisModiSetzen(achseId, modus) {
    document.getElementById('pm-' + achseId).value = modus;
    var apBtn = document.getElementById('pm-ap-' + achseId);
    var dpBtn = document.getElementById('pm-dp-' + achseId);
    function setAktiv(btn, aktiv) {
        if (!btn) return;
        btn.style.background   = aktiv ? '#1e40af' : '#f1f5f9';
        btn.style.color        = aktiv ? '#fff'    : '#64748b';
        btn.style.borderColor  = aktiv ? '#1e40af' : '#e2e8f0';
    }
    setAktiv(apBtn, modus === 'aufpreis');
    setAktiv(dpBtn, modus === 'direktpreis');
    formDirty = true;
}

function achseGeaendert(achseId) {
    var cb    = document.getElementById('cb-' + achseId);
    var wBlk  = document.getElementById('werte-blk-' + achseId);
    var uaBtn = document.getElementById('ua-btn-' + achseId);
    var checked = cb.checked;
    if (wBlk)  { if (checked) wBlk.removeAttribute('hidden');  else wBlk.setAttribute('hidden', ''); }
    if (uaBtn) { if (checked) uaBtn.removeAttribute('hidden'); else uaBtn.setAttribute('hidden', ''); }
}

function uaZeigen(parentId) {
    document.getElementById('ua-btn-'  + parentId).style.display = 'none';
    document.getElementById('ua-form-' + parentId).style.display = '';
    document.getElementById('ua-name-' + parentId).focus();
}

function uaAbbrechen(parentId) {
    document.getElementById('ua-form-'   + parentId).style.display = 'none';
    document.getElementById('ua-btn-'    + parentId).style.display = '';
    document.getElementById('ua-name-'   + parentId).value = '';
    document.getElementById('ua-fehler-' + parentId).textContent = '';
}

function uaSpeichern(parentId) {
    var name     = document.getElementById('ua-name-'   + parentId).value.trim();
    var isGruppe = document.getElementById('ua-gruppe-' + parentId).checked ? '1' : '0';
    var fehlEl   = document.getElementById('ua-fehler-' + parentId);
    fehlEl.textContent = '';
    if (!name) { fehlEl.textContent = 'Name erforderlich'; return; }
    if (formDirty && !confirm('Nicht gespeicherte Werte gehen beim Neuladen verloren.\nTrotzdem Unterachse anlegen?')) return;
    var code = name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
    var body = new FormData();
    body.append('name', name); body.append('code', code); body.append('darstellungsform', 'swatches');
    body.append('ist_gruppe', isGruppe); body.append('abhaengig_von_achse_id', parentId); body.append('sort_order', '0');
    fetch(window.BASE_PATH + '/achsen/achse_speichern_ajax.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else fehlEl.textContent = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Fehler');
        })
        .catch(function () { fehlEl.textContent = 'Serverfehler'; });
}

function neueAchseZeigen() {
    document.getElementById('na-name').value   = '';
    document.getElementById('na-gruppe').checked = false;
    document.getElementById('na-darst').value  = 'swatches';
    document.getElementById('na-fehler').textContent = '';
    document.getElementById('na-btn').disabled = false;
    document.getElementById('neue-achse-modal').style.display = 'flex';
    document.getElementById('na-name').focus();
}

function neueAchseSchliessen() { document.getElementById('neue-achse-modal').style.display = 'none'; }

function neueAchseSpeichern() {
    var name     = document.getElementById('na-name').value.trim();
    var isGruppe = document.getElementById('na-gruppe').checked ? '1' : '0';
    var darst    = document.getElementById('na-darst').value;
    var fehlEl   = document.getElementById('na-fehler');
    fehlEl.textContent = '';
    if (!name) { fehlEl.textContent = 'Name erforderlich'; document.getElementById('na-name').focus(); return; }
    if (formDirty && !confirm('Nicht gespeicherte Werte gehen beim Neuladen verloren.\nTrotzdem neue Achse anlegen?')) return;
    var code = name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
    var body = new FormData();
    body.append('name', name); body.append('code', code); body.append('darstellungsform', darst);
    body.append('ist_gruppe', isGruppe); body.append('abhaengig_von_achse_id', ''); body.append('sort_order', '0');
    document.getElementById('na-btn').disabled = true;
    fetch(window.BASE_PATH + '/achsen/achse_speichern_ajax.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else { fehlEl.textContent = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Fehler'); document.getElementById('na-btn').disabled = false; }
        })
        .catch(function () { fehlEl.textContent = 'Serverfehler'; document.getElementById('na-btn').disabled = false; });
}

function achseSort(id, richtung, parentId) {
    fetch(window.BASE_PATH + '/achsen/achse_sort_tree_ajax.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ id: id, richtung: richtung, parent_id: parentId })
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else alert(d.fehler || 'Fehler beim Sortieren');
    });
}

function updateWerteCount(achseId) {
    var cont  = document.getElementById('chips-' + achseId);
    var badge = document.getElementById('werte-count-' + achseId);
    if (!cont || !badge) return;
    var count = cont.querySelectorAll('.wert-chip').length;
    badge.textContent = count + (count === 1 ? ' Wert' : ' Werte');
    badge.style.display = count > 0 ? '' : 'none';
}

function achseBBOeffnen(achse) {
    document.getElementById('abb-id').value        = achse.id;
    document.getElementById('abb-name').value      = achse.name;
    document.getElementById('abb-darst').value     = achse.darstellungsform;
    document.getElementById('abb-gruppe').checked  = achse.ist_gruppe == 1;
    document.getElementById('abb-abhaengig').value = achse.abhaengig_von_achse_id || '';
    document.getElementById('abb-sort').value      = achse.sort_order || 0;
    document.getElementById('abb-fehler').textContent = '';
    document.getElementById('abb-save-btn').disabled  = false;
    var gruppeEl      = document.getElementById('abb-gruppe');
    var gruppeHinweis = document.getElementById('abb-gruppe-hinweis');
    if (achse.hat_kinder) { gruppeEl.disabled = true; gruppeHinweis.style.display = ''; }
    else                  { gruppeEl.disabled = false; gruppeHinweis.style.display = 'none'; }
    var delBtn = document.getElementById('abb-del-btn');
    if (achse.in_use || achse.hat_kinder) {
        delBtn.disabled      = true;
        delBtn.title         = achse.hat_kinder ? 'Zuerst alle Unterachsen löschen' : 'Achse ist Artikeln zugewiesen — kann nicht gelöscht werden';
        delBtn.style.opacity = '0.4';
    } else {
        delBtn.disabled      = false;
        delBtn.title         = '';
        delBtn.style.opacity = '1';
    }
    document.getElementById('achse-bearb-modal').style.display = 'flex';
    document.getElementById('abb-name').focus();
}

function achseBBSchliessen() { document.getElementById('achse-bearb-modal').style.display = 'none'; }

function achseBBSpeichern() {
    var id       = document.getElementById('abb-id').value;
    var name     = document.getElementById('abb-name').value.trim();
    var darst    = document.getElementById('abb-darst').value;
    var isGruppe = document.getElementById('abb-gruppe').checked ? '1' : '0';
    var abhaengig = document.getElementById('abb-abhaengig').value;
    var sort     = document.getElementById('abb-sort').value;
    var fehlEl   = document.getElementById('abb-fehler');
    fehlEl.textContent = '';
    if (!name) { fehlEl.textContent = 'Name erforderlich'; document.getElementById('abb-name').focus(); return; }
    var code = name.toLowerCase().replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
    var body = new FormData();
    body.append('id', id); body.append('name', name); body.append('code', code);
    body.append('darstellungsform', darst); body.append('ist_gruppe', isGruppe);
    body.append('abhaengig_von_achse_id', abhaengig); body.append('sort_order', sort);
    document.getElementById('abb-save-btn').disabled = true;
    fetch(window.BASE_PATH + '/achsen/achse_aktualisieren_ajax.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else { fehlEl.textContent = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Fehler'); document.getElementById('abb-save-btn').disabled = false; }
        })
        .catch(function () { fehlEl.textContent = 'Serverfehler'; document.getElementById('abb-save-btn').disabled = false; });
}

function achseBBLoeschen() {
    var name = document.getElementById('abb-name').value;
    if (!confirm('Achse "' + name + '" global löschen?\nDies betrifft alle Artikel!')) return;
    var id   = document.getElementById('abb-id').value;
    var body = new FormData();
    body.append('id', id);
    fetch(window.BASE_PATH + '/achsen/achse_loeschen_ajax.php', { method: 'POST', body: body })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.erfolg) window.location.reload();
            else document.getElementById('abb-fehler').textContent = Array.isArray(d.fehler) ? d.fehler.join(', ') : (d.fehler || 'Fehler');
        })
        .catch(function () { document.getElementById('abb-fehler').textContent = 'Serverfehler'; });
}
