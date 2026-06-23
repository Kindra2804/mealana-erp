function toggleFirma(isFirma) {
    document.getElementById('feld-firmenname').style.display       = isFirma ? '' : 'none';
    document.getElementById('feld-uid').style.display              = isFirma ? '' : 'none';
    document.getElementById('feld-kreditlimit').style.display      = isFirma ? '' : 'none';
    document.getElementById('feld-geburtsdatum').style.visibility  = isFirma ? 'hidden' : '';
    document.getElementById('nachname-stern').style.display        = isFirma ? 'none' : '';
}
