-- 220: Paperless-Rechnung (Beleg per QR-Code)
-- Der A4-Bon wird als PDF auf die Belegseite (beleg.<domain>, Ordner beleg-portal/) geladen,
-- der Kunde scannt den QR-Code und lädt ihn herunter. Je Bon merken wir uns Link + Abruf.

ALTER TABLE kassen_bons
    ADD COLUMN paperless_token        VARCHAR(64)  NULL COMMENT 'Token auf der Belegseite (48 hex)',
    ADD COLUMN paperless_url          VARCHAR(255) NULL COMMENT 'Abruf-Link für den QR-Code',
    ADD COLUMN paperless_hochgeladen_am DATETIME   NULL,
    ADD COLUMN paperless_abgerufen_am DATETIME     NULL COMMENT 'erster Abruf durch den Kunden';

-- Einstellungen (Einstellungen → System → Paperless). Schlüssel = gleicher Wert wie in beleg-portal/config.php
INSERT IGNORE INTO system_einstellungen (schluessel, wert) VALUES
    ('paperless_url',        ''),
    ('paperless_schluessel', '');
