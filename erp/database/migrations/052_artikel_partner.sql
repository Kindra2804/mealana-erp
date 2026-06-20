-- Artikel ↔ Partner Verknüpfung
--
-- partner_modus = 'eigen':       normaler MeaLana-Artikel
-- partner_modus = 'kommission':  Artikel gehört einem Mietfach-Inhaber (partner_id gesetzt)
-- partner_modus = 'spende':      Artikel einer Spendenorganisation (partner_id gesetzt)
--
-- partner_id darf NULL sein wenn partner_modus = 'eigen'.
-- FK ON DELETE SET NULL: Partner löschen → Artikel fällt auf 'eigen' zurück
-- (SET NULL auf ENUM geht nicht direkt → Trigger oder App-seitig partner_modus auf 'eigen' setzen)

ALTER TABLE artikel
    ADD COLUMN partner_id       INT UNSIGNED    NULL        AFTER hersteller_id,
    ADD COLUMN partner_modus    ENUM('eigen','kommission','spende')
                                NOT NULL DEFAULT 'eigen'    AFTER partner_id,
    ADD CONSTRAINT fk_art_partner
        FOREIGN KEY (partner_id) REFERENCES partner(id) ON DELETE SET NULL,
    ADD INDEX idx_art_partner   (partner_id),
    ADD INDEX idx_art_pmodus    (partner_modus);
