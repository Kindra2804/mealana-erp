-- Migration 218: Superadmin-Übergabe (Lizenz-Bau Schritt 3)
--
-- Jacky 2026-10-30: ein neuer Superadmin NUR per Übergabe-Schlüssel von NAHTLOS (gleiche
-- Signatur wie die Lizenz), nie "einfach so". Schlüssel gebunden an Firmenname + UID,
-- einmalig (nonce wird hier als verbraucht vermerkt), ca. 7 Tage gültig, Eingabe auch
-- ohne Login (Fall: niemand kommt mehr rein). Jacky prüft vorher die Identität (schriftlich
-- vom Inhaber/Geschäftsführer). Alte Superadmins werden herabgestuft oder deaktiviert,
-- nie gelöscht (Logs/Belege).
--
-- benutzer.sitzung_ungueltig_ab: laufende Anmeldungen, die VOR diesem Zeitpunkt begonnen
-- haben, werden beim nächsten Seitenaufruf beendet (z.B. abgelöster Superadmin).

CREATE TABLE IF NOT EXISTS superadmin_uebergaben (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nonce              VARCHAR(64)  NOT NULL,
    schluessel_nr      VARCHAR(30)  NOT NULL,
    neuer_benutzer_id  INT UNSIGNED NOT NULL,
    alte_superadmins   TEXT         NULL COMMENT 'JSON: [{benutzer_id, aktion}]',
    ip_adresse         VARCHAR(45)  NULL,
    verwendet_am       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_uebergabe_nonce (nonce),
    CONSTRAINT fk_uebergabe_benutzer FOREIGN KEY (neuer_benutzer_id) REFERENCES benutzer (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE benutzer
    ADD COLUMN sitzung_ungueltig_ab DATETIME NULL AFTER max_sessions;
