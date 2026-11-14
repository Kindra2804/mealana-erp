-- 246: Einzelartikel-Inventur mit vorher gewähltem Lager (Jacky 2026-11-14)
-- Bisher zeigte die Zählliste eines einzelnen Artikels alle Lager; das Lager musste beim Erfassen
-- gewählt werden. Jetzt kann man es beim Anlegen festlegen (NULL = alle Lager wie bisher).
-- Wiederholbar (IF NOT EXISTS)

ALTER TABLE inventur_laeufe
    ADD COLUMN IF NOT EXISTS lager_id INT UNSIGNED NULL DEFAULT NULL AFTER scope_id;
