-- Lagerverwaltungs-UI: bisher gab es keine Seite, die in `lager` schreibt
-- (Lager anlegen/bearbeiten ging nur per SQL). Ergänzt die Felder für die
-- neue Verwaltungs-UI: Offline-Kassen-Eignung, Beziehungstyp (eigen/Partner-
-- Bestand/Händler-Außenlager) + die zugehörigen optionalen Verknüpfungen.
ALTER TABLE lager
    ADD COLUMN fuer_offline_kasse_waehlbar TINYINT(1) NOT NULL DEFAULT 0 AFTER aktiv,
    ADD COLUMN lager_beziehung ENUM('eigen','partner_bestand','haendler_aussenlager') NOT NULL DEFAULT 'eigen' AFTER fuer_offline_kasse_waehlbar,
    ADD COLUMN partner_id INT UNSIGNED NULL AFTER lager_beziehung,
    ADD COLUMN kunde_id INT UNSIGNED NULL AFTER partner_id,
    ADD CONSTRAINT fk_lager_partner FOREIGN KEY (partner_id) REFERENCES partner(id),
    ADD CONSTRAINT fk_lager_kunde  FOREIGN KEY (kunde_id)  REFERENCES kunden(id);
