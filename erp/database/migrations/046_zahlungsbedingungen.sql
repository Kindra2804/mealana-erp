-- Zahlungsbedingungen: geteilt zwischen Kunden und Lieferanten
-- z.B. "30 Tage netto", "14/2 30 netto" (14 Tage 2% Skonto, sonst 30 Tage)

CREATE TABLE zahlungsbedingungen (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    beschreibung    VARCHAR(255) NOT NULL DEFAULT '',
    netto_tage      INT NOT NULL DEFAULT 30,
    skonto_prozent  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    skonto_tage     INT NOT NULL DEFAULT 0,
    aktiv           TINYINT(1) NOT NULL DEFAULT 1,
    erstellt_am     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_zb_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO zahlungsbedingungen (name, beschreibung, netto_tage, skonto_prozent, skonto_tage) VALUES
    ('Sofort fällig',   'Zahlung bei Erhalt / Lieferung',                     0,  0.00,  0),
    ('14 Tage netto',   'Zahlung innerhalb 14 Tagen',                         14, 0.00,  0),
    ('30 Tage netto',   'Zahlung innerhalb 30 Tagen',                         30, 0.00,  0),
    ('14/2 30 netto',   '2% Skonto bei Zahlung bis 14 Tage, sonst 30 Tage',  30, 2.00, 14),
    ('Vorauskasse',     'Zahlung vor Lieferung',                               0,  0.00,  0);
