-- Ergänzt 'pickliste' im ENUM der dokument_nummern.typ-Spalte
-- und legt den Zähler-Eintrag an falls noch nicht vorhanden.

ALTER TABLE dokument_nummern
    MODIFY COLUMN typ ENUM(
        'rechnung','gutschrift','lieferschein','mietrechnung',
        'abrechnung','auftrag','pickliste'
    ) NOT NULL;

INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
VALUES ('pickliste', 'PL', YEAR(NOW()), 0);
