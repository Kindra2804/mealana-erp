// Einstellungen → Abholorte: Fenster zum Anlegen/Bearbeiten (Migration 210)
function abholortBearbeiten(ort) {
    const neu = !ort;
    ort = ort || {};
    document.getElementById('ao-titel').textContent = neu ? 'Neuer Abholort' : 'Abholort bearbeiten';
    document.getElementById('ao-id').value = ort.id || '';
    ['name', 'strasse', 'plz', 'ort', 'telefon', 'oeffnungszeiten', 'hinweis'].forEach(feld => {
        document.getElementById('ao-' + feld).value = ort[feld] || '';
    });
    document.getElementById('ao-standard').checked = Number(ort.ist_standard) === 1;
    const lagerIds = (ort.lager_ids || []).map(Number);
    document.querySelectorAll('.ao-lager').forEach(cb => { cb.checked = lagerIds.includes(Number(cb.value)); });
    // Standard und noch nicht gespeicherte Abholorte kann man nicht löschen
    document.getElementById('ao-loeschen').style.visibility = (neu || Number(ort.ist_standard) === 1) ? 'hidden' : 'visible';
    document.getElementById('abholort-modal').style.display = 'flex';
}
