var vskModal   = document.getElementById('vsk-modal');
var vskAktId   = 0;

function vskNeu() {
    vskAktId = 0;
    document.getElementById('vsk-modal-titel').textContent = 'Neue Versandklasse';
    document.getElementById('vsk-id').value      = '';
    document.getElementById('vsk-name').value    = '';
    document.getElementById('vsk-code').value    = '';
    document.getElementById('vsk-kuerzel').value = '';
    document.getElementById('vsk-preis').value   = '';
    document.getElementById('vsk-sort').value    = '10';
    document.getElementById('vsk-gruppe').value  = '';
    document.getElementById('vsk-btn-loeschen').style.display = 'none';
    vskModal.style.display = 'flex';
    document.getElementById('vsk-name').focus();
}

function vskBearbeiten(vk) {
    vskAktId = vk.id;
    document.getElementById('vsk-modal-titel').textContent = 'Versandklasse bearbeiten';
    document.getElementById('vsk-id').value      = vk.id;
    document.getElementById('vsk-name').value    = vk.name;
    document.getElementById('vsk-code').value    = vk.code || '';
    document.getElementById('vsk-kuerzel').value = vk.kuerzel || '';
    document.getElementById('vsk-preis').value   = vk.preis_brutto || '';
    document.getElementById('vsk-sort').value    = vk.sortierung;
    document.getElementById('vsk-gruppe').value  = vk.artikel_gruppe_id || '';
    document.getElementById('vsk-btn-loeschen').style.display = '';
    vskModal.style.display = 'flex';
    document.getElementById('vsk-name').focus();
}

function vskLoeschen() {
    if (!vskAktId) return;
    if (!confirm('Versandklasse wirklich löschen?')) return;
    var f = document.createElement('form');
    f.method = 'post';
    f.action = '/mealana/versand/versandklasse_loeschen.php';
    var inp  = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'id';
    inp.value = vskAktId;
    f.appendChild(inp);
    document.body.appendChild(f);
    f.submit();
}

function vskModalSchliessen() {
    vskModal.style.display = 'none';
}

vskModal.addEventListener('click', function(e) {
    if (e.target === vskModal) vskModalSchliessen();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') vskModalSchliessen();
});
