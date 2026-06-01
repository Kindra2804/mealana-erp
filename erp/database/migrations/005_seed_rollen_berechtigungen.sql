INSERT INTO `rollen`(`name`, `beschreibung`) VALUES ('superadmin','Zugriff auf Alles + API-Zugriff + Benutzerverwaltung'),('admin','Administrator Zugang zu Artikel, Lager, Lieferanten, Berichte'),('mitarbeiter','Lager, Kasse, Packplatz')

INSERT INTO `berechtigungen`(`name`, `beschreibung`) 
VALUES (
    'artikel.anzeigen',
    'artikel anzeigen'
    ),
    (
    'artikel.bearbeiten',
    'artikel bearbeiten'
    ),
    (
    'artikel.anlegen',
    'artikel anlegen'
    ),
    ('artikel.loeschen',
    'artikel löschen'    ),
    ('varianten.anzeigen',
    'varianten anzeigen'    ),
    ('varianten.bearbeiten',
    'varianten bearbeiten'
    ),
    ('varianten.anlegen',
    'varianten anlegen'
    ),
    ('varianten.loeschen',
    'varianten löschen'
    ),
    ('lager.anzeigen',
    'lager anzeigen'
    ),
    ('lager.bearbeiten',
    'lager bearbeiten'
    ),
    ('lager.anlegen',
    'lager anlegen'
    ),
    ('lager.loeschen',
    'lager löschen'
    ),
    ('wareneingang.buchen',
    'wareneingang buchen'
    ),
    ('wareneingang.bearbeiten',
    'wareneingang bearbeiten'
    ),
    ('bestand.anzeigen',
    'bestand anzeigen'
    ),
    ('bestand.bearbeiten',
    'bestand bearbeiten'
    ),
    ('bestand.korrigieren',
    'bestand korrigieren'
    ),
    ('bestand.loeschen',
    'bestand löschen'
    ),
    ('lieferanten.anzeigen',
    'lieferanten anzeigen'
    ),
    ('lieferanten.bearbeiten',
    'lieferanten bearbeiten'
    ),
    ('lieferanten.anlegen',
    'lieferanten anlegen'
    ),
    ('lieferanten.loeschen',
    'lieferanten löschen'
    ),
    ('inventur.anzeigen',
    'inventur anzeigen'
    ),
    ('inventur.bearbeiten',
    'inventur bearbeiten'
    ),
    ('inventur.anlegen',
    'inventur anlegen'
    ),
    ('inventur.loeschen',
    'inventur löschen'
    ),
    ('inventurpositionen.anzeigen',
    'inventurpositionen anzeigen'
    ),
    ('inventurpositionen.bearbeiten',
    'inventurpositionen bearbeiten'
    ),
    ('inventurpositionen.anlegen',
    'inventurpositionen anlegen'
    ),
    ('inventurpositionen.loeschen',
    'inventurpositionen löschen'
    ),
    ('benutzer.anlegen',
    'benutzer anlegen'    ),
    ('benutzer.bearbeiten',
    'benutzer bearbeiten'    ),
    ('benutzer.loeschen',
    'benutzer löschen'    ),
    ('api.zugriff',
    'API Zugriff'    ),
    ('berichte.anzeigen',
    'berichte anzeigen'    ),
    ('berichte.bearbeiten',
    'berichte bearbeiten'    ),
    ('berichte.anlegen',
    'berichte anlegen'    ),
    ('berichte.loeschen',
    'berichte löschen'    ),
    ('berichte.drucken',
    'berichte drucken'    ),
    ('shopabgleich.starten',
    'shopabgleich starten'    ),
    ('shopabgleich.stoppen',
    'shopabgleich stoppen'    ),
    ('packplatz.starten',
    'packplatz starten'    ),
    ('packplatz.stoppen',
    'packplatz stoppen'    ),
    ('kasse.starten',
    'kasse starten'    ),
    ('kasse.stoppen',
    'kasse stoppen'    )
    ;


    -- superadmin bekommt alles
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
SELECT r.id, b.id
FROM rollen r, berechtigungen b
WHERE r.name = 'superadmin';

-- admin bekommt alles außer api, benutzer löschen, shopabgleich
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
SELECT r.id, b.id
FROM rollen r, berechtigungen b
WHERE r.name = 'admin'
AND b.name NOT IN (
    'api.zugriff',
    'benutzer.loeschen',
    'shopabgleich.starten',
    'shopabgleich.stoppen'
);

-- mitarbeiter bekommt nur operative Berechtigungen
INSERT INTO rollen_berechtigungen (rolle_id, berechtigung_id)
SELECT r.id, b.id
FROM rollen r, berechtigungen b
WHERE r.name = 'mitarbeiter'
AND b.name IN (
    'artikel.anzeigen', 'varianten.anzeigen', 'lager.anzeigen',
    'wareneingang.buchen', 'bestand.anzeigen', 'bestand.korrigieren',
    'inventur.anzeigen', 'inventurpositionen.anzeigen',
    'lieferanten.anzeigen', 'berichte.anzeigen', 'berichte.drucken',
    'packplatz.starten', 'packplatz.stoppen',
    'kasse.starten', 'kasse.stoppen'
);

-- Erster Admin-Benutzer
INSERT INTO benutzer (username, passwort, vorname, formularname, email)
VALUES ('admin', '$2y$10$Apn.W3t.e9RPE/8B7I7JQungWu/6MyQDl70iwNOmgqLAUqld9BjR2', 'Admin', 'Administrator', 'indy1@gmx.at');

-- Superadmin-Rolle zuweisen
INSERT INTO benutzer_rollen (benutzer_id, rolle_id)
SELECT b.id, r.id
FROM benutzer b, rollen r
WHERE b.username = 'admin' AND r.name = 'superadmin';
