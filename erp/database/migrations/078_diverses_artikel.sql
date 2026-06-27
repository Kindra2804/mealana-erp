-- Migration 078: Divers-Platzhalter-Artikel für Kassen-Positionsbuchung in auftrag_positionen
-- Artikelnummer 99-9999, kein Lagerstand, ueberverkauf_erlaubt
-- Wird von KassenService als FK-Platzhalter für freie Kassenpositionen (Divers) verwendet

INSERT IGNORE INTO artikel
    (artikelnummer, name, artikeltyp_id, steuerklasse_id, einheit_id,
     ueberverkauf_erlaubt, hat_eigenen_lagerstand, aktiv)
SELECT
    '99-9999',
    'Diverses (Kasse)',
    (SELECT id FROM artikel_typen  WHERE code = 'STANDARD' LIMIT 1),
    (SELECT id FROM steuerklassen  WHERE satz = 20 AND land = 'AT' LIMIT 1),
    (SELECT MIN(id) FROM einheiten),
    1, 0, 1;
