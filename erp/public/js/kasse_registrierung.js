// kasse_registrierung.php: überträgt den Geräte-Token aus localStorage ins
// versteckte Formularfeld, bevor abgeschickt wird — die eigentliche Bindung an
// die Kasse passiert serverseitig beim Abschließen (ArbeitsplatzService).
// Falls der Server einen abweichenden, schon bestehenden Token zurückmeldet
// (window.AP_TOKEN_SYNC), wird localStorage danach synchronisiert.

var AP_TOKEN_KEY = 'arbeitsplatz_token';

document.addEventListener('DOMContentLoaded', function () {
    if (window.AP_TOKEN_SYNC) {
        localStorage.setItem(AP_TOKEN_KEY, window.AP_TOKEN_SYNC);
    }

    var form = document.getElementById('kasse-registrierung-form');
    if (form) {
        form.addEventListener('submit', function () {
            document.getElementById('ap-geraete-token').value = localStorage.getItem(AP_TOKEN_KEY) || '';
        });
    }
});
