-- Migration 210: Abholorte
--
-- Jacky 2026-10-28: Abholzettel und Abholmail zeigten die Firmenadresse (Firmensitz), abgeholt
-- wird aber in der Wollboutique. Abholorte sind eigene Adressen mit Öffnungszeiten:
--   - ein Abholort kann mehrere Lager haben (lager.abholort_id), ein Lager höchstens einen
--   - Lager ohne eigenen Abholort (z.B. Privathaus-Keller) → Standard-Abholort
--   - kein Abholort angelegt → Firmenadresse wie bisher
-- Der Auftrag merkt sich seinen Abholort (auftraege.abholort_id): beim Packen automatisch aus
-- dem Lager der Ausbuchung, im Auftrag von Hand änderbar. NULL = Standard-Abholort.

CREATE TABLE IF NOT EXISTS abholorte (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    strasse          VARCHAR(150) NOT NULL,
    plz              VARCHAR(10)  NOT NULL,
    ort              VARCHAR(100) NOT NULL,
    telefon          VARCHAR(50)  NULL,
    oeffnungszeiten  TEXT         NULL,
    hinweis          VARCHAR(500) NULL,
    ist_standard     TINYINT(1)   NOT NULL DEFAULT 0,
    erstellt_am      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE lager
    ADD COLUMN abholort_id INT UNSIGNED NULL AFTER kunde_id,
    ADD CONSTRAINT fk_lager_abholort FOREIGN KEY (abholort_id) REFERENCES abholorte (id) ON DELETE SET NULL;

ALTER TABLE auftraege
    ADD COLUMN abholort_id INT UNSIGNED NULL AFTER lieferart,
    ADD CONSTRAINT fk_auftrag_abholort FOREIGN KEY (abholort_id) REFERENCES abholorte (id) ON DELETE SET NULL;

-- Erster Abholort: Wollboutique, Standard, mit dem Ladengeschäft
INSERT INTO abholorte (name, strasse, plz, ort, oeffnungszeiten, hinweis, ist_standard)
SELECT 'Mealana Wollboutique', 'Bahngasse 13', '2700', 'Wiener Neustadt',
       'Mo, Mi – Fr: 09:00 – 12:00 und 14:00 – 18:00 Uhr\nDi: 09:00 – 12:00 und 14:00 – 16:00 Uhr\nSa: 09:00 – 13:00 Uhr',
       'Bitte Abholzettel oder diese E-Mail mitbringen.', 1
WHERE NOT EXISTS (SELECT 1 FROM abholorte);

UPDATE lager SET abholort_id = (SELECT id FROM abholorte WHERE ist_standard = 1 ORDER BY id LIMIT 1)
WHERE typ = 'ladengeschaeft' AND lager_beziehung = 'eigen' AND abholort_id IS NULL;
