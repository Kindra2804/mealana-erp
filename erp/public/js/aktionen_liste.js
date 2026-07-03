var delAktionId = null;

function aktionLoeschen(id, name) {
    delAktionId = id;
    document.getElementById('del-info').textContent = 'Aktion "' + name + '" wirklich löschen?';
    document.getElementById('del-modal').style.display = 'flex';
}

document.getElementById('del-btn').addEventListener('click', function () {
    if (!delAktionId) return;
    this.disabled = true;
    fetch(window.BASE_PATH + '/aktionen/aktion_loeschen.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    'id=' + delAktionId
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d.erfolg) window.location.reload();
        else { alert(d.fehler || 'Fehler'); document.getElementById('del-btn').disabled = false; }
    });
});
