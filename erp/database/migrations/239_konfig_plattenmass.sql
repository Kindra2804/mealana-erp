-- 239: Schild-Bausteine - Plattenmasse in die Konfiguration (Jacky 2026-11-13)
-- Platten und andere Rohmaterialien werden wie Artikel gepflegt, sind aber nie im Shop
-- bei Platten immer "sicheres Maß", nicht Idealangabe, da oft nicht genau geschnitten, oder schief eingelegt
-- bei 400x400mm Platte also 395x395mm annehmen
-- Schildmaß = Rechteck inkl. Aufhängung (20 cm → 200x209)
-- Wiederholbar (IF NOT EXISTS)

ALTER TABLE konfig_bausteine
    ADD COLUMN IF NOT EXISTS schild_breite_mm SMALLINT UNSIGNED NULL DEFAULT NULL AFTER max_zeichen,
    ADD COLUMN IF NOT EXISTS schild_hoehe_mm SMALLINT UNSIGNED NULL DEFAULT NULL AFTER schild_breite_mm;

ALTER TABLE artikel
    ADD COLUMN IF NOT EXISTS platte_breite_mm SMALLINT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS platte_hoehe_mm SMALLINT UNSIGNED NULL DEFAULT NULL;