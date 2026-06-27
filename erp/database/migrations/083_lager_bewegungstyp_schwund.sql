-- Migration 083: Schwund als eigener Bewegungstyp in lager_bewegungen
-- Unterscheidet Messe-Schwund (Verlust/Beschädigung) von normalem Warenausgang

ALTER TABLE lager_bewegungen
    MODIFY COLUMN bewegungstyp
        ENUM('eingang','ausgang','korrektur','inventur','schwund')
        NOT NULL DEFAULT 'ausgang';
