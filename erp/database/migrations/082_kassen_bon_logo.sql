-- Migration 082: Bon-Logo Einstellung pro Kasse
-- Steuert ob das Firmenlogo auf dem Kassenbon erscheint

ALTER TABLE kassen
    ADD COLUMN bon_logo TINYINT(1) NOT NULL DEFAULT 1 AFTER aktiv;
