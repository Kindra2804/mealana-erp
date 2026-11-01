-- Migration 217: Ablaufdatum des Signaturzertifikats (Lizenz-Bau Schritt 2)
--
-- Jacky 2026-11-01: bei aktiver RKSV soll die Kasse warnen, wenn das Signaturzertifikat
-- abläuft (A-Trust-Karten über den BFR: 5 Jahre). Der BFR liefert über /state nur die
-- Seriennummer (SC), kein Ablaufdatum → Feld in der Registrierung, Vorgabe
-- "Zertifikat gemeldet + 5 Jahre", in den Einstellungen änderbar (Datum auf der Karte).
-- NULL = Vorgabe gilt (wird beim Anzeigen berechnet).

ALTER TABLE bfr_kassen_registrierungen
    ADD COLUMN zertifikat_gueltig_bis DATE NULL AFTER zertifikat_gemeldet_am;
