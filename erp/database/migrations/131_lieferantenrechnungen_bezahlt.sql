-- Migration 131: Zahlungsstatus für Lieferantenrechnungen
-- Bewusst kein neues lieferanten_rechnungen-Tabelle — bestellungen.rechnung_nummer/
-- _betrag/_datum existieren schon (Migration 056), nur das "ist sie bezahlt"-Feld fehlte.

ALTER TABLE bestellungen
    ADD COLUMN rechnung_bezahlt_am DATE NULL COMMENT 'NULL = offen, Datum = bezahlt' AFTER rechnung_datum;
