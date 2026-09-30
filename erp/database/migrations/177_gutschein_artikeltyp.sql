-- Gutschein-Modul: eigener Artikeltyp "Gutschein" als einzige Quelle fuer
-- artikel.ist_gutschein (analog ist_download beim Typ DOWNLOAD). Bisher gab es
-- das Flag nur in der DB, ohne jede Moeglichkeit es in der Oberflaeche zu setzen.
-- ArtikelService gleicht artikel.ist_gutschein beim Speichern mit dem Typ ab und
-- setzt die Steuerklasse auf steuerfrei (Mehrzweckgutschein: USt erst bei
-- Einloesung, siehe project_gutscheine.md).
ALTER TABLE artikel_typen
    ADD COLUMN ist_gutschein TINYINT(1) NOT NULL DEFAULT 0 AFTER ist_set;

INSERT INTO artikel_typen (code, name, teilbar, hat_varianten, hat_lagerstand, ist_download, ist_set, ist_gutschein, sortierung, aktiv)
VALUES ('GUTSCHEIN', 'Gutschein', 0, 0, 0, 0, 0, 1, 7, 1);

-- Artikelgruppe "Gutscheine" existierte schon (Konto 4700), war aber deaktiviert
-- und damit im Artikel-Formular nicht auswaehlbar.
UPDATE artikel_gruppen SET aktiv = 1 WHERE konto_nr = '4700';
