function afApplyFilter() {
    var p = new URLSearchParams();
    var suche   = document.getElementById('af-suche').value.trim();
    var benutzer = document.getElementById('af-benutzer').value;
    var modul   = document.getElementById('af-modul').value;
    var tabelle = document.getElementById('af-tabelle').value;
    var stufe   = document.getElementById('af-stufe').value;
    var von     = document.getElementById('af-von').value;
    var bis     = document.getElementById('af-bis').value;

    if (suche)    p.set('suche', suche);
    if (benutzer) p.set('benutzer_id', benutzer);
    if (modul)    p.set('modul', modul);
    if (tabelle)  p.set('tabelle', tabelle);
    if (stufe)    p.set('stufe', stufe);
    if (von)      p.set('von', von);
    if (bis)      p.set('bis', bis);

    window.location = 'aktivitaeten.php?' + p.toString();
}

document.getElementById('af-benutzer').addEventListener('change', afApplyFilter);
document.getElementById('af-modul').addEventListener('change', afApplyFilter);
document.getElementById('af-tabelle').addEventListener('change', afApplyFilter);
document.getElementById('af-stufe').addEventListener('change', afApplyFilter);
