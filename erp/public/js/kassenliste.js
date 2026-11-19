// Kassen-Liste / Preisliste (schilder/kassenliste.php): Zähler, Alle/Keine, Barcode nur bei Kassen-Liste
function klZaehlen() {
    var n = document.querySelectorAll('.kl-cb:checked').length;
    var z = document.getElementById('kl-zaehler');
    if (z) z.textContent = n + (n === 1 ? ' Artikel' : ' Artikel') + ' ausgewählt';
    var b = document.getElementById('kl-drucken');
    if (b) b.disabled = n === 0;
}
function klAlle(an) {
    document.querySelectorAll('.kl-cb').forEach(function (c) { c.checked = an; });
    klZaehlen();
}
function klVorlage() {
    var v = document.querySelector('[name="vorlage"]:checked');
    var bc = document.getElementById('kl-barcode');
    if (v && bc) bc.style.display = v.value === 'kassenliste' ? 'flex' : 'none';
    var mini = !!v && v.value === 'mini';
    document.querySelectorAll('.kl-nur-mini').forEach(function (el) {
        el.style.display = mini ? (el.tagName === 'SPAN' && el.parentNode.tagName === 'DIV' && el.querySelector('label') ? 'flex' : '') : 'none';
    });
    document.querySelectorAll('.kl-nur-liste').forEach(function (el) { el.style.display = mini ? 'none' : (el.querySelector('label') ? 'flex' : ''); });
    var b = document.getElementById('kl-drucken');
    if (b) b.textContent = mini ? '💾 Kurznamen speichern + PDF' : '🖨 PDF erzeugen';
}
document.addEventListener('change', function (e) {
    if (e.target.matches('.kl-cb')) klZaehlen();
    if (e.target.matches('[name="vorlage"]')) klVorlage();
});
klZaehlen();
klVorlage();
