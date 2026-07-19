-- Logger-UI: Schweregrad je Aktivitäten-Eintrag (info/warn/error), damit die
-- Shell-Bottom-Logger-Zeile und die neue Admin-Aktivitäten-Seite kritische
-- Vorgänge (z.B. fehlgeschlagener Import) farblich hervorheben können.
-- Default 'info' -- bestehende Logger::log()-Aufrufe im Code müssen nicht
-- angefasst werden, nur neue "geht schief"-Stellen setzen künftig warn/error.
ALTER TABLE aktivitaeten
    ADD COLUMN stufe ENUM('info','warn','error') NOT NULL DEFAULT 'info' AFTER details,
    ADD INDEX idx_aktivitaeten_erstellt_am (erstellt_am);

-- Neue Berechtigung für die Admin-Aktivitäten-Seite
INSERT INTO berechtigungen (name, beschreibung, aktiv) VALUES
    ('system.log', 'aktivitäten-log einsehen', 1);

-- Superadmin/Admin/Assistent bekommen sie automatisch (gleiche Gruppe wie
-- die übrigen administrativen Rechte aus Migration 109). Andere Rollen
-- können sie Jacky bei Bedarf über die Rollen-Matrix-UI selbst zuweisen.
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT r.id, b.id
    FROM rollen r
    JOIN berechtigungen b ON b.name = 'system.log'
    WHERE r.name IN ('superadmin', 'admin', 'assistent');
