function mrNeuBerechnen(input) {
    const row = input.closest('tr');
    const mengeRaus = parseFloat(row.dataset.mengeRaus) || 0;
    const rueck   = parseFloat(row.querySelector('.mr-rueck').value) || 0;
    const schwund = parseFloat(row.querySelector('.mr-schwund').value) || 0;
    const verkauft = mengeRaus - rueck - schwund;
    row.querySelector('.mr-verkauft-zelle').textContent = verkauft;
    row.querySelector('.mr-verkauft-zelle').style.color = verkauft < 0 ? '#dc2626' : '';
}

function mrAbschliessen(syncId, vonLagerId, nachLagerId) {
    const rueckgabe = [];
    const schwund   = [];

    document.querySelectorAll('#mr-tabelle tr').forEach(row => {
        const artikelId = parseInt(row.dataset.artikelId, 10);
        const charge    = row.dataset.charge || null;
        const rueck     = parseFloat(row.querySelector('.mr-rueck').value) || 0;
        const schw      = parseFloat(row.querySelector('.mr-schwund').value) || 0;
        if (rueck > 0)  rueckgabe.push({ artikel_id: artikelId, charge: charge, menge_rueck: rueck });
        if (schw > 0)   schwund.push({ artikel_id: artikelId, charge: charge, menge: schw });
    });

    const fb = document.getElementById('mr-feedback');
    fb.innerHTML = '<div class="ks-feedback info">Wird verarbeitet…</div>';

    const fd = new FormData();
    fd.append('aktion', 'rueckkehr');
    fd.append('sync_id', syncId);
    fd.append('von_lager_id', vonLagerId);
    fd.append('nach_lager_id', nachLagerId);
    fd.append('rueckgabe', JSON.stringify(rueckgabe));
    fd.append('schwund', JSON.stringify(schwund));

    fetch(window.BASE_PATH + '/kasse/ajax_messe.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (!d.erfolg) {
                fb.innerHTML = '<div class="ks-feedback fehler">Fehler: ' + (d.fehler || 'unbekannt') + '</div>';
                return;
            }
            fb.innerHTML = '<div class="ks-feedback ok">Rückkehr verbucht — Restbestand zurückgebucht.</div>';
            setTimeout(() => { window.location = 'messe_rueckkehr.php'; }, 1200);
        })
        .catch(() => {
            fb.innerHTML = '<div class="ks-feedback fehler">Netzwerkfehler.</div>';
        });
}
