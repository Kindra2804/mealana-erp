-- JTL "Eigener Export" für den ERP-Lagerabgleich (Lager -> JTL-Abgleich, Schritt 2), Stand 2026-11-11.
-- Liefert ALLE Nicht-Vater-Artikel: mit Bestand je Lager/Lagerplatz/Charge, ohne Bestand als eine Zeile
-- mit leerem Lager (= Bestand 0). Wichtig: Artikel, die in der Datei fehlen, nimmt das ERP aus dem
-- Abgleich heraus (nicht auf 0) -- darum darf der Export nicht gefiltert werden.
-- Aus den Tabellen/Spalten von "CargenArtikelLager_JackyERP_2" zusammengesetzt.
SELECT
    a.cArtNr                                                  AS Artikelnummer,
    wl.kWarenLager                                            AS WarenlagerID,
    ISNULL(wl.cName, '')                                      AS Warenlager,
    ISNULL(wlp.cName, '')                                     AS Lagerplatz,
    ISNULL(wle.cChargenNr, '')                                AS Charge,
    CAST(ISNULL(SUM(wle.fAnzahlAktuell), 0) AS decimal(18,3)) AS BestandAktuell
FROM dbo.tArtikel AS a
LEFT JOIN dbo.tWarenLagerEingang AS wle
    ON wle.kArtikel = a.kArtikel
    AND wle.fAnzahlAktuell > 0
LEFT JOIN dbo.tWarenLagerPlatz AS wlp
    ON wlp.kWarenLagerPlatz = wle.kWarenLagerPlatz
LEFT JOIN dbo.tWarenLager AS wl
    ON wl.kWarenLager = wlp.kWarenLager
WHERE ISNULL(a.nIstVater, 0) = 0
GROUP BY a.cArtNr, wl.kWarenLager, wl.cName, wlp.cName, ISNULL(wle.cChargenNr, '')
ORDER BY a.cArtNr, wl.cName, wlp.cName, ISNULL(wle.cChargenNr, '');
