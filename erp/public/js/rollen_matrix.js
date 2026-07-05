function zeigeBanner(msg, ok) {
    if (ok === undefined) ok = true;
    var b = document.getElementById('banner');
    b.textContent       = msg;
    b.style.background  = ok ? '#2ecc71' : '#e74c3c';
    b.style.color       = '#fff';
    b.style.display     = 'block';
    setTimeout(function () { b.style.display = 'none'; }, 3000);
}

async function berechtigungToggle(checkbox) {
    var rolleId        = checkbox.dataset.rolle;
    var berechtigungId = checkbox.dataset.berechtigung;
    var gewaehrt        = checkbox.checked;

    var fd = new FormData();
    fd.append('rolle_id', rolleId);
    fd.append('berechtigung_id', berechtigungId);
    fd.append('gewaehrt', gewaehrt ? '1' : '0');

    var res  = await fetch(window.BASE_PATH + '/rollen/berechtigung_setzen.php', { method: 'POST', body: fd });
    var data = await res.json();

    if (!data.erfolg) {
        checkbox.checked = !gewaehrt; // zurücksetzen bei Fehler
        zeigeBanner(data.fehler ? data.fehler.join(' | ') : 'Fehler beim Speichern.', false);
    }
}
