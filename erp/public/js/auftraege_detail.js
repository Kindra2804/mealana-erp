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
    const r    = await fetch('/mealana/auftraege/zahlung_buchen.php', { method: 'POST', body });
    const data = await r.json();
    if (data.erfolg) {
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
