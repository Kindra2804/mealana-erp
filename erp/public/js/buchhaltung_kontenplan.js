var modal = document.getElementById('konto-modal');

function kontoNeu() {
    document.getElementById('modal-titel').textContent = 'Neues Konto';
    document.getElementById('f-id').value          = '';
    document.getElementById('f-kontonummer').value = '';
    document.getElementById('f-name').value        = '';
    document.getElementById('f-typ').value          = 'erloes';
    document.getElementById('f-aktiv').checked      = true;
    document.getElementById('modal-fehler').textContent = '';
    document.getElementById('konto-form').action = window.BASE_PATH + '/buchhaltung/kontenplan_speichern.php';
    modal.style.display = 'flex';
    document.getElementById('f-kontonummer').focus();
}

function kontoBearbeiten(k) {
    document.getElementById('modal-titel').textContent = 'Konto bearbeiten';
    document.getElementById('f-id').value          = k.id;
    document.getElementById('f-kontonummer').value = k.kontonummer;
    document.getElementById('f-name').value        = k.name;
    document.getElementById('f-typ').value          = k.typ;
    document.getElementById('f-aktiv').checked      = k.aktiv == 1;
    document.getElementById('modal-fehler').textContent = '';
    document.getElementById('konto-form').action = window.BASE_PATH + '/buchhaltung/kontenplan_speichern.php';
    modal.style.display = 'flex';
    document.getElementById('f-name').focus();
}

function modalSchliessen() {
    modal.style.display = 'none';
}

modal.addEventListener('click', function (e) {
    if (e.target === modal) modalSchliessen();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') modalSchliessen();
});
