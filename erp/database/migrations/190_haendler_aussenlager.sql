-- Händler-Außenlager (Jacky 2026-10-01): Ware liegt beim Händler (Konsignation), bleibt
-- unser Bestand bis zur Verkaufsmeldung. Lieferung = Umbuchung ins Händler-Lager mit
-- Lieferschein (Händlerpreis netto + empfohlener Endkunden-VK). Verkaufsmeldung → Auftrag
-- (kanal 'haendler', Netto-Preisbasis) + Rechnung, erst dann wird der Bestand abgebucht.
-- Preis gilt ab Lieferung: jede Lieferzeile merkt sich ihren Preis, abgebaut wird FIFO.

-- Standard-Händlerrabatt auf den Endkunden-VK (je Kundengruppe); ein eigener Artikelpreis
-- für die Kundengruppe (artikel_preise) hat Vorrang
-- (kein Vorgabewert: den Rabatt legt Jacky auf der Händler-Seite fest)
ALTER TABLE kundengruppen ADD COLUMN rabatt_prozent DECIMAL(5,2) NULL AFTER typ;
-- Die Seed-Gruppe "Händler" stand auf typ 'endkunde'
UPDATE kundengruppen SET typ = 'haendler' WHERE name LIKE 'H%ndler' AND typ = 'endkunde';

-- Preisbasis je Auftragsposition: Händler-Rechnungen rechnen netto (Netto runden, MwSt
-- aufschlagen), alles andere bleibt brutto (siehe Positionsrechnung)
ALTER TABLE auftrag_positionen ADD COLUMN preisbasis ENUM('brutto','netto') NOT NULL DEFAULT 'brutto' AFTER rabatt_prozent;

ALTER TABLE auftraege MODIFY kanal ENUM('woocommerce','manuell','kasse','jtl_archiv','haendler') NOT NULL DEFAULT 'manuell';

ALTER TABLE dokument_nummern
    MODIFY typ ENUM('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag','pickliste',
                    'partner_uebernahme','partner_rueckgabe',
                    'haendler_lieferung','haendler_ruecknahme','haendler_schwund') NOT NULL;
INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
SELECT t.typ, t.praefix, YEAR(CURDATE()), 0
FROM (SELECT 'haendler_lieferung' typ, 'HL' praefix UNION ALL SELECT 'haendler_ruecknahme', 'HR'
      UNION ALL SELECT 'haendler_schwund', 'HS') t
WHERE NOT EXISTS (SELECT 1 FROM dokument_nummern d WHERE d.typ = t.typ);

CREATE TABLE IF NOT EXISTS haendler_belege (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kunde_id     INT UNSIGNED NOT NULL,
    lager_id     INT UNSIGNED NOT NULL,
    typ          ENUM('lieferung','ruecknahme','schwund','verkauf') NOT NULL,
    nummer       VARCHAR(30) NOT NULL,
    auftrag_id   INT UNSIGNED NULL,         -- bei 'verkauf': der abgerechnete Auftrag
    von_lager_id INT UNSIGNED NULL,         -- bei 'lieferung'/'ruecknahme': eigenes Lager
    notiz        TEXT NULL,
    benutzer_id  INT UNSIGNED NULL,
    erstellt_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_haendler_beleg_nummer (nummer),
    KEY idx_haendler_beleg_kunde (kunde_id, erstellt_am),
    CONSTRAINT fk_hb_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id),
    CONSTRAINT fk_hb_lager FOREIGN KEY (lager_id) REFERENCES lager(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS haendler_beleg_positionen (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    beleg_id              INT UNSIGNED NOT NULL,
    artikel_id            INT UNSIGNED NOT NULL,
    artikelnummer         VARCHAR(50)  NOT NULL,
    bezeichnung           VARCHAR(300) NOT NULL,
    menge                 DECIMAL(10,3) NOT NULL,
    menge_offen           DECIMAL(10,3) NOT NULL DEFAULT 0,  -- nur Lieferung: noch beim Händler (FIFO)
    charge                VARCHAR(255) NULL,
    preis_netto           DECIMAL(10,4) NOT NULL DEFAULT 0,  -- Händlerpreis zum Lieferzeitpunkt
    vk_brutto             DECIMAL(10,2) NULL,                -- empfohlener Endkunden-VK
    steuer_prozent        DECIMAL(5,2) NOT NULL DEFAULT 20,
    lieferung_position_id INT UNSIGNED NULL,                 -- Abbau: aus welcher Lieferzeile
    KEY idx_hbp_fifo (artikel_id, menge_offen),
    CONSTRAINT fk_hbp_beleg   FOREIGN KEY (beleg_id)   REFERENCES haendler_belege(id) ON DELETE CASCADE,
    CONSTRAINT fk_hbp_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
