var modal     = document.getElementById('gruppe-modal');
var btnLoesen = document.getElementById('btn-loeschen');
var aktuelleId = 0;

function gruppeNeu() {
    aktuelleId = 0;
    document.getElementById('modal-titel').textContent = 'Neue Artikelgruppe';
    document.getElementById('f-id').value       = '';
    document.getElementById('f-konto').value    = '';
    document.getElementById('f-name').value     = '';
    document.getElementById('f-sort').value     = '10';
    document.getElementById('f-aktiv').checked  = true;
    document.getElementById('modal-fehler').textContent = '';
    document.getElementById('gruppe-form').action = '/mealana/buchhaltung/artikel_gruppen_speichern.php';
    btnLoesen.style.display = 'none';
    modal.style.display     = 'flex';
    document.getElementById('f-konto').focus();
}

function gruppeBearbeiten(g) {
    aktuelleId = g.id;
    document.getElementById('modal-titel').textContent = 'Artikelgruppe bearbeiten';
    document.getElementById('f-id').value       = g.id;
    document.getElementById('f-konto').value    = g.konto_nr;
    document.getElementById('f-name').value     = g.name;
    document.getElementById('f-sort').value     = g.sortierung;
    document.getElementById('f-aktiv').checked  = g.aktiv == 1;
    document.getElementById('modal-fehler').textContent = '';
    document.getElementById('gruppe-form').action = '/mealana/buchhaltung/artikel_gruppen_speichern.php';
    btnLoesen.style.display = '';
    modal.style.display     = 'flex';
    document.getElementById('f-name').focus();
}

function gruppeLoeschen() {
    if (!aktuelleId) return;
    if (!confirm('Artikelgruppe wirklich löschen?\nNur möglich wenn keine Artikel oder Versandklassen zugeordnet sind.')) return;
    var f = document.createElement('form');
    f.method  = 'post';
    f.action  = '/mealana/buchhaltung/artikel_gruppen_loeschen.php';
    var inp   = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'id';
    inp.value = aktuelleId;
    f.appendChild(inp);
    document.body.appendChild(f);
    f.submit();
}

function modalSchliessen() {
    modal.style.display = 'none';
}

modal.addEventListener('click', function(e) {
    if (e.target === modal) modalSchliessen();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') modalSchliessen();
});
