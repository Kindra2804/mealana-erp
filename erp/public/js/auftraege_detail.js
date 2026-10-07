/* auftraege/detail.php — Statusänderungen, Tracking, Stornierung */

async function zahlungBuchen(auftragId) {
    const betrag = parseFloat(document.getElementById('zahl-betrag').value.replace(',', '.'));
    const datum  = document.getElementById('zahl-datum').value;
    const notiz  = document.getElementById('zahl-notiz').value.trim();
    if (!betrag || betrag <= 0) { alert('Bitte gültigen Betrag eingeben'); return; }
    if (!datum) { alert('Bitte Buchungsdatum eingeben'); return; }
    const body = new FormData();
    body.append('auftrag_id', auftragId);
    body.append('betrag', betrag);
    body.append('buchungsdatum', datum);
    if (notiz) body.append('notiz', notiz);
    const weg = document.getElementById('zahl-weg');
    if (weg) body.append('zahlungsweg', weg.value);
    const r    = await fetch(window.BASE_PATH + '/auftraege/zahlung_buchen.php', { method: 'POST', body });
    const data = await r.json();
    if (data.erfolg) {
        // Shop-Bestellung: Rückmeldung an WooCommerce fehlgeschlagen -> Hinweis, Zahlung ist trotzdem gebucht
        if (data.shop_hinweis && data.shop_gemeldet === false) alert(data.shop_hinweis);
        location.reload();
    } else {
        alert(data.fehler || 'Fehler beim Buchen');
    }
}

async function statusSetzen(feld, wert, notiz) {
    const body = new FormData();
    body.append('id', window.AUFTRAG_ID);
    body.append(feld, wert);
    if (notiz) body.append('notiz', notiz);

    const res  = await fetch(window.STATUS_AJAX_URL, { method: 'POST', body });
    const data = await res.json();
    if (data.erfolg) {
        location.reload();
    } else {
        alert((data.fehler || ['Fehler']).join('\n'));
    }
}

function lieferstatusAktualisieren() {
    const wert = document.getElementById('lieferstatus-select').value;
    statusSetzen('lieferstatus', wert, null);
}

async function trackingSpeichern() {
    const nr = document.getElementById('tracking-nr').value.trim();
    const dl = document.getElementById('versand-dl').value;
    const body = new FormData();
    body.append('id', window.AUFTRAG_ID);
    body.append('tracking_nr', nr);
    body.append('versanddienstleister', dl);
    if (nr) body.append('lieferstatus', 'versendet');

    const res  = await fetch(window.STATUS_AJAX_URL, { method: 'POST', body });
    const data = await res.json();
    if (data.erfolg) {
        location.reload();
    } else {
        alert((data.fehler || ['Fehler']).join('\n'));
    }
}

function trackingBearbeitenToggle() {
    var form = document.getElementById('tracking-edit-form');
    if (!form) return;
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function storniereAuftrag() {
    if (!confirm('Auftrag wirklich stornieren? Dies kann nicht rückgängig gemacht werden.')) return;
    const notiz = prompt('Stornierungsgrund (optional):') || '';
    const form  = document.createElement('form');
    form.method = 'POST';
    form.action = window.STORNO_URL;
    form.innerHTML = `<input name="id" value="${window.AUFTRAG_ID}"><input name="notiz" value="${notiz.replace(/"/g, '&quot;')}">`;
    document.body.appendChild(form);
    form.submit();
}

// Banner auto-hide
const banner = document.getElementById('erfolg-banner');
if (banner) setTimeout(() => { banner.style.transition = 'opacity .5s'; banner.style.opacity = '0'; setTimeout(() => banner.remove(), 500); }, 3000);

/* ── Versandart ändern (Abholung ↔ Versand), siehe AuftragService::versandartAendern ── */
function versandartDialog() {
    const V = window.VERSANDART;
    const neu = V.lieferart === 'abholung' ? 'versand' : 'abholung';
    const optionen = V.klassen.map(k =>
        `<option value="${k.id}" data-preis="${k.preis_brutto}" ${k.id == V.versandklasse_id ? 'selected' : ''}>${k.name} (${Number(k.preis_brutto).toFixed(2).replace('.', ',')} €)</option>`
    ).join('');
    const hinweis = neu === 'versand'
        ? (['abholbereit', 'kommissioniert', 'teilgeliefert'].includes(V.lieferstatus)
            ? 'Bereits gepackte Ware aus dem Abholfach wird ins Lager zurückgebucht — sie kommt dann normal über den Packplatz in den Versand (Ware aus dem Abholfach nehmen).'
            : 'Der Auftrag geht normal über den Packplatz in den Versand.')
          + (V.zahlungsstatus === 'bezahlt' ? ' Der Kunde hat schon bezahlt: die Versandkosten werden als Restbetrag offen, er bekommt eine Mail mit dem Restbetrag. Der Packplatz fragt vor dem Versand nach.' : '')
        : 'Die Versandkosten werden aus dem Auftrag genommen.'
          + (V.zahlungsstatus === 'bezahlt' ? ' Der Kunde hat schon bezahlt: das Guthaben zahlt die Kasse bei der Abholung bar oder als Gutschein aus.' : '');

    const ov = document.createElement('div');
    ov.id = 'ov-versandart';
    ov.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:3000;display:flex;align-items:center;justify-content:center';
    ov.innerHTML = `
      <div style="background:#fff;border-radius:10px;padding:20px 24px;width:460px;max-width:94vw;box-shadow:0 10px 30px rgba(0,0,0,.2)">
        <div style="font-size:17px;font-weight:700;margin-bottom:12px">⇄ Versandart ändern</div>
        <div style="margin-bottom:12px">
          ${V.lieferart === 'abholung' ? '🏬 Abholung' : '📦 Versand'} &nbsp;→&nbsp;
          <strong>${neu === 'abholung' ? '🏬 Abholung' : '📦 Versand'}</strong>
        </div>
        ${neu === 'versand' ? `
          <label style="font-size:12px;color:#64748b">Versandklasse</label>
          <select id="va-klasse" class="erp-select" style="width:100%;margin-bottom:8px">${optionen}</select>` : ''}
        <label style="font-size:12px;color:#64748b">Versandkosten (brutto)</label>
        <input id="va-kosten" type="number" step="0.01" min="0" class="erp-input" style="width:120px;margin-bottom:10px"
               value="${neu === 'abholung' ? '0.00' : ''}"> €
        <div style="font-size:12px;color:#1e40af;background:#eff6ff;border-radius:6px;padding:8px 10px;margin:6px 0 14px">${hinweis}</div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <button class="btn btn-secondary btn-sm" onclick="document.getElementById('ov-versandart').remove()">Abbrechen</button>
          <button class="btn btn-primary btn-sm" id="va-ok">Umstellen</button>
        </div>
      </div>`;
    document.body.appendChild(ov);

    const klasse = document.getElementById('va-klasse');
    const kosten = document.getElementById('va-kosten');
    if (klasse) {
        const setzePreis = () => { kosten.value = Number(klasse.selectedOptions[0]?.dataset.preis || 0).toFixed(2); };
        klasse.addEventListener('change', setzePreis);
        setzePreis();
    }
    document.getElementById('va-ok').onclick = async function () {
        this.disabled = true;
        const body = new FormData();
        body.append('auftrag_id', window.AUFTRAG_ID);
        body.append('lieferart', neu);
        body.append('versandkosten', kosten.value || '0');
        if (klasse) body.append('versandklasse_id', klasse.value);
        const r = await fetch(window.BASE_PATH + '/auftraege/versandart_aendern.php', { method: 'POST', body });
        const d = await r.json();
        if (!d.erfolg) { alert(d.fehler || 'Fehler'); this.disabled = false; return; }
        let msg = 'Versandart geändert.';
        if (d.rest > 0.004 && window.VERSANDART.zahlungsstatus === 'bezahlt') msg +='\nRestbetrag offen: € ' + d.rest.toFixed(2).replace('.', ',') + (d.mail ? ' — Mail an den Kunden verschickt.' : ' — keine Mail (keine E-Mail-Adresse).');
        if (d.guthaben > 0.004) msg += '\nGuthaben des Kunden: € ' + d.guthaben.toFixed(2).replace('.', ',') + ' — die Kasse zahlt es bei der Abholung aus (bar oder Gutschein).';
        alert(msg);
        location.reload();
    };
}

/* ── Rückerstattung an den Kunden (Guthaben nach Rechnungskorrektur/Stornorechnung) ── */
async function rueckerstattungBuchen(auftragId) {
    const betrag = parseFloat(document.getElementById('re-betrag').value.replace(',', '.'));
    if (!betrag || betrag <= 0) { alert('Bitte gültigen Betrag eingeben'); return; }
    if (!confirm('Rückerstattung über € ' + betrag.toFixed(2).replace('.', ',') + ' buchen?')) return;
    const body = new FormData();
    body.append('auftrag_id', auftragId);
    body.append('betrag', betrag);
    body.append('buchungsdatum', document.getElementById('re-datum').value);
    body.append('zahlungsweg', document.getElementById('re-weg').value);
    body.append('rueckerstattung', '1');
    const r = await fetch(window.BASE_PATH + '/auftraege/zahlung_buchen.php', { method: 'POST', body });
    const d = await r.json();
    if (d.erfolg) location.reload(); else alert(d.fehler || 'Fehler beim Buchen');
}
