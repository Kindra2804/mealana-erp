-- Migration 129: Mapping-Tabellen Zahlungsart->Konto und Steuerklasse->USt-Konto

-- PayPal-Split von Bank (2800) ergaenzen, wie von Babsi angekuendigt (aktuell noch mit
-- Kennung "PP" auf 2800 gebucht, soll aber ein eigenes Konto werden).
INSERT INTO kontenplan (kontonummer, name, typ) VALUES ('2801', 'PayPal', 'bank');

CREATE TABLE zahlungsart_konten (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    zahlungsart  VARCHAR(30)  NOT NULL,
    konto_id     INT UNSIGNED NULL,
    hinweis      VARCHAR(255) NULL,

    UNIQUE KEY uq_zahlungsart (zahlungsart),
    CONSTRAINT fk_zk_konto FOREIGN KEY (konto_id) REFERENCES kontenplan (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO zahlungsart_konten (zahlungsart, konto_id, hinweis) VALUES
    ('bar',          (SELECT id FROM kontenplan WHERE kontonummer = '2700'), NULL),
    ('karte_extern', (SELECT id FROM kontenplan WHERE kontonummer = '2800'), NULL),
    ('paypal',       (SELECT id FROM kontenplan WHERE kontonummer = '2801'), NULL),
    ('vorkasse',     (SELECT id FROM kontenplan WHERE kontonummer = '2800'), 'Überweisung vorab'),
    ('nachnahme',    (SELECT id FROM kontenplan WHERE kontonummer = '2800'), NULL),
    ('gutschein',    (SELECT id FROM kontenplan WHERE kontonummer = '3203'), NULL),
    ('rechnung',     NULL, 'Kein einfaches Zahlungskonto: Erlös (Warengruppe) + individuelles Debitorenkonto, Ausgleich bei Zahlungseingang über Bank'),
    ('gemischt',     NULL, 'Mehrere Zahlarten in einer Auftrags-Buchung kombiniert — Sonderbehandlung im Export'),
    ('kombi',        NULL, 'Mehrere Zahlarten in einem Kassenbon kombiniert — Sonderbehandlung im Export');

CREATE TABLE steuerklassen_konten (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    steuerklasse_id   INT UNSIGNED NOT NULL,
    steuer_konto_id   INT UNSIGNED NULL,

    UNIQUE KEY uq_steuerklasse (steuerklasse_id),
    CONSTRAINT fk_sk_steuerklasse FOREIGN KEY (steuerklasse_id) REFERENCES steuerklassen (id),
    CONSTRAINT fk_sk_konto        FOREIGN KEY (steuer_konto_id) REFERENCES kontenplan (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO steuerklassen_konten (steuerklasse_id, steuer_konto_id)
SELECT s.id, k.id FROM steuerklassen s
JOIN kontenplan k ON (
    (s.satz = 20.00 AND k.kontonummer = '3520') OR
    (s.satz = 10.00 AND k.kontonummer = '3510') OR
    (s.satz = 13.00 AND k.kontonummer = '3513')
);

-- Steuerfrei (0%) und Sonder-Steuersatz (4,9%) haben aktuell kein eigenes USt-Konto —
-- Zeile trotzdem anlegen (konto_id NULL) damit jede Steuerklasse einen Mapping-Eintrag hat.
INSERT INTO steuerklassen_konten (steuerklasse_id, steuer_konto_id)
SELECT s.id, NULL FROM steuerklassen s
WHERE s.id NOT IN (SELECT steuerklasse_id FROM steuerklassen_konten);
