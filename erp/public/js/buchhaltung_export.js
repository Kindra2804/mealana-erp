function zeitraumSetzen(art) {
    var heute = new Date();
    var von, bis;
    if (art === 'monat') {
        von = new Date(heute.getFullYear(), heute.getMonth(), 1);
        bis = new Date(heute.getFullYear(), heute.getMonth() + 1, 0);
    } else if (art === 'quartal') {
        var q = Math.floor(heute.getMonth() / 3);
        von = new Date(heute.getFullYear(), q * 3, 1);
        bis = new Date(heute.getFullYear(), q * 3 + 3, 0);
    } else {
        von = new Date(heute.getFullYear(), 0, 1);
        bis = new Date(heute.getFullYear(), 11, 31);
    }
    document.getElementById('f-von').value = von.toISOString().slice(0, 10);
    document.getElementById('f-bis').value = bis.toISOString().slice(0, 10);
}
