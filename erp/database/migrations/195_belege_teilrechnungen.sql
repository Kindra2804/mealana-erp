-- Belege + Abschluss-Umbau (Jacky 2026-10-07), Stufe 1: Datenbasis.
-- Grundregel: jede Ware, die das Haus verlässt, steht auf genau einem Beleg
-- (Rechnung ODER Kassenbon); Umsatz/Buchhaltung kommt nur noch aus Belegen.

-- ── Rechnungen: Teilrechnung pro Lieferung ──────────────────────────────────
-- Bisher eine Rechnung pro Auftrag über alle Positionen. Jetzt beliebig viele
-- (Teil-)Rechnungen; jede kennt ihre eigenen Positionen + Mengen.
ALTER TABLE rechnungen
    ADD COLUMN lieferung_id INT UNSIGNED NULL AFTER auftrag_id,
    ADD COLUMN leistungsdatum DATE NULL AFTER faellig_am,
    ADD COLUMN versandkosten_brutto DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER bruttobetrag,
    ADD COLUMN dateiname VARCHAR(255) NULL AFTER leistungsdatum,
    ADD CONSTRAINT fk_re_lieferung FOREIGN KEY (lieferung_id) REFERENCES auftrag_lieferungen (id) ON DELETE SET NULL;

CREATE TABLE rechnung_positionen (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rechnung_id         INT UNSIGNED NOT NULL,
    auftrag_position_id INT UNSIGNED NULL,
    artikel_id          INT UNSIGNED NULL,
    bezeichnung         VARCHAR(255) NOT NULL,
    menge               INT NOT NULL,
    einzelpreis_netto   DECIMAL(10,4) NOT NULL,
    steuer_prozent      DECIMAL(5,2) NOT NULL,
    rabatt_prozent      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    preisbasis          ENUM('brutto','netto') NOT NULL DEFAULT 'brutto',
    netto               DECIMAL(10,2) NOT NULL,
    steuer              DECIMAL(10,2) NOT NULL,
    brutto              DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_rp_rechnung (rechnung_id),
    KEY idx_rp_position (auftrag_position_id),
    CONSTRAINT fk_rp_rechnung FOREIGN KEY (rechnung_id) REFERENCES rechnungen (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_position FOREIGN KEY (auftrag_position_id) REFERENCES auftrag_positionen (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Gutschriften als echte Tabelle (bisher nur PDF + auftrag_dokumente) ────
CREATE TABLE gutschriften (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gutschrift_nr   VARCHAR(20) NOT NULL,
    auftrag_id      INT UNSIGNED NOT NULL,
    rechnung_id     INT UNSIGNED NULL,
    art             ENUM('vollstorno','teilgutschrift') NOT NULL,
    grund           VARCHAR(500) NULL,
    nettobetrag     DECIMAL(10,2) NOT NULL,
    steuerbetrag    DECIMAL(10,2) NOT NULL,
    bruttobetrag    DECIMAL(10,2) NOT NULL,
    dateiname       VARCHAR(255) NULL,
    erstellt_am     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    erstellt_von    INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gutschrift_nr (gutschrift_nr),
    KEY idx_gs_auftrag (auftrag_id),
    CONSTRAINT fk_gs_auftrag  FOREIGN KEY (auftrag_id)  REFERENCES auftraege (id),
    CONSTRAINT fk_gs_rechnung FOREIGN KEY (rechnung_id) REFERENCES rechnungen (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gutschrift_positionen (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    gutschrift_id       INT UNSIGNED NOT NULL,
    auftrag_position_id INT UNSIGNED NULL,
    artikel_id          INT UNSIGNED NULL,
    bezeichnung         VARCHAR(255) NOT NULL,
    menge               INT NOT NULL,
    einzelpreis_netto   DECIMAL(10,4) NOT NULL,
    steuer_prozent      DECIMAL(5,2) NOT NULL,
    rabatt_prozent      DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    preisbasis          ENUM('brutto','netto') NOT NULL DEFAULT 'brutto',
    netto               DECIMAL(10,2) NOT NULL,
    steuer              DECIMAL(10,2) NOT NULL,
    brutto              DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_gsp_gutschrift (gutschrift_id),
    CONSTRAINT fk_gsp_gutschrift FOREIGN KEY (gutschrift_id) REFERENCES gutschriften (id) ON DELETE CASCADE,
    CONSTRAINT fk_gsp_position FOREIGN KEY (auftrag_position_id) REFERENCES auftrag_positionen (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Zahlungen: Zahlungsweg + Zahlbeleg (für "Zahlungsinfo" auf der Rechnung) ─
-- z.B. "per Überweisung vom 03.10.: 50,00" / "bar an K1 vom 05.10.: 20,00 (Zahlbeleg K1-...)"
ALTER TABLE auftrag_zahlungen
    ADD COLUMN zahlungsweg ENUM('ueberweisung','paypal','bar','karte','gutschein','nachnahme','sonstig') NULL AFTER buchungsdatum,
    ADD COLUMN kassen_bon_id INT UNSIGNED NULL AFTER zahlungsweg;

-- Kassen-Zahlbeleg: Zahlung auf eine bereits gestellte Rechnung (0 % USt, Steuer steht
-- schon auf der Rechnung) — RKSV-pflichtiger Barumsatz, aber kein Erlös.
ALTER TABLE kassen_bons
    MODIFY COLUMN typ ENUM('verkauf','storno','x_bon','z_bon','zahlbeleg') NOT NULL DEFAULT 'verkauf';

-- ── Lieferstatus "Retoure offen" ────────────────────────────────────────────
-- Ware zurückgenommen, am Packplatz noch nicht bearbeitet bzw. noch nicht gutgeschrieben.
ALTER TABLE auftraege
    MODIFY COLUMN lieferstatus ENUM('neu','in_bearbeitung','versandbereit','teilgeliefert','zurueckgestellt',
        'versendet','abgeschlossen','storniert','abholbereit','kommissioniert','retoure_offen') NOT NULL DEFAULT 'neu';

-- ── auftrag_dokumente: Abholzettel fehlte im ENUM (wurde als '' gespeichert) ─
ALTER TABLE auftrag_dokumente
    MODIFY COLUMN typ ENUM('auftragsbestaetigung','lieferschein','rechnung','gutschrift','mahnung','abholzettel') NOT NULL,
    ADD COLUMN beleg_nr VARCHAR(30) NULL AFTER typ;

UPDATE auftrag_dokumente SET typ = 'abholzettel' WHERE typ = '' AND dateiname LIKE 'AZ-%';

-- ── Altbestand übernehmen ───────────────────────────────────────────────────
-- Bisherige Rechnungen waren immer Vollrechnungen über alle Positionen.
INSERT INTO rechnung_positionen
    (rechnung_id, auftrag_position_id, artikel_id, bezeichnung, menge, einzelpreis_netto,
     steuer_prozent, rabatt_prozent, preisbasis, netto, steuer, brutto)
SELECT r.id, p.id, p.artikel_id, p.bezeichnung, p.menge, p.einzelpreis_netto,
       p.steuer_prozent, p.rabatt_prozent, p.preisbasis, p.gesamtpreis_netto,
       ROUND(p.gesamtpreis_netto * p.steuer_prozent / 100, 2),
       ROUND(p.gesamtpreis_netto * (1 + p.steuer_prozent / 100), 2)
FROM rechnungen r
JOIN auftrag_positionen p ON p.auftrag_id = r.auftrag_id;

UPDATE rechnungen r
JOIN auftraege a ON a.id = r.auftrag_id
SET r.leistungsdatum = DATE(r.erstellt_am), r.versandkosten_brutto = COALESCE(a.versandkosten, 0);

UPDATE rechnungen r
JOIN auftrag_dokumente d ON d.auftrag_id = r.auftrag_id AND d.typ = 'rechnung' AND d.dateiname LIKE CONCAT('%', r.rechnung_nr, '%')
SET r.dateiname = d.dateiname;

UPDATE auftrag_dokumente d
JOIN rechnungen r ON r.auftrag_id = d.auftrag_id AND d.dateiname LIKE CONCAT('%', r.rechnung_nr, '%')
SET d.beleg_nr = r.rechnung_nr
WHERE d.typ = 'rechnung';

UPDATE auftrag_dokumente SET beleg_nr = SUBSTRING_INDEX(SUBSTRING_INDEX(dateiname, '_', -1), '.pdf', 1)
WHERE typ = 'gutschrift' AND dateiname LIKE 'GS-%\_GS-%';
