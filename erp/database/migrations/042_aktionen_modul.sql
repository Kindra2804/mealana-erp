-- Schritt 1: preis_aktionen → aktionen umbenennen
RENAME TABLE preis_aktionen TO aktionen;

-- Schritt 2: aktionen aufräumen — altes Design entfernen, Beschreibung ergänzen
ALTER TABLE aktionen
    DROP COLUMN typ,
    DROP COLUMN gueltig_ab,
    DROP COLUMN gueltig_bis,
    DROP COLUMN aktiv,
    ADD COLUMN beschreibung TEXT NULL AFTER name;

-- Schritt 3: preis_aktionen_positionen → nur noch SALE-Overrides
--   aktion_id raus (SALE ist nicht mehr an eine Aktion gebunden)
--   Zeitraum-Felder + bis_lagerstand_null rein
ALTER TABLE preis_aktionen_positionen
    DROP FOREIGN KEY fk_prAktPos_aktion_id,
    DROP COLUMN aktion_id,
    ADD COLUMN gueltig_ab          DATETIME   NULL              AFTER netto_vk,
    ADD COLUMN gueltig_bis         DATETIME   NULL              AFTER gueltig_ab,
    ADD COLUMN bis_lagerstand_null TINYINT(1) NOT NULL DEFAULT 0 AFTER gueltig_bis;

-- Schritt 4: Kategorie bekommt Aktions-Flag
ALTER TABLE kategorien
    ADD COLUMN ist_aktions_kategorie TINYINT(1) NOT NULL DEFAULT 0 AFTER aktiv;

-- Schritt 5: Aktion ↔ Kategorie + Zeitraum
--   Überschneidungs-Validierung (gleiche Kategorie, verschiedene Aktionen, gleicher Zeitraum)
--   erfolgt auf Anwendungsebene
CREATE TABLE aktionen_kategorien (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aktion_id      INT UNSIGNED NOT NULL,
    kategorie_id   INT UNSIGNED NOT NULL,
    gueltig_ab     DATE         NOT NULL,
    gueltig_bis    DATE         NOT NULL,
    erstellt_am    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aktion_kategorie (aktion_id, kategorie_id),
    CONSTRAINT fk_aktKat_aktion_id
        FOREIGN KEY (aktion_id)    REFERENCES aktionen(id)   ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_aktKat_kat_id
        FOREIGN KEY (kategorie_id) REFERENCES kategorien(id) ON DELETE RESTRICT ON UPDATE CASCADE
);

-- Schritt 6: Preiseingaben pro Aktion + Vater-Artikel + Sub-Achse + Kundengruppe
--   sub_achse_id NULL = Artikel ohne Sub-Achsen (Einheitspreis)
--   UNIQUE greift nur wenn sub_achse_id NOT NULL; NULL-Duplikate werden auf App-Ebene verhindert
CREATE TABLE aktionen_artikel_preise (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aktion_id        INT UNSIGNED NOT NULL,
    artikel_id       INT UNSIGNED NOT NULL,
    sub_achse_id     INT UNSIGNED NULL,
    kundengruppen_id INT UNSIGNED NOT NULL,
    brutto_vk        DECIMAL(8,2) NOT NULL,
    netto_vk         DECIMAL(8,2) NOT NULL,
    erstellt_am      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aktion_artikel_achse_kg (aktion_id, artikel_id, sub_achse_id, kundengruppen_id),
    CONSTRAINT fk_aktArtPr_aktion_id
        FOREIGN KEY (aktion_id)        REFERENCES aktionen(id)         ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT fk_aktArtPr_artikel_id
        FOREIGN KEY (artikel_id)       REFERENCES artikel(id)          ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_aktArtPr_achse_id
        FOREIGN KEY (sub_achse_id)     REFERENCES varianten_achsen(id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_aktArtPr_kg_id
        FOREIGN KEY (kundengruppen_id) REFERENCES kundengruppen(id)    ON DELETE RESTRICT ON UPDATE CASCADE
);
