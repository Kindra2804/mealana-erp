-- Migration 130: Platzhalter-Einstellungen für den DATEV-Export (EXTF-Kopfsatz).
-- Leer/0 vorbelegt — müssen vor dem ersten echten DATEV-Export vom Steuerberater
-- erfragt und eingetragen werden (Buchhaltung -> DATEV/CSV-Export).

INSERT INTO system_einstellungen (schluessel, wert) VALUES
    ('datev_berater_nr', ''),
    ('datev_mandant_nr', ''),
    ('datev_wj_beginn', ''),
    ('datev_sachkontenlaenge', '4');
