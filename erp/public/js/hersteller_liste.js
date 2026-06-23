var EU_ISO  = window.HERSTELLER_EU_ISO  || [];
var hDaten  = window.HERSTELLER_DATEN   || [];
var hAktion = 'neu';

function modalNeuOeffnen() {
    hAktion = 'neu';
    document.getElementById('h-modal-titel').textContent = 'Neuer Hersteller';
    document.getElementById('h-id').value          = '';
    document.getElementById('h-name').value        = '';
    document.getElementById('h-handelsname').value = '';
    document.getElementById('h-land').value        = '';
    document.getElementById('h-email').value       = '';
    document.getElementById('h-webseite').value    = '';
    document.getElementById('h-strasse').value     = '';
    document.getElementById('h-plz').value         = '';
    document.getElementById('h-ort').value         = '';
    document.getElementById('h-reo-name').value    = '';
    document.getElementById('h-reo-strasse').value = '';
    document.getElementById('h-reo-plz').value     = '';
    document.getElementById('h-reo-ort').value     = '';
    document.getElementById('h-reo-land').value    = '';
    document.getElementById('h-reo-email').value   = '';
    document.getElementById('h-notizen').value     = '';
    document.getElementById('h-aktiv').checked     = true;
    document.getElementById('h-logo').value        = '';
    document.getElementById('h-logo-vorschau').style.display = 'none';
    document.getElementById('h-fehler').textContent = '';
    updateReoSichtbarkeit();
    document.getElementById('h-modal').style.display = 'flex';
    document.getElementById('h-name').focus();
}

function modalBearbeiten(id) {
    var h = hDaten.find(function (x) { return x.id == id; });
    if (!h) return;
    hAktion = 'bearbeiten';
    document.getElementById('h-modal-titel').textContent = 'Hersteller: ' + h.name;
    document.getElementById('h-id').value          = h.id;
    document.getElementById('h-name').value        = h.name        || '';
    document.getElementById('h-handelsname').value = h.handelsname || '';
    document.getElementById('h-land').value        = h.land        || '';
    document.getElementById('h-email').value       = h.email       || '';
    document.getElementById('h-webseite').value    = h.webseite    || '';
    document.getElementById('h-strasse').value     = h.strasse     || '';
    document.getElementById('h-plz').value         = h.plz         || '';
    document.getElementById('h-ort').value         = h.ort         || '';
    document.getElementById('h-reo-name').value    = h.reo_name    || '';
    document.getElementById('h-reo-strasse').value = h.reo_strasse || '';
    document.getElementById('h-reo-plz').value     = h.reo_plz     || '';
    document.getElementById('h-reo-ort').value     = h.reo_ort     || '';
    document.getElementById('h-reo-land').value    = h.reo_land    || '';
    document.getElementById('h-reo-email').value   = h.reo_email   || '';
    document.getElementById('h-notizen').value     = h.notizen     || '';
    document.getElementById('h-aktiv').checked     = h.aktiv == 1;
    document.getElementById('h-logo').value        = '';
    document.getElementById('h-fehler').textContent = '';
    var vorschau = document.getElementById('h-logo-vorschau');
    if (h.logo_pfad) { vorschau.src = '/mealana/img/hersteller/' + h.logo_pfad; vorschau.style.display = 'block'; }
    else vorschau.style.display = 'none';
    updateReoSichtbarkeit();
    document.getElementById('h-modal').style.display = 'flex';
    document.getElementById('h-name').focus();
}

function modalSchliessen() { document.getElementById('h-modal').style.display = 'none'; }

function updateReoSichtbarkeit() {
    var land  = document.getElementById('h-land').value;
    var zeigen = land && !EU_ISO.includes(land);
    document.getElementById('h-reo-section').style.display = zeigen ? 'block' : 'none';
}

function logoVorschau(input) {
    var vorschau = document.getElementById('h-logo-vorschau');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) { vorschau.src = e.target.result; vorschau.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

async function modalSpeichern() {
    var btn = document.getElementById('h-speichern-btn');
    btn.disabled = true;
    btn.textContent = 'Speichern…';
    document.getElementById('h-fehler').textContent = '';
    var url = hAktion === 'neu' ? '/mealana/hersteller/speichern.php' : '/mealana/hersteller/aktualisieren.php';
    try {
        var fd   = new FormData(document.getElementById('h-form'));
        var resp = await fetch(url, { method: 'POST', body: fd });
        var data = await resp.json();
        if (data.erfolg) window.location.reload();
        else document.getElementById('h-fehler').textContent = Array.isArray(data.fehler) ? data.fehler.join(' · ') : (data.fehler || 'Unbekannter Fehler');
    } catch (e) {
        document.getElementById('h-fehler').textContent = 'Netzwerkfehler – bitte nochmal versuchen';
    }
    btn.disabled = false;
    btn.textContent = 'Speichern';
}

document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modalSchliessen(); });
