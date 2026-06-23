function adresseEditOeffnen(card) {
    var d = card.dataset;
    document.getElementById('edit-adr-id').value         = d.adrId;
    document.getElementById('edit-adr-typ').value        = d.adrTyp;
    document.getElementById('edit-adr-firma').value      = d.adrFirma;
    document.getElementById('edit-adr-vorname').value    = d.adrVorname;
    document.getElementById('edit-adr-nachname').value   = d.adrNachname;
    document.getElementById('edit-adr-strasse').value    = d.adrStrasse;
    document.getElementById('edit-adr-hausnummer').value = d.adrHausnummer;
    document.getElementById('edit-adr-plz').value        = d.adrPlz;
    document.getElementById('edit-adr-ort').value        = d.adrOrt;
    document.getElementById('edit-adr-land').value       = d.adrLand;
    document.getElementById('edit-adr-zusatz').value     = d.adrZusatz;
    document.getElementById('edit-adr-standard').checked = d.adrStandard === '1';
    document.getElementById('adresse-edit-modal').style.display = 'flex';
}
function adresseEditSchliessen() { document.getElementById('adresse-edit-modal').style.display = 'none'; }
function adresseNeuOeffnen()     { document.getElementById('adresse-neu-modal').style.display  = 'flex'; }
function adresseNeuSchliessen()  { document.getElementById('adresse-neu-modal').style.display  = 'none'; }

setTimeout(function () { var b = document.getElementById('flash-banner'); if (b) b.style.display = 'none'; }, 3000);
