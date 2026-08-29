-- Gutschein-Modul: Nachverfolgbarkeit bei Teileinloesung (Jacky-Anfrage 2026-08-29).
-- Verknuepft einen neu erzeugten "Rest-Code" mit dem Code, aus dessen
-- Teileinloesung er entstanden ist -- sonst findet Support bei "mein Code
-- funktioniert nicht" (weil laengst durch einen neuen ersetzt) nur eine
-- Sackgasse statt den aktuell gueltigen Nachfolge-Code.
ALTER TABLE gutscheine
    ADD COLUMN vorgaenger_gutschein_id INT UNSIGNED NULL AFTER auftrag_id_ursprung,
    ADD CONSTRAINT fk_gutschein_vorgaenger FOREIGN KEY (vorgaenger_gutschein_id) REFERENCES gutscheine (id);
