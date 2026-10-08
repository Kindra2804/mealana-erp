-- Migration 202: Papier-Messe (Strichliste + händische Belege)
--
-- Zweite Messe-Variante NEBEN der elektronischen Offline-Kasse: Ware wird wie
-- bisher ins Messe-Lager gebucht, vor Ort wird mit Strichliste + händischen
-- Belegen gearbeitet (kein Gerät, kein Strom/Internet, in D keine TSE-Frage).
-- Zurück in der Firma:
--   * Lager  = Strichliste: verkauft (Stricherl) + zurück gezählt → Schwund = Rest
--   * Umsatz = jeder händische Beleg einzeln als signierter Bon nacherfasst
--     (Einzelaufzeichnungspflicht), Zeilen Artikelgruppe + Betrag
-- Ein Messe-Auftrag entsteht bewusst NICHT (wäre doppelter Umsatz).

-- Variante + Papier-Messe braucht keine Offline-Kasse
ALTER TABLE kassen_messe_sync
    MODIFY COLUMN kasse_id INT UNSIGNED NULL,
    ADD COLUMN variante ENUM('elektronisch','papier') NOT NULL DEFAULT 'elektronisch' AFTER typ,
    -- Rückkehr erledigt (gilt für beide Varianten) — verhindert doppeltes Zurückbuchen
    ADD COLUMN rueckkehr_am DATETIME NULL AFTER abgeschlossen_am;

-- Stricherl = verkaufte Menge laut Strichliste (nur Papier-Messe)
ALTER TABLE kassen_messe_umbuchungen
    ADD COLUMN menge_strich DECIMAL(10,3) NULL AFTER menge_rueck;

-- Freitext-Zeilen der Strichliste (Info-Liste: was ohne Lagerstand verkauft
-- bzw. als Werbung dazugegeben wurde — Umsatz kommt über die Belege)
CREATE TABLE kassen_messe_freitext (
    id                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    sync_id           INT UNSIGNED  NOT NULL,
    bezeichnung       VARCHAR(300)  NOT NULL,
    artikel_gruppe_id INT UNSIGNED  NULL,
    menge             INT UNSIGNED  NOT NULL DEFAULT 1,
    einzelpreis       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    zugabe            TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Werbe-Zugabe, 0 €',
    PRIMARY KEY (id),
    KEY idx_sync (sync_id),
    CONSTRAINT fk_mft_sync   FOREIGN KEY (sync_id) REFERENCES kassen_messe_sync(id) ON DELETE CASCADE,
    CONSTRAINT fk_mft_gruppe FOREIGN KEY (artikel_gruppe_id) REFERENCES artikel_gruppen(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Nacherfasster händischer Beleg: Bezug zur Messe + Original-Nr/-Datum.
-- Signiert wird zum Zeitpunkt der Nacherfassung (erstellt_am), der Buchhaltungs-
-- Export verwendet aber das Belegdatum (richtiger USt-Monat).
-- Bewusst KEIN Unique auf (messe_sync_id, handbeleg_nr): nach Storno muss der
-- Beleg neu erfasst werden können — Doppel-Check im Code (storniert = 0).
ALTER TABLE kassen_bons
    ADD COLUMN messe_sync_id   INT UNSIGNED NULL AFTER web_auftrag_id,
    ADD COLUMN handbeleg_nr    VARCHAR(30)  NULL AFTER messe_sync_id,
    ADD COLUMN handbeleg_datum DATE         NULL AFTER handbeleg_nr,
    ADD KEY idx_messe_handbeleg (messe_sync_id, handbeleg_nr),
    ADD CONSTRAINT fk_kb_messe_sync FOREIGN KEY (messe_sync_id) REFERENCES kassen_messe_sync(id);
