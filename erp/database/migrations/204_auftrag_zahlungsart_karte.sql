-- Migration 204: Zahlungsart 'karte' für Aufträge
--
-- Kassen-Bons legen einen Spiegel-Auftrag an. Dessen Zahlungsart kannte keine
-- Karte, darum wurde jeder Bankomat-Bon (karte_extern) als 'gemischt' gespeichert.
-- Code erwartete 'karte' schon (zahlung_buchen, ZAHLUNGSWEG_ZU_ZAHLUNGSART).

ALTER TABLE auftraege
    MODIFY COLUMN zahlungsart ENUM('vorkasse','paypal','rechnung','bar','karte','nachnahme','gutschein','gemischt')
        NOT NULL DEFAULT 'vorkasse';

-- Bestehende Kassen-Aufträge reiner Kartenzahlung korrigieren
UPDATE auftraege a
JOIN kassen_bons b ON b.bon_nr COLLATE utf8mb4_unicode_ci = a.auftrag_nr COLLATE utf8mb4_unicode_ci
SET a.zahlungsart = 'karte'
WHERE a.kanal = 'kasse' AND a.zahlungsart = 'gemischt' AND b.zahlungsart = 'karte_extern';
