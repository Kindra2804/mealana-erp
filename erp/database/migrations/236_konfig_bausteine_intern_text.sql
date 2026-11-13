-- 236: Schild-Bausteine – "nur intern" + Texteingabe (Jacky 2026-11-13)
-- nur_intern:  Wert erscheint NICHT im Shop (Preis-Matrix + Produkt-Attribute), ist aber beim Anlegen
--              fertiger Schilder und an der Kasse wählbar (z.B. "Sonderlackierung", +5 €).
-- texteingabe: Wählt man diesen Wert, muss ein Text eingegeben werden (Personalisierung, z.B.
--              "Metallfeld" / "Name aufgeklebt"). max_zeichen begrenzt die Länge (NULL = 30).
-- Gilt wie Kürzel/Rohmaterial zentral je Achse + Wert-Text für alle Layouts.
-- Wiederholbar (IF NOT EXISTS).

ALTER TABLE konfig_bausteine
    ADD COLUMN IF NOT EXISTS nur_intern  TINYINT(1)        NOT NULL DEFAULT 0    AFTER rohmaterial_artikel_id,
    ADD COLUMN IF NOT EXISTS texteingabe TINYINT(1)        NOT NULL DEFAULT 0    AFTER nur_intern,
    ADD COLUMN IF NOT EXISTS max_zeichen SMALLINT UNSIGNED NULL     DEFAULT NULL AFTER texteingabe;
