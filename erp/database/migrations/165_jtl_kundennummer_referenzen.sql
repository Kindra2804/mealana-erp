-- Migration 165: kunden.jtl_kundennummer (Migration 164) durch eine echte
-- Zuordnungstabelle ersetzt.
--
-- Grund: beim ersten echten Import-Lauf hat sich gezeigt, dass mehrere alte
-- JTL-Kundennummern auf DENSELBEN Menschen zeigen können (dieselbe E-Mail,
-- mehrere Alt-Kundendatensätze aus verschiedenen Jahren/Importen in JTL). Eine
-- einzelne UNIQUE-Spalte an kunden kann das nicht abbilden -- jeder erneute
-- Import-Lauf hätte den zuletzt gesehenen Wert überschrieben (Flip-Flop statt
-- sauberem "schon vorhanden, überspringen").

CREATE TABLE kunden_jtl_referenzen (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kunde_id          INT UNSIGNED NOT NULL,
    jtl_kundennummer  VARCHAR(20)  NOT NULL,
    erstellt_am       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_kjtlref_kunde FOREIGN KEY (kunde_id) REFERENCES kunden(id) ON DELETE CASCADE,
    UNIQUE KEY uq_jtl_kundennummer (jtl_kundennummer),
    KEY idx_kjtlref_kunde (kunde_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO kunden_jtl_referenzen (kunde_id, jtl_kundennummer)
SELECT id, jtl_kundennummer FROM kunden WHERE jtl_kundennummer IS NOT NULL;

ALTER TABLE kunden DROP COLUMN jtl_kundennummer;
