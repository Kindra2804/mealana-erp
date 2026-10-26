-- Migration 205: Partner-Verkaufsart + Stammdaten für die Abrechnung
--
-- Klärung Steuerberatung 2026-10-26: Ein Partner ist ENTWEDER
--   fremdrechnung → Verkauf im Namen und auf Rechnung des Partners (nie unser Umsatz, keine RKSV)
--   kommission    → unser eigener Umsatz, Partner stellt uns Rechnung (EK = netto − Provision)
--   spende        → Durchlauf (z.B. Yarnpride), eigene Kassenlade
-- Ein Mietfach ist davon unabhängig (eigene Mietrechnung, nie gegenverrechnet).
-- Darum fallen abrechnungs_modus (Gegenverrechnung) und abrechnungs_beleg_typ weg.

-- 1. Typ → Verkaufsart (alte Werte zuerst zulassen, dann umschlüsseln, dann einschränken)
ALTER TABLE partner
    MODIFY typ ENUM('mietfach','kommission','spende','beides','fremdrechnung') NOT NULL DEFAULT 'fremdrechnung';

UPDATE partner SET typ = 'fremdrechnung' WHERE typ = 'mietfach';
UPDATE partner SET typ = 'kommission'    WHERE typ = 'beides';

ALTER TABLE partner
    MODIFY typ ENUM('fremdrechnung','kommission','spende') NOT NULL DEFAULT 'fremdrechnung';

-- 2. Adresse (Bon-Abschnitt "im Namen und auf Rechnung von", Mietrechnung, Zahlungsaviso)
--    + eigener Kleinunternehmer-Text (leer = Standardtext) + Verknüpfungen:
--    kunde_id     = Debitor für die Fachmiete-Rechnung
--    lieferant_id = Kreditor, von dem die Kommissions-Rechnung kommt
ALTER TABLE partner
    ADD COLUMN strasse      VARCHAR(200) NULL AFTER name,
    ADD COLUMN plz          VARCHAR(10)  NULL AFTER strasse,
    ADD COLUMN ort          VARCHAR(100) NULL AFTER plz,
    ADD COLUMN land         CHAR(2)      NOT NULL DEFAULT 'AT' AFTER ort,
    ADD COLUMN ku_text      VARCHAR(255) NULL AFTER kleinunternehmer,
    ADD COLUMN kunde_id     INT UNSIGNED NULL AFTER ku_text,
    ADD COLUMN lieferant_id INT UNSIGNED NULL AFTER kunde_id,
    ADD CONSTRAINT fk_partner_kunde     FOREIGN KEY (kunde_id)     REFERENCES kunden(id)      ON DELETE SET NULL,
    ADD CONSTRAINT fk_partner_lieferant FOREIGN KEY (lieferant_id) REFERENCES lieferanten(id) ON DELETE SET NULL;

-- 3. Gegenverrechnung + Belegtyp-Auswahl gibt es nicht mehr
ALTER TABLE partner
    DROP COLUMN abrechnungs_modus,
    DROP COLUMN abrechnungs_beleg_typ;

-- 4. artikel.partner_modus kennt jetzt auch 'fremdrechnung' und folgt der Verkaufsart des Partners
--    (Kasse/Bon/Export lesen die Verkaufsart am Artikel, PartnerService hält sie synchron)
ALTER TABLE artikel
    MODIFY partner_modus ENUM('eigen','kommission','spende','fremdrechnung') NOT NULL DEFAULT 'eigen';

UPDATE artikel a
JOIN partner p ON p.id = a.partner_id
SET a.partner_modus = p.typ;
