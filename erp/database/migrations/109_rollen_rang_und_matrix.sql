-- Erweitert das Rollen/Rechte-Schema auf die Zielvision (siehe project_rechte_rollen.md):
-- 1. rang-Spalte auf rollen: bestimmt, wer wessen Rechte in der künftigen Matrix-UI
--    bearbeiten darf (nur echt niedrigerer Rang als der eigene -- verhindert
--    Selbst-Hochstufung und dass ein neuer "Assistent" den Admin aussperrt).
-- 2. "mitarbeiter" (bisher von niemandem real genutzt) ersetzt durch granularere Rollen.
-- 3. Neue Berechtigungen für Module, die bisher keine hatten (Kunden/Aufträge/Partner/
--    Bestellwesen/Einstellungen/Lizenz/Dashboard/Versand/Buchhaltung + einige gezielte
--    "gefährliche" Sonderrechte für den künftigen Manager-Override).

ALTER TABLE rollen ADD COLUMN rang INT UNSIGNED NOT NULL DEFAULT 0 AFTER beschreibung;

UPDATE rollen SET rang = 100 WHERE name = 'superadmin';
UPDATE rollen SET rang = 90  WHERE name = 'admin';

-- CASCADE löscht automatisch die (ungenutzten) rollen_berechtigungen-Zeilen mit.
DELETE FROM rollen WHERE name = 'mitarbeiter';

INSERT INTO rollen (name, beschreibung, rang, aktiv) VALUES
    ('assistent',  'Wie Admin, aber darf keine Lizenzen verwalten und kann von Admin jederzeit entmachtet werden', 80, 1),
    ('manager',    'Alles außer Einstellungen; gibt Manager-Codes für Geldgeschäfte frei', 70, 1),
    ('kassier',    'Kasse-Betrieb, Artikel/Bestand nur lesend', 50, 1),
    ('lager',      'Lager, Bestellwesen, Wareneingang, Inventur', 50, 1),
    ('packplatz',  'Packplatz, Versand, Retoure erfassen', 50, 1),
    ('praktikant', 'Artikel-Datenpflege ohne Löschrechte, kein Dashboard', 30, 1),
    ('readonly',   'Alle Module nur lesend', 10, 1);

INSERT INTO berechtigungen (name, beschreibung, aktiv) VALUES
    ('kunden.anzeigen',         'kunden anzeigen', 1),
    ('kunden.bearbeiten',       'kunden bearbeiten', 1),
    ('kunden.anlegen',          'kunden anlegen', 1),
    ('kunden.loeschen',         'kunden löschen', 1),
    ('auftraege.anzeigen',      'aufträge anzeigen', 1),
    ('auftraege.anlegen',       'aufträge anlegen', 1),
    ('auftraege.bearbeiten',    'aufträge bearbeiten', 1),
    ('auftraege.stornieren',    'aufträge stornieren', 1),
    ('partner.anzeigen',        'partner anzeigen', 1),
    ('partner.anlegen',         'partner anlegen', 1),
    ('partner.bearbeiten',      'partner bearbeiten', 1),
    ('partner.loeschen',        'partner löschen', 1),
    ('bestellwesen.anzeigen',   'bestellwesen anzeigen', 1),
    ('bestellwesen.anlegen',    'bestellwesen anlegen', 1),
    ('bestellwesen.bearbeiten', 'bestellwesen bearbeiten', 1),
    ('einstellungen.anzeigen',  'einstellungen anzeigen', 1),
    ('einstellungen.bearbeiten','einstellungen bearbeiten', 1),
    ('lizenz.verwalten',        'lizenz verwalten (nur Superadmin, in der Matrix-UI fix gesperrt)', 1),
    ('dashboard.zugriff',       'dashboard zugriff', 1),
    ('kasse.auszahlung',        'kasse auszahlung (künftig Manager-Override)', 1),
    ('kasse.verwaltung',        'kassen-instanzen verwalten', 1),
    ('packplatz.retoure',       'packplatz retoure erfassen', 1),
    ('packplatz.gutschrift',    'packplatz gutschrift auslösen (künftig Manager-Override)', 1),
    ('versand.anzeigen',        'versand anzeigen', 1),
    ('versand.bearbeiten',      'versand bearbeiten', 1),
    ('buchhaltung.anzeigen',    'buchhaltung anzeigen', 1),
    ('benutzer.anzeigen',       'benutzer anzeigen', 1);

-- Superadmin: wirklich alles (auch lizenz.verwalten)
DELETE FROM rollen_berechtigungen WHERE rolle_id = (SELECT id FROM rollen WHERE name = 'superadmin');
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'superadmin'), id FROM berechtigungen;

-- Admin + Assistent: alles außer lizenz.verwalten (identische operative Rechte,
-- der Unterschied liegt nur im rang für die Matrix-Bearbeitungssperre)
DELETE FROM rollen_berechtigungen WHERE rolle_id IN (SELECT id FROM rollen WHERE name IN ('admin', 'assistent'));
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT r.id, b.id
    FROM rollen r
    JOIN berechtigungen b ON b.name != 'lizenz.verwalten'
    WHERE r.name IN ('admin', 'assistent');

-- Manager: alles außer Einstellungen + Lizenz
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT r.id, b.id
    FROM rollen r
    JOIN berechtigungen b ON b.name NOT IN ('lizenz.verwalten', 'einstellungen.anzeigen', 'einstellungen.bearbeiten')
    WHERE r.name = 'manager';

-- Kassier: Kasse-Betrieb + Artikel/Bestand nur lesend, OHNE kasse.auszahlung
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'kassier'), b.id
    FROM berechtigungen b
    WHERE b.name IN ('artikel.anzeigen', 'varianten.anzeigen', 'bestand.anzeigen', 'kasse.starten', 'kasse.stoppen');

-- Lager: komplettes Lager-Domain + Bestellwesen, Artikel nur lesend
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'lager'), b.id
    FROM berechtigungen b
    WHERE b.name IN (
        'artikel.anzeigen', 'varianten.anzeigen', 'lieferanten.anzeigen',
        'lager.anzeigen', 'lager.bearbeiten', 'lager.anlegen', 'lager.loeschen',
        'wareneingang.buchen', 'wareneingang.bearbeiten',
        'bestand.anzeigen', 'bestand.bearbeiten', 'bestand.korrigieren', 'bestand.loeschen',
        'inventur.anzeigen', 'inventur.bearbeiten', 'inventur.anlegen', 'inventur.loeschen',
        'inventurpositionen.anzeigen', 'inventurpositionen.bearbeiten', 'inventurpositionen.anlegen', 'inventurpositionen.loeschen',
        'bestellwesen.anzeigen', 'bestellwesen.anlegen', 'bestellwesen.bearbeiten'
    );

-- Packplatz: scannen/versenden + Retoure ERFASSEN, OHNE Gutschrift auslösen
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'packplatz'), b.id
    FROM berechtigungen b
    WHERE b.name IN ('artikel.anzeigen', 'bestand.anzeigen', 'packplatz.starten', 'packplatz.stoppen', 'packplatz.retoure', 'versand.anzeigen', 'versand.bearbeiten');

-- Praktikant: Artikel-Datenpflege ohne Löschrechte, kein Dashboard
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'praktikant'), b.id
    FROM berechtigungen b
    WHERE b.name IN ('artikel.anzeigen', 'artikel.bearbeiten', 'artikel.anlegen', 'varianten.anzeigen', 'varianten.bearbeiten', 'varianten.anlegen');

-- Readonly: alle *.anzeigen-Rechte + Dashboard, keine Schreibrechte
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
    SELECT (SELECT id FROM rollen WHERE name = 'readonly'), b.id
    FROM berechtigungen b
    WHERE b.name LIKE '%.anzeigen' OR b.name = 'dashboard.zugriff';
