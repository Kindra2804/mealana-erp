-- 248: Mobil-Messe (Sunmi, Jacky 2026-11-15)
-- Dritte Messe-Variante neben Papier und elektronisch: ein Handgerät (Sunmi V2, 58-mm-Drucker) erteilt
-- nur den Beleg (Belegerteilungspflicht), OHNE Signatur — wie ein händischer Paragon mit Durchschrift.
-- Nach der Rückkehr werden die Belege an der Signatur-Kasse nacherfasst (je Beleg ein signierter Bon,
-- mit echten Artikeln/Chargen, Lagerabgang aus dem Messe-Lager).
-- Wiederholbar.

ALTER TABLE kassen_messe_sync
    MODIFY variante ENUM('elektronisch','papier','mobil') NOT NULL DEFAULT 'elektronisch';

-- Vom Gerät hochgeladene, noch nicht (oder schon) nacherfasste Belege.
-- daten = JSON mit den Positionen, so wie das Gerät sie gedruckt hat.
CREATE TABLE IF NOT EXISTS kassen_messe_mobilbelege (
    id             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    sync_id        INT UNSIGNED  NOT NULL,
    beleg_nr       VARCHAR(30)   NOT NULL,          -- M<Messe-Nr>-<Gerät>-<laufend>, so am Beleg gedruckt
    geraet         VARCHAR(10)   NOT NULL,
    beleg_zeit     DATETIME      NOT NULL,          -- Zeitpunkt am Gerät
    zahlungsart    ENUM('bar','karte_extern') NOT NULL,
    bruttobetrag   DECIMAL(10,2) NOT NULL,
    daten          LONGTEXT      NOT NULL,
    hochgeladen_am DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hochgeladen_von INT UNSIGNED NULL,
    bon_id         INT UNSIGNED  NULL,              -- signierter Bon nach der Nacherfassung
    nacherfasst_am DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kmm_beleg (sync_id, beleg_nr),
    KEY idx_kmm_offen (sync_id, bon_id),
    CONSTRAINT fk_kmm_sync FOREIGN KEY (sync_id) REFERENCES kassen_messe_sync (id),
    CONSTRAINT fk_kmm_bon  FOREIGN KEY (bon_id)  REFERENCES kassen_bons (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
