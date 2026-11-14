-- 243: Mengen-Aktionen an der Kasse (Jacky 2026-11-14)
-- Der Server rechnet die Aktion beim Speichern nach (MengenAktionRechner) und legt die Position mit dem
-- REDUZIERTEN Einzelpreis ab (einzelpreis_brutto, 4 Nachkommastellen, rabatt_prozent bleibt 0).
-- Für Bon-Druck/Journal bleibt der Preis vorher und der Aktionstext erhalten (Aktion kann sich später ändern).
-- Wiederholbar (IF NOT EXISTS)

ALTER TABLE kassen_bon_positionen
    ADD COLUMN IF NOT EXISTS preis_vorher_brutto DECIMAL(10,4) NULL DEFAULT NULL AFTER einzelpreis_brutto,
    ADD COLUMN IF NOT EXISTS mengen_aktion_id INT UNSIGNED NULL DEFAULT NULL AFTER preis_vorher_brutto,
    ADD COLUMN IF NOT EXISTS aktion_hinweis VARCHAR(60) NULL DEFAULT NULL AFTER mengen_aktion_id;
