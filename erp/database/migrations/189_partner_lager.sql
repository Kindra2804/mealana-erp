-- Partner-Lager (Jacky 2026-10-01): ein Lager je Partner (lager_beziehung =
-- 'partner_bestand', partner_id), die Mietfächer des Partners sind Lagerplätze darin.
-- Partnerware = Artikel mit artikel.partner_id, Artikelnummer "XP<Partner-ID>-...",
-- gepflegt nur über die Partner-Detailseite, nie im Onlineshop.

-- Mietfach ↔ Lagerplatz (ein Fach = ein Platz, wandert beim Mieterwechsel mit)
ALTER TABLE lagerplaetze
    ADD COLUMN mietfach_id INT UNSIGNED NULL AFTER lager_id,
    ADD UNIQUE KEY uq_lagerplatz_mietfach (mietfach_id),
    ADD CONSTRAINT fk_lagerplatz_mietfach FOREIGN KEY (mietfach_id) REFERENCES mietfaecher(id) ON DELETE SET NULL;

-- Ein Partner hat höchstens ein Partner-Lager
ALTER TABLE lager ADD UNIQUE KEY uq_lager_partner (partner_id);

-- Belege an den Partner: Übernahmeschein (Ware kommt rein) / Rückgabeschein (Ware geht zurück)
ALTER TABLE dokument_nummern
    MODIFY typ ENUM('rechnung','gutschrift','lieferschein','mietrechnung','abrechnung','auftrag','pickliste',
                    'partner_uebernahme','partner_rueckgabe') NOT NULL;
INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
SELECT 'partner_uebernahme', 'US', YEAR(CURDATE()), 0
WHERE NOT EXISTS (SELECT 1 FROM dokument_nummern WHERE typ = 'partner_uebernahme');
INSERT INTO dokument_nummern (typ, praefix, jahr, letzt_nr)
SELECT 'partner_rueckgabe', 'RS', YEAR(CURDATE()), 0
WHERE NOT EXISTS (SELECT 1 FROM dokument_nummern WHERE typ = 'partner_rueckgabe');

CREATE TABLE IF NOT EXISTS partner_belege (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    partner_id   INT UNSIGNED NOT NULL,
    typ          ENUM('uebernahme','rueckgabe') NOT NULL,
    nummer       VARCHAR(30)  NOT NULL,
    lager_id     INT UNSIGNED NOT NULL,
    notiz        TEXT NULL,
    benutzer_id  INT UNSIGNED NULL,
    erstellt_am  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partner_beleg_nummer (nummer),
    KEY idx_partner_beleg_partner (partner_id, erstellt_am),
    CONSTRAINT fk_pb_partner FOREIGN KEY (partner_id) REFERENCES partner(id),
    CONSTRAINT fk_pb_lager   FOREIGN KEY (lager_id)   REFERENCES lager(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_beleg_positionen (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    beleg_id       INT UNSIGNED NOT NULL,
    artikel_id     INT UNSIGNED NOT NULL,
    artikelnummer  VARCHAR(50)  NOT NULL,
    bezeichnung    VARCHAR(300) NOT NULL,
    menge          DECIMAL(10,3) NOT NULL,
    charge         VARCHAR(255) NULL,
    lagerplatz_id  INT UNSIGNED NULL,
    CONSTRAINT fk_pbp_beleg   FOREIGN KEY (beleg_id)   REFERENCES partner_belege(id) ON DELETE CASCADE,
    CONSTRAINT fk_pbp_artikel FOREIGN KEY (artikel_id) REFERENCES artikel(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Warengruppe für Partnerware (Pflichtfeld am Artikel). Das Konto ist bewusst ein
-- Platzhalter: wie Fremd-/Kommissionsumsätze gebucht werden, klärt die Partner-Abrechnung
-- (mit Babsi) -- bis dahin warnt die Kontenplan-Seite über das fehlende Konto.
INSERT INTO artikel_gruppen (konto_nr, name, aktiv, sortierung)
SELECT 'PARTNER', 'Partnerware (Kommission/Fremd)', 1, 99
WHERE NOT EXISTS (SELECT 1 FROM artikel_gruppen WHERE konto_nr = 'PARTNER');
