-- Migration 201: Artikelgruppe (Erlöskonto) für Divers + Schnellwahl an der Kasse
--
-- Bis hier landete jede Divers-Position ("+ Freier Artikel", artikel_id NULL) im
-- Buchhaltungs-Export fix auf der Gruppe des Platzhalters 99-9999 (4040 Sonstiges
-- Zubehör). Jetzt wählt die Kasse die Gruppe pro Position (Kacheln im Divers-Dialog
-- oder Gruppen-Taste in der Schnellwahl).

-- Welche Gruppen erscheinen als Kachel an der Kasse + vorbelegter Steuersatz
-- (NULL = Normalsatz 20 %). Neue Gruppen sind standardmäßig an der Kasse wählbar.
ALTER TABLE artikel_gruppen
    ADD COLUMN an_kasse_waehlbar      TINYINT(1)   NOT NULL DEFAULT 1 AFTER aktiv,
    ADD COLUMN standard_steuer_prozent DECIMAL(5,2) NULL     AFTER an_kasse_waehlbar;

-- Reine Buchhaltungs-Gruppen nicht als Kachel anbieten
UPDATE artikel_gruppen SET an_kasse_waehlbar = 0
WHERE konto_nr IN ('3230', '4090', '4890', 'PARTNER');

-- Gewählte Gruppe einer Divers-Position. Hat im Export Vorrang vor der Gruppe
-- des Artikels; bei echten Artikeln bleibt sie NULL (Gruppe kommt vom Artikel).
ALTER TABLE kassen_bon_positionen
    ADD COLUMN artikel_gruppe_id INT UNSIGNED NULL AFTER artikel_id,
    ADD CONSTRAINT fk_kbp_gruppe
        FOREIGN KEY (artikel_gruppe_id) REFERENCES artikel_gruppen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Schnellwahl-Slot ist entweder Artikel-Taste (artikel_id) ODER Gruppen-Taste
-- (artikel_gruppe_id, freier Preis) — nie beides.
ALTER TABLE kassen_schnellwahl
    ADD COLUMN artikel_gruppe_id INT UNSIGNED NULL AFTER artikel_id,
    ADD CONSTRAINT fk_ksw_gruppe
        FOREIGN KEY (artikel_gruppe_id) REFERENCES artikel_gruppen(id)
        ON UPDATE CASCADE ON DELETE SET NULL;
