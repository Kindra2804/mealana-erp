-- Migration 092: Kassen-Bon ↔ Auftrag Verknüpfung
-- auftraege bekommt kassen_bon_id (gesetzt wenn Kasse die Zahlung übernimmt)
-- kassen_bons bekommt web_auftrag_id (Referenz zum Original-Webauftrag)

ALTER TABLE auftraege
    ADD COLUMN kassen_bon_id INT NULL DEFAULT NULL
        COMMENT 'Gesetzt wenn dieser Auftrag an der Kasse bezahlt wurde — sperrt Rechnung-Erstellung';

ALTER TABLE kassen_bons
    ADD COLUMN web_auftrag_id INT NULL DEFAULT NULL
        COMMENT 'Referenz zum Original-Webauftrag der an der Kasse abgewickelt wurde';
