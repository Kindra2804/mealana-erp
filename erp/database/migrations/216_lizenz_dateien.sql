-- Migration 216: Lizenzdateien (Lizenz-Bau Schritt 1)
--
-- Jacky 2026-11-01: Kauf + Wartung, Lizenz läuft nie ab (nur "Wartung bis"), offline.
-- Die Lizenzdatei ist von Jacky signiert (ECDSA P-256 über OpenSSL, privater Schlüssel nur
-- bei ihm), das ERP prüft mit dem öffentlichen Schlüssel in config/lizenz_public.pem.
-- Gebunden an Firmenname + UID (ersatzweise Steuernummer) aus den Einstellungen.
-- Ohne gültige Datei läuft nur das Grundmodul (keine Testphase: eine einmal aktivierte
-- RKSV-Kasse dürfte man nicht mehr abdrehen).
--
-- Jede eingespielte Datei bleibt als Historie stehen, genau eine ist aktiv.
-- inhalt = die komplette Datei (JSON mit "daten" + "signatur"), wird bei jedem Laden neu geprüft.

CREATE TABLE IF NOT EXISTS lizenz_dateien (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lizenz_nr        VARCHAR(30)  NOT NULL,
    inhalt           TEXT         NOT NULL,
    aktiv            TINYINT(1)   NOT NULL DEFAULT 0,
    eingespielt_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    eingespielt_von  INT UNSIGNED NULL,
    PRIMARY KEY (id),
    KEY idx_lizenz_aktiv (aktiv),
    CONSTRAINT fk_lizenz_benutzer FOREIGN KEY (eingespielt_von) REFERENCES benutzer (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
