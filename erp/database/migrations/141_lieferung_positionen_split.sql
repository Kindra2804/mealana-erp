-- Packplatz-Teillieferung Phase 2: echter Positions-Split je Lieferung.
-- Bisher stand die Charge nur kumulativ (comma-joined) auf auftrag_positionen.charge,
-- ohne Bezug welche Menge/Charge zu welcher konkreten Teillieferung gehörte.
-- auftrag_lieferungen ist bisher nur ein Kopf-Eintrag (Tracking/Datum) ohne
-- Positions-Bezug -- diese Tabelle ergänzt genau das.
CREATE TABLE auftrag_lieferung_positionen (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    lieferung_id INT UNSIGNED NOT NULL,
    auftrag_position_id INT UNSIGNED NOT NULL,
    menge DECIMAL(10,3) NOT NULL,
    charge VARCHAR(20) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY fk_auflp_lieferung (lieferung_id),
    KEY fk_auflp_position (auftrag_position_id),
    CONSTRAINT fk_auflp_lieferung FOREIGN KEY (lieferung_id) REFERENCES auftrag_lieferungen (id) ON DELETE CASCADE,
    CONSTRAINT fk_auflp_position FOREIGN KEY (auftrag_position_id) REFERENCES auftrag_positionen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einstellbar, ob die Charge auf dem gedruckten Lieferschein erscheint (Jacky
-- 2026-07-19: standardmäßig AUS, intern auf der Auftrags-Detailseite aber immer sichtbar).
INSERT INTO system_einstellungen (schluessel, wert) VALUES
    ('lieferschein_charge_anzeigen', '0');
