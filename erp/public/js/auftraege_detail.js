/* auftraege/detail.php — Statusänderungen, Tracking, Stornierung */

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
